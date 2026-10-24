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
 * The first look: a few questions before setting out, of three levels, with «Not yet» always among their answers. The
 * student sees no mark and no right or wrong: the world only says it knows which way to take them. What they answer
 * suggests their step, and their teacher confirms it with a touch.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class entry {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * The first look of the mission of a course for a student, while they have not done it nor played a scene yet; null
     * without it.
     *
     * @param int $courseid
     * @param \stdClass $member
     * @return array|null title, intro, end, questions (prompt, options).
     */
    public static function for_student(int $courseid, \stdClass $member): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $entry = $mission ? ($mission->data['firstlook'] ?? null) : null;
        if (!$entry || (int) ($member->entrystep ?? 0) > 0 || $member->status === 'paused') {
            return null;
        }
        // Before setting out: who has played a scene already is on their way.
        if ($DB->record_exists(self::T . 'sceneattempt', ['charid' => $member->charid, 'courseid' => $courseid])) {
            return null;
        }
        $text = fn($t) => missions::text($t ?? '', (string) $mission->skin, (string) $mission->variant);
        $questions = [];
        foreach ($entry['questions'] ?? [] as $q) {
            $options = array_map($text, (array) ($q['options'] ?? []));
            shuffle($options);
            // «Not yet» is always there, last: guessing is not needed.
            $options[] = get_string('app_entry_notyet', 'local_oksigeniatesape');
            $questions[] = ['prompt' => $text($q['prompt'] ?? ''), 'options' => $options];
        }
        return ['title' => $text($entry['title'] ?? ''), 'intro' => $text($entry['intro'] ?? ''),
            'end' => $text($entry['end'] ?? ''), 'questions' => $questions];
    }

    /**
     * The step some answers suggest: of all of them, how many right. Half or less, first steps; most, core; all but one,
     * further; all, expert.
     *
     * @param array $entry The first look of the mission.
     * @param string[] $answers One for each question, in their order.
     * @param string $skin
     * @param string $variant
     * @return int From 1 to 4.
     */
    public static function suggest(array $entry, array $answers, string $skin, string $variant): int {
        $questions = array_values($entry['questions'] ?? []);
        $right = 0;
        foreach ($questions as $i => $q) {
            $answer = missions::text($q['answer'] ?? '', $skin, $variant);
            $right += \core_text::strtolower(trim((string) ($answers[$i] ?? ''))) === \core_text::strtolower($answer) ? 1 : 0;
        }
        $total = max(1, count($questions));
        if ($right === $total) {
            return 4;
        }
        if ($right === $total - 1 && $total > 3) {
            return 3;
        }
        return $right * 2 > $total ? 2 : 1;
    }

    /**
     * A student answers their first look: it keeps the step it suggests, once, for their teacher.
     *
     * @param int $courseid
     * @param int $userid
     * @param string[] $answers One for each question, in their order.
     * @param int|null $now
     * @return bool Whether it was kept now (not done before).
     */
    public static function answer(int $courseid, int $userid, array $answers, ?int $now = null): bool {
        global $DB;
        $mission = missions::get($courseid);
        $char = characters::get($userid);
        $member = $char ? engine::member($char, $courseid) : null;
        if (!$mission || empty($mission->data['firstlook']) || !$member || $member->status === 'paused') {
            throw new \moodle_exception('noentry', 'local_oksigeniatesape');
        }
        if (count($answers) !== count($mission->data['firstlook']['questions'] ?? [])) {
            throw new \invalid_parameter_exception('One answer for each question');
        }
        if ((int) $member->entrystep > 0) {
            return false;
        }
        $step = self::suggest($mission->data['firstlook'], $answers, (string) $mission->skin, (string) $mission->variant);
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'entrystep' => $step,
            'timemodified' => $now ?? time()]);
        return true;
    }

    /**
     * The teacher puts the steps the first look suggests, for those whose step is another one.
     *
     * @param int $courseid
     * @param int|null $now
     * @return int Students whose step changed.
     */
    public static function apply(int $courseid, ?int $now = null): int {
        global $DB;
        $students = view::students($courseid);
        if (!$students) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
        $members = $DB->get_records_sql(
            'SELECT m.id, m.entrystep
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . "char} c ON c.id = m.charid
              WHERE c.userid $insql AND m.courseid = ? AND m.entrystep > 0 AND m.entrystep <> m.step",
            array_merge($params, [$courseid])
        );
        foreach ($members as $member) {
            $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'step' => (int) $member->entrystep,
                'timemodified' => $now ?? time()]);
        }
        return count($members);
    }
}
