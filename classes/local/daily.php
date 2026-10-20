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
 * The page of the day: each day the creature asks for a page, a quick question from the chapters the student has
 * played. Answering it right feeds the creature and gives breath, never saber, so that it is not a farm; three days in
 * a row and the creature glows. One a day. Questions answered wrong come back first, and then those of the minions of
 * the Embrollo the student has not beaten yet: spaced practice in disguise. Answering right a question of a minion
 * beats it: it is «untangled» in the bestiary of the student.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class daily {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Breath for the page answered right. */
    public const BREATH = 1;

    /** @var int Days in a row for the creature to glow. */
    public const GLOW = 3;

    /**
     * The page of today for a student, as their phone shows it: the question, and once answered, how it went.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int|null $now
     * @return array|null question (text, options), answered, right, mine, correct and why (once answered), streak; null
     *     if there is no page (no creature yet, paused, or no chapter played).
     */
    public static function today(\stdClass $char, \stdClass $member, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        if (empty($char->creature) || $member->status === 'paused') {
            return null;
        }
        $courseid = (int) $member->courseid;
        $day = engine::day_start($now, $courseid);
        $shown = self::shown($char, $courseid, $day, $now);
        $done = $shown && (int) $shown->answer >= 0 ? $shown : null;
        $id = $shown ? $shown->question : null;
        $question = $id ? questions::get($courseid, $id, self::seed($char, $day)) : null;
        if (!$question) {
            return null;
        }
        $view = ['question' => ['text' => $question['text'], 'options' => $question['options']], 'answered' => (bool) $done,
            'right' => $done && $done->success, 'mine' => $done ? (int) $done->answer : -1, 'breath' => self::BREATH,
            'streak' => self::streak($char, $courseid, $day), 'glow' => self::GLOW];
        // The minion of the Embrollo that carries the question, and whether the answer of today untangled it.
        $mission = missions::get($courseid);
        $knot = $mission ? self::minion($mission, (string) $id) : null;
        if ($knot) {
            $view['minion'] = missions::text($knot['name'] ?? '', $mission->skin);
            $beaten = $DB->get_field(self::T . 'found', 'timecreated', ['charid' => $char->id, 'missionid' => $mission->id,
                'kind' => 'untangled', 'code' => (string) $knot['code']]);
            $view['untangled'] = $done && $done->success && $beaten !== false && (int) $beaten >= $day;
        }
        if ($done) {
            $view['correct'] = (int) $question['correct'];
            $view['why'] = (string) ($question['why'] ?? '');
        }
        return $view;
    }

    /**
     * The student answers the page of today, once.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $answer Index in the options as shown.
     * @param int|null $now
     * @return bool It was right.
     */
    public static function answer(\stdClass $char, \stdClass $member, int $answer, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        $courseid = (int) $member->courseid;
        $day = engine::day_start($now, $courseid);
        if (empty($char->creature) || $member->status === 'paused') {
            throw new \moodle_exception('nopage', 'local_oksigeniatesape');
        }
        $shown = self::shown($char, $courseid, $day, $now);
        if ($shown && (int) $shown->answer >= 0) {
            throw new \moodle_exception('pagedone', 'local_oksigeniatesape');
        }
        // The question that was shown, never another one picked now.
        $id = $shown ? $shown->question : null;
        $question = $id ? questions::get($courseid, $id, self::seed($char, $day)) : null;
        if (!$question || !isset($question['options'][$answer])) {
            throw new \moodle_exception('nopage', 'local_oksigeniatesape');
        }
        $right = $answer === (int) $question['correct'];
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->delete_records_select(self::T . 'daily', 'id = ? AND answer < 0', [$shown->id]);
            $DB->insert_record(self::T . 'daily', (object) ['charid' => $char->id, 'courseid' => $courseid, 'daystart' => $day,
                'question' => $id, 'answer' => $answer, 'success' => (int) $right, 'timecreated' => $now]);
        } catch (\dml_write_exception $e) {
            // A second tap at the same time: the first one counts.
            throw new \moodle_exception('pagedone', 'local_oksigeniatesape');
        }
        if ($right) {
            // With «Hungry for pages» as the card of the day, twice.
            engine::breath($member, self::BREATH * (events::active($courseid, 'pageday', $now) ? 2 : 1), 'page', 0, 0, $now);
            // The minion that carried it is beaten, once.
            $mission = missions::get($courseid);
            $knot = $mission ? self::minion($mission, (string) $id) : null;
            $beaten = $knot ? ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'untangled',
                'code' => (string) $knot['code']] : null;
            if ($beaten && !$DB->record_exists(self::T . 'found', $beaten)) {
                $DB->insert_record(self::T . 'found', $beaten + ['courseid' => $courseid, 'timecreated' => $now]);
            }
        }
        $transaction->allow_commit();
        return $right;
    }

    /**
     * The page of a day as it was first shown: its question is kept then (answer -1), so that the one answered is the
     * one seen, even if the student plays a new chapter in between.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int $day
     * @param int $now
     * @return \stdClass|null The row of the day, or null if there is no question for it.
     */
    private static function shown(\stdClass $char, int $courseid, int $day, int $now): ?\stdClass {
        global $DB;
        $key = ['charid' => $char->id, 'courseid' => $courseid, 'daystart' => $day];
        $row = $DB->get_record(self::T . 'daily', $key);
        if ($row) {
            return $row;
        }
        $id = self::pick($char, $courseid, $day);
        if (!$id) {
            return null;
        }
        try {
            $DB->insert_record(self::T . 'daily', (object) ($key + ['question' => $id, 'answer' => -1, 'success' => 0,
                'timecreated' => $now]));
        } catch (\dml_write_exception $e) {
            // Two requests at the same time: the other one kept it, and it is read just below.
            debugging('The page of the day was kept by another request', DEBUG_DEVELOPER);
        }
        return $DB->get_record(self::T . 'daily', $key) ?: null;
    }

    /**
     * The question of a day: from the chapters the student has played, first one they got wrong on an earlier day,
     * then one never asked, then any; always the same for that day.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int $day Start of the day.
     * @return string|null Its id.
     */
    private static function pick(\stdClass $char, int $courseid, int $day): ?string {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || empty($mission->data['questions'])) {
            return null;
        }
        $played = $DB->get_fieldset_sql(
            "SELECT DISTINCT c.code
               FROM {" . self::T . "found} f
               JOIN {" . self::T . "scene} s ON s.missionid = f.missionid AND s.code = f.code
               JOIN {" . self::T . "chapter} c ON c.id = s.chapterid
              WHERE f.charid = ? AND f.missionid = ? AND f.kind = 'scene'",
            [$char->id, $mission->id]
        );
        $eligible = array_values(array_filter(questions::usable($mission), fn($q) => in_array($q['chapter'] ?? '', $played, true)));
        if (!$eligible) {
            return null;
        }
        $history = $DB->get_records_select(
            self::T . 'daily',
            'charid = ? AND courseid = ? AND daystart < ? AND answer >= 0',
            [$char->id, $courseid, $day],
            'daystart DESC',
            'id, question, success'
        );
        $asked = [];
        foreach ($history as $row) {
            // The latest answer to each question is what counts.
            $asked[$row->question] = $asked[$row->question] ?? (bool) $row->success;
        }
        $wrong = array_values(array_filter($eligible, fn($q) => isset($asked[$q['id']]) && !$asked[$q['id']]));
        $fresh = array_values(array_filter($eligible, fn($q) => !isset($asked[$q['id']])));
        // After what went wrong, the minions not beaten yet: a question of theirs not asked, or else any of theirs.
        $mine = "charid = ? AND missionid = ? AND kind = 'untangled'";
        $beaten = $DB->get_fieldset_select(self::T . 'found', 'code', $mine, [$char->id, $mission->id]);
        $standing = function (array $q) use ($mission, $beaten): bool {
            $knot = self::minion($mission, (string) $q['id']);
            return $knot && !in_array((string) $knot['code'], $beaten, true);
        };
        $minions = array_values(array_filter($eligible, $standing));
        $list = $wrong ?: (array_values(array_filter($fresh, $standing)) ?: ($minions ?: ($fresh ?: $eligible)));
        return $list[self::seed($char, $day) % count($list)]['id'];
    }

    /**
     * Days in a row with the page answered right, up to today (or up to yesterday, while today's is still to do).
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int $day Start of today.
     * @return int
     */
    private static function streak(\stdClass $char, int $courseid, int $day): int {
        global $DB;
        $rows = $DB->get_records_select_menu(
            self::T . 'daily',
            'charid = ? AND courseid = ? AND daystart <= ? AND answer >= 0',
            [$char->id, $courseid, $day],
            'daystart DESC',
            'daystart, success',
            0,
            60
        );
        $streak = 0;
        $expected = isset($rows[$day]) ? $day : $day - DAYSECS;
        foreach ($rows as $when => $right) {
            // Days are 24 h apart except across a change of time: a few hours either way still follow.
            if (abs((int) $when - $expected) > 3 * HOURSECS || !$right) {
                break;
            }
            $streak++;
            $expected = (int) $when - DAYSECS;
        }
        return $streak;
    }

    /**
     * The minion of the Embrollo that carries a question: the knot of the battle with it among its questions.
     *
     * @param \stdClass $mission With data.
     * @param string $questionid
     * @return array|null The knot (code, name, claim, weakness…).
     */
    public static function minion(\stdClass $mission, string $questionid): ?array {
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if (in_array($questionid, (array) ($knot['questions'] ?? []), true)) {
                return $knot;
            }
        }
        return null;
    }

    /**
     * The bestiary of a student: the minions of the Embrollo of the places they have played, as cards, and whether they
     * have beaten each one with the page of the day.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return array name, claim, weakness, untangled; in the order of the places.
     */
    public static function bestiary(\stdClass $char, int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        $played = $DB->get_fieldset_sql(
            "SELECT DISTINCT c.code
               FROM {" . self::T . "found} f
               JOIN {" . self::T . "scene} s ON s.missionid = f.missionid AND s.code = f.code
               JOIN {" . self::T . "chapter} c ON c.id = s.chapterid
              WHERE f.charid = ? AND f.missionid = ? AND f.kind = 'scene'",
            [$char->id, $mission->id]
        );
        $codes = array_column($mission->data['chapters'] ?? [], 'code');
        $mine = "charid = ? AND missionid = ? AND kind = 'untangled'";
        $beaten = $DB->get_fieldset_select(self::T . 'found', 'code', $mine, [$char->id, $mission->id]);
        $cards = [];
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            $place = $codes[(int) ($knot['chapter'] ?? 0) - 1] ?? '';
            if (!in_array($place, $played, true)) {
                continue;
            }
            $cards[] = ['code' => (string) $knot['code'], 'name' => missions::text($knot['name'] ?? '', $mission->skin),
                'claim' => missions::text($knot['claim'] ?? '', $mission->skin),
                'weakness' => missions::text($knot['weakness'] ?? '', $mission->skin),
                'untangled' => in_array((string) $knot['code'], $beaten, true)];
        }
        return $cards;
    }

    /**
     * The seed of a day for a character: the question and the order of its options do not change during the day.
     *
     * @param \stdClass $char
     * @param int $day
     * @return int
     */
    private static function seed(\stdClass $char, int $day): int {
        return crc32('tesape-page-' . $char->id . '-' . $day);
    }
}
