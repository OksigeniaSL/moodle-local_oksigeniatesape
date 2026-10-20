<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_oksigeniatesape\local;

/**
 * The minions of the Embrollo on the map: in the place the class is opening and has not lit yet, the first minion of
 * the place a student has not untangled stands on the road to it, with its claim and a question of its knot. Right, it
 * is untangled (as with the page of the day) and gives a gem; wrong, it laughs and waits until tomorrow. When the class
 * lights the lantern of the place, its minions are gone. Nothing of the class waits for them.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class minions {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Gems for a minion untangled. */
    public const GEMS = 1;

    /**
     * The minion that stands on the road to a place for a student today, with the question of today.
     *
     * @param \stdClass $char
     * @param \stdClass $mission With data.
     * @param string $place Code of the place.
     * @param int|null $now
     * @return array|null code, name, claim, picture, question, options, tried; null if none is left there.
     */
    public static function on_road(\stdClass $char, \stdClass $mission, string $place, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        $knot = self::knot_of($char, $mission, $place);
        if (!$knot) {
            return null;
        }
        $day = engine::day_start($now, (int) $mission->courseid);
        $question = self::question($char, $mission, $knot, $day);
        if (!$question) {
            return null;
        }
        $tried = $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id,
            'kind' => 'miniontry', 'code' => $knot['code'] . '-' . $day]);
        return ['code' => (string) $knot['code'], 'name' => missions::text($knot['name'] ?? '', $mission->skin),
            'claim' => missions::text($knot['claim'] ?? '', $mission->skin), 'picture' => 'minion.' . $knot['code'],
            'question' => $question['text'], 'options' => $question['options'], 'tried' => $tried];
    }

    /**
     * A student answers the question of the minion on the road, once a day.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param string $code The minion.
     * @param int $answer Index in the options as shown.
     * @param int|null $now
     * @return array right, correct, why, gems, weakness (once untangled).
     */
    public static function answer(\stdClass $char, \stdClass $member, string $code, int $answer, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $courseid = (int) $member->courseid;
        $mission = missions::get($courseid);
        $knot = null;
        foreach ($mission ? self::places($mission) : [] as $place) {
            $standing = self::knot_of($char, $mission, $place);
            if ($standing && (string) $standing['code'] === $code) {
                $knot = $standing;
            }
        }
        if (!$knot || $member->status === 'paused') {
            throw new \moodle_exception('nominion', 'local_oksigeniatesape');
        }
        $day = engine::day_start($now, $courseid);
        $question = self::question($char, $mission, $knot, $day);
        if (!$question || !isset($question['options'][$answer])) {
            throw new \moodle_exception('nominion', 'local_oksigeniatesape');
        }
        $try = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'miniontry', 'code' => $code . '-' . $day];
        if ($DB->record_exists(self::T . 'found', $try)) {
            throw new \moodle_exception('miniontried', 'local_oksigeniatesape');
        }
        $DB->insert_record(self::T . 'found', $try + ['courseid' => $courseid, 'timecreated' => $now]);
        $right = $answer === (int) $question['correct'];
        $out = ['right' => $right, 'correct' => (int) $question['correct'], 'why' => (string) ($question['why'] ?? ''),
            'gems' => 0, 'weakness' => ''];
        if ($right) {
            $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'missionid' => $mission->id, 'kind' => 'untangled', 'code' => $code, 'timecreated' => $now]);
            // With «Minions out walking» as the card of the day, twice.
            $gems = self::GEMS * (events::active($courseid, 'minionday', $now) ? 2 : 1);
            if (engine::gems($member, $gems, 'minion', 0, 0, 0, $now)) {
                $out['gems'] = $gems;
            }
            $out['weakness'] = missions::text($knot['weakness'] ?? '', $mission->skin);
        }
        return $out;
    }

    /**
     * The places of a mission where a minion can stand: the one the class is opening (after the last lantern lit),
     * while its lantern is not lit.
     *
     * @param \stdClass $mission
     * @return string[] Codes.
     */
    public static function places(\stdClass $mission): array {
        global $DB;
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder', 'id, code, litat');
        $out = [];
        $opened = 0;
        $i = 0;
        foreach ($chapters as $chapter) {
            $i++;
            if ($chapter->litat) {
                $opened = $i;
            }
        }
        $i = 0;
        foreach ($chapters as $chapter) {
            $i++;
            if (!$chapter->litat && $i <= $opened + 1) {
                $out[] = (string) $chapter->code;
            }
        }
        return $out;
    }

    /**
     * The first minion of a place a student has not untangled, if the place is one where they stand.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $place
     * @return array|null The knot.
     */
    private static function knot_of(\stdClass $char, \stdClass $mission, string $place): ?array {
        global $DB;
        if (!in_array($place, self::places($mission), true)) {
            return null;
        }
        $index = array_search($place, array_column($mission->data['chapters'] ?? [], 'code'), true);
        if ($index === false) {
            return null;
        }
        $beaten = $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            "charid = ? AND missionid = ? AND kind = 'untangled'",
            [$char->id, $mission->id]
        );
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if ((int) ($knot['chapter'] ?? 0) === $index + 1 && !in_array((string) $knot['code'], $beaten, true)) {
                return $knot;
            }
        }
        return null;
    }

    /**
     * The question of a knot a minion asks a student on a day: one of its knot, the same all day.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param array $knot
     * @param int $day
     * @return array|null As questions::get.
     */
    private static function question(\stdClass $char, \stdClass $mission, array $knot, int $day): ?array {
        $ids = array_values((array) ($knot['questions'] ?? []));
        if (!$ids) {
            return null;
        }
        $seed = crc32('tesape-minion-' . $char->id . '-' . $knot['code'] . '-' . $day);
        return questions::get((int) $mission->courseid, (string) $ids[$seed % count($ids)], $seed);
    }
}
