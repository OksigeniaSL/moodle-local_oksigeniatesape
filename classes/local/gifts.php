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
 * The gift of the place: when the class is in a place, each team chooses one of the gifts of the mission for it, each
 * good for something and not for the rest. The lamp: the first hint of each scene of the place is free. The heron's step:
 * each scene of the place that goes well gives a gem more. The breath of the wood: the reserve of the team gets breath at
 * once. Once a place, and it stays.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gifts {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] What each gift does, by its code. */
    public const CODES = ['lamp', 'heron', 'breath'];

    /** @var int Breath the breath of the wood puts in the reserve of the team. */
    public const BREATH = 4;

    /**
     * The gifts of the mission of a course, in its story.
     *
     * @param \stdClass $mission
     * @return array Each: code, name, text.
     */
    public static function of_mission(\stdClass $mission): array {
        $text = fn($t) => missions::text($t ?? '', (string) $mission->skin, (string) $mission->variant);
        return array_values(array_map(
            fn($g) => ['code' => (string) $g['code'], 'name' => $text($g['name']), 'text' => $text($g['text'])],
            array_filter($mission->data['gifts'] ?? [], fn($g) => in_array($g['code'] ?? '', self::CODES, true))
        ));
    }

    /**
     * The gifts a team chose: place => gift.
     *
     * @param int $groupid
     * @return array
     */
    public static function chosen(int $groupid): array {
        global $DB;
        return json_decode((string) $DB->get_field(self::T . 'team', 'gifts', ['groupid' => $groupid]), true) ?: [];
    }

    /**
     * Whether the team of a student has a gift in a place.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $chapterid The place, by its id.
     * @param string $code
     * @return bool
     */
    public static function has(int $courseid, int $userid, int $chapterid, string $code): bool {
        global $DB;
        $team = teams::of($courseid, $userid);
        if (!$team || !$chapterid) {
            return false;
        }
        $place = (string) $DB->get_field(self::T . 'chapter', 'code', ['id' => $chapterid]);
        return (self::chosen((int) $team->id)[$place] ?? '') === $code;
    }

    /**
     * A student chooses the gift of their team for the place where the class is now: once a place.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param int|null $now
     * @return array The gifts of the team: place => gift.
     */
    public static function choose(int $courseid, int $userid, string $code, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $team = teams::of($courseid, $userid);
        $mission = missions::get($courseid);
        $place = $mission ? pacts::chapter($mission) : null;
        if (!$team || !$place || !in_array($code, array_column(self::of_mission($mission), 'code'), true)) {
            throw new \invalid_parameter_exception('Not a gift of the place');
        }
        // The row of the team, made if it is not there yet.
        reserve::row($courseid, (int) $team->id, $now);
        $chosen = self::chosen((int) $team->id);
        if (isset($chosen[$place->code])) {
            throw new \moodle_exception('giftchosen', 'local_oksigeniatesape');
        }
        $chosen[$place->code] = $code;
        $DB->set_field(self::T . 'team', 'gifts', json_encode($chosen), ['groupid' => $team->id]);
        if ($code === 'breath') {
            reserve::credit($courseid, (int) $team->id, 'gift-' . $place->code, self::BREATH, $now);
        }
        return $chosen;
    }

    /**
     * The gift of the place where the class is now, for a student: the place, the gifts to choose from, and the one their
     * team chose.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null place (name), gifts, chosen (code, empty if none).
     */
    public static function for_student(int $courseid, int $userid): ?array {
        $mission = missions::get($courseid);
        $team = teams::of($courseid, $userid);
        $place = $mission ? pacts::chapter($mission) : null;
        $list = $mission ? self::of_mission($mission) : [];
        if (!$team || !$place || !$list) {
            return null;
        }
        $name = '';
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            if ((string) $chapter['code'] === (string) $place->code) {
                $name = missions::text($chapter['name'] ?? $place->code, (string) $mission->skin, (string) $mission->variant);
            }
        }
        return ['place' => $name, 'gifts' => $list, 'chosen' => (string) (self::chosen((int) $team->id)[$place->code] ?? '')];
    }
}
