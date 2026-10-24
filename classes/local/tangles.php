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
 * Be the Embrollo: a team writes a tangle, as the Embrollo would say it (a false idea), with what is true, another wrong
 * idea if it wants and why. The teacher reads it before anybody else; once approved, the other teams undo it from the
 * map, the class hunts it on the class screen, and the teacher can take it to the battle. Making the mistake asks for
 * understanding it. One tangle a week for each team.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class tangles {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Longest text of what the Embrollo says, what is true and the other wrong idea. */
    public const MAX_LINE = 160;

    /** @var int Longest text of why. */
    public const MAX_WHY = 300;

    /** @var int Gems for each member of the team, when the teacher approves its tangle. */
    public const GEMS_WRITTEN = 2;

    /** @var int Gems for undoing the tangle of another team at the first try. */
    public const GEMS_UNDONE = 1;

    /** @var int Most tangles of other teams waiting on the map of a student. */
    private const SHOWN = 5;

    /**
     * A team writes its tangle: it waits for the teacher. A new one takes the place of the one waiting; with one approved
     * this week, the team waits for the next.
     *
     * @param int $courseid
     * @param int $userid Who writes it.
     * @param array $tangle claim, truth, trap (may be empty), why.
     * @param int|null $now
     * @return \stdClass The tangle.
     */
    public static function write(int $courseid, int $userid, array $tangle, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $team = teams::of($courseid, $userid);
        if (!$char || !$team) {
            throw new \moodle_exception('noteam', 'local_oksigeniatesape');
        }
        $line = fn($key) => \core_text::substr(trim(clean_param((string) ($tangle[$key] ?? ''), PARAM_TEXT)), 0, self::MAX_LINE);
        $clean = [
            'claim' => $line('claim'),
            'truth' => $line('truth'),
            'trap' => $line('trap'),
            'why' => \core_text::substr(trim(clean_param((string) ($tangle['why'] ?? ''), PARAM_TEXT)), 0, self::MAX_WHY),
        ];
        if ($clean['claim'] === '' || $clean['truth'] === '' || $clean['why'] === '') {
            throw new \moodle_exception('tangleempty', 'local_oksigeniatesape');
        }
        $mine = self::of_team($courseid, (int) $team->id, $now);
        if ($mine && $mine->status === 'approved') {
            throw new \moodle_exception('tanglewait', 'local_oksigeniatesape');
        }
        if ($mine && $mine->status === 'pending') {
            $DB->update_record(self::T . 'tangle', (object) (['id' => $mine->id, 'charid' => $char->id,
                'timemodified' => $now] + $clean));
            return $DB->get_record(self::T . 'tangle', ['id' => $mine->id], '*', MUST_EXIST);
        }
        $id = $DB->insert_record(self::T . 'tangle', (object) ($clean + ['courseid' => $courseid, 'groupid' => $team->id,
            'charid' => $char->id, 'status' => 'pending', 'reviewedby' => 0, 'inbattle' => 0, 'timecreated' => $now,
            'timemodified' => $now]));
        return $DB->get_record(self::T . 'tangle', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The tangle of a team this week (waiting, approved or turned down), or the one still waiting from before.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function of_team(int $courseid, int $groupid, ?int $now = null): ?\stdClass {
        global $DB;
        $week = engine::week_start($now ?? time(), $courseid);
        $records = $DB->get_records_select(
            self::T . 'tangle',
            "courseid = ? AND groupid = ? AND (status = 'pending' OR timecreated >= ?)",
            [$courseid, $groupid, $week],
            'timecreated DESC, id DESC',
            '*',
            0,
            1
        );
        return $records ? reset($records) : null;
    }

    /**
     * What the team of a student sees of its own tangle: how it is and, approved, how many classmates undid it.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return array|null id, status, claim, truth, trap, why, undone (students who undid it), tried.
     */
    public static function for_team(int $courseid, int $userid, ?int $now = null): ?array {
        global $DB;
        $team = teams::of($courseid, $userid);
        $tangle = $team ? self::of_team($courseid, (int) $team->id, $now) : null;
        if (!$tangle) {
            return null;
        }
        $tries = $tangle->status === 'approved' ? self::tries($courseid, (int) $tangle->id) : ['tried' => 0, 'undone' => 0];
        return ['id' => (int) $tangle->id, 'status' => (string) $tangle->status, 'claim' => (string) $tangle->claim,
            'truth' => (string) $tangle->truth, 'trap' => (string) $tangle->trap, 'why' => (string) $tangle->why] + $tries;
    }

    /**
     * How many students tried a tangle and how many undid it, without names.
     *
     * @param int $courseid
     * @param int $tangleid
     * @return array tried, undone.
     */
    private static function tries(int $courseid, int $tangleid): array {
        global $DB;
        $where = "courseid = ? AND kind IN ('tangle', 'tangled') AND code = ?";
        $kinds = $DB->get_records_select_menu(self::T . 'found', $where, [$courseid, (string) $tangleid], '', 'id, kind');
        return ['tried' => count($kinds), 'undone' => count(array_filter($kinds, fn($k) => $k === 'tangle'))];
    }

    /**
     * The tangles of other teams a student has not tried yet, the newest first: what the Embrollo says, and the answers to
     * choose from in an order of their own (what is true, the other wrong idea, and that the Embrollo is right).
     *
     * @param int $courseid
     * @param int $userid
     * @return array Each: id, team (name), claim, options.
     */
    public static function for_student(int $courseid, int $userid): array {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            return [];
        }
        $team = teams::of($courseid, $userid);
        $tried = $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            "charid = ? AND courseid = ? AND kind IN ('tangle', 'tangled')",
            [$char->id, $courseid]
        );
        $records = $DB->get_records_select(
            self::T . 'tangle',
            "courseid = ? AND status = 'approved' AND groupid <> ?",
            [$courseid, $team ? (int) $team->id : 0],
            'timecreated DESC, id DESC'
        );
        $out = [];
        foreach ($records as $tangle) {
            if (in_array((string) $tangle->id, $tried, true)) {
                continue;
            }
            $group = groups_get_group((int) $tangle->groupid, 'id, name');
            $out[] = ['id' => (int) $tangle->id, 'team' => $group ? format_string($group->name) : '',
                'claim' => (string) $tangle->claim, 'options' => array_column(self::options($tangle, (int) $char->id), 'text')];
            if (count($out) >= self::SHOWN) {
                break;
            }
        }
        return $out;
    }

    /**
     * The answers of a tangle, in an order of their own for each student: what is true, the other wrong idea if there is
     * one, and that the Embrollo is right.
     *
     * @param \stdClass $tangle
     * @param int $charid
     * @return array Each: text, right.
     */
    private static function options(\stdClass $tangle, int $charid): array {
        $options = [['text' => (string) $tangle->truth, 'right' => true]];
        if (trim((string) $tangle->trap) !== '') {
            $options[] = ['text' => (string) $tangle->trap, 'right' => false];
        }
        $options[] = ['text' => get_string('tangle_embrollo_right', 'local_oksigeniatesape'), 'right' => false];
        $order = array_map(fn($i) => crc32($tangle->id . '|' . $charid . '|' . $i), array_keys($options));
        array_multisort($order, $options);
        return $options;
    }

    /**
     * A student undoes the tangle of another team: one try, a gem if it is right at once, and what is true with why.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $tangleid
     * @param int $answer Position of the answer chosen.
     * @param int|null $now
     * @return array right, truth, why, gems.
     */
    public static function undo(int $courseid, int $userid, int $tangleid, int $answer, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $team = teams::of($courseid, $userid);
        $tangle = $DB->get_record(self::T . 'tangle', ['id' => $tangleid, 'courseid' => $courseid, 'status' => 'approved']);
        if (!$char || !$tangle || ($team && (int) $team->id === (int) $tangle->groupid)) {
            throw new \invalid_parameter_exception('Not a tangle of another team');
        }
        $options = self::options($tangle, (int) $char->id);
        $right = !empty($options[$answer]['right']);
        $mission = missions::get($courseid);
        $where = "charid = ? AND courseid = ? AND kind IN ('tangle', 'tangled') AND code = ?";
        $gems = 0;
        if (!$DB->record_exists_select(self::T . 'found', $where, [$char->id, $courseid, (string) $tangle->id])) {
            $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'missionid' => $mission ? (int) $mission->id : 0, 'kind' => $right ? 'tangle' : 'tangled',
                'code' => (string) $tangle->id, 'timecreated' => $now]);
            if (
                $right && engine::gems(
                    engine::member($char, $courseid, $now),
                    self::GEMS_UNDONE,
                    'tangle',
                    0,
                    0,
                    (int) $tangle->id,
                    $now
                )
            ) {
                $gems = self::GEMS_UNDONE;
            }
        }
        return ['right' => $right, 'truth' => (string) $tangle->truth, 'why' => (string) $tangle->why, 'gems' => $gems];
    }

    /**
     * The tangles waiting for a teacher, and those approved with how they went, for the panel.
     *
     * @param int $courseid
     * @return array pending (id, team, author, claim, truth, trap, why), approved (the same, and tried, undone, inbattle).
     */
    public static function for_teacher(int $courseid): array {
        global $DB;
        $records = $DB->get_records_select(
            self::T . 'tangle',
            "courseid = ? AND status IN ('pending', 'approved')",
            [$courseid],
            'timecreated DESC, id DESC'
        );
        $out = ['pending' => [], 'approved' => []];
        foreach ($records as $tangle) {
            $group = groups_get_group((int) $tangle->groupid, 'id, name');
            $author = $DB->get_field(self::T . 'char', 'userid', ['id' => $tangle->charid]);
            $user = $author ? \core_user::get_user((int) $author) : null;
            $row = ['id' => (int) $tangle->id, 'team' => $group ? format_string($group->name) : '',
                'author' => $user ? fullname($user) : '', 'claim' => (string) $tangle->claim, 'truth' => (string) $tangle->truth,
                'trap' => (string) $tangle->trap, 'why' => (string) $tangle->why];
            if ($tangle->status === 'pending') {
                $out['pending'][] = $row;
            } else {
                $out['approved'][] = $row + self::tries($courseid, (int) $tangle->id) + ['inbattle' => !empty($tangle->inbattle)];
            }
        }
        return $out;
    }

    /**
     * The tangles waiting for a teacher in a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function pending(int $courseid): int {
        global $DB;
        return $DB->count_records(self::T . 'tangle', ['courseid' => $courseid, 'status' => 'pending']);
    }

    /**
     * A teacher approves a tangle (each member of the team gets its gems) or gives it back to the team.
     *
     * @param int $courseid
     * @param int $tangleid
     * @param bool $ok
     * @param int $actorid
     * @param int|null $now
     */
    public static function review(int $courseid, int $tangleid, bool $ok, int $actorid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $tangle = $DB->get_record(self::T . 'tangle', ['id' => $tangleid, 'courseid' => $courseid, 'status' => 'pending']);
        if (!$tangle) {
            return;
        }
        $DB->update_record(self::T . 'tangle', (object) ['id' => $tangle->id, 'status' => $ok ? 'approved' : 'rejected',
            'reviewedby' => $actorid, 'timemodified' => $now]);
        if ($ok) {
            foreach (teams::members($courseid, (int) $tangle->groupid) as $member) {
                engine::gems($member, self::GEMS_WRITTEN, 'tangle', $actorid, 0, (int) $tangle->id, $now);
            }
        }
    }

    /**
     * The teacher takes an approved tangle to the battle: a question of the place where the class is now, with what is
     * true as the right answer, and its why.
     *
     * @param int $courseid
     * @param int $tangleid
     * @return array The question.
     */
    public static function to_battle(int $courseid, int $tangleid): array {
        global $DB;
        $tangle = $DB->get_record(self::T . 'tangle', ['id' => $tangleid, 'courseid' => $courseid, 'status' => 'approved']);
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        if (!$tangle || !$chapter) {
            throw new \invalid_parameter_exception('Not an approved tangle of a course with a mission');
        }
        $questions = edits::view($courseid)['questions'];
        // The objective the place asks most about.
        $objectives = array_count_values(array_filter(array_map(
            fn($q) => $q['chapter'] === $chapter->code ? $q['objective'] : '',
            $questions
        )));
        arsort($objectives);
        $options = [(string) $tangle->truth];
        if (trim((string) $tangle->trap) !== '') {
            $options[] = (string) $tangle->trap;
        }
        $options[] = get_string('tangle_embrollo_right', 'local_oksigeniatesape');
        $question = ['id' => '', 'chapter' => (string) $chapter->code, 'objective' => (string) (array_key_first($objectives) ?? ''),
            'text' => get_string('tangle_question', 'local_oksigeniatesape', preg_replace(
                '/^[\s«»"]+|[\s«»"]+$/u',
                '',
                (string) $tangle->claim
            )),
            'options' => $options,
            'why' => (string) $tangle->why];
        $saved = edits::save_questions($courseid, array_merge($questions, [$question]));
        $DB->set_field(self::T . 'tangle', 'inbattle', 1, ['id' => $tangle->id]);
        return end($saved);
    }

    /**
     * The tangle the class screen shows in a session: one of the approved, another each time.
     *
     * @param int $courseid
     * @param int $seed
     * @return array|null team, claim, options, truth, why.
     */
    public static function for_board(int $courseid, int $seed): ?array {
        global $DB;
        $records = array_values($DB->get_records(
            self::T . 'tangle',
            ['courseid' => $courseid, 'status' => 'approved'],
            'timecreated DESC, id DESC',
            '*',
            0,
            12
        ));
        if (!$records) {
            return null;
        }
        $tangle = $records[abs($seed) % count($records)];
        $group = groups_get_group((int) $tangle->groupid, 'id, name');
        return ['team' => $group ? format_string($group->name) : '', 'claim' => (string) $tangle->claim,
            'options' => array_column(self::options($tangle, 0), 'text'), 'truth' => (string) $tangle->truth,
            'why' => (string) $tangle->why];
    }

    /**
     * Takes away the tangles of a course, or those some characters wrote.
     *
     * @param int $courseid
     * @param int[]|null $charids
     */
    public static function remove_in_course(int $courseid, ?array $charids = null): void {
        global $DB;
        if ($charids === null) {
            $DB->delete_records(self::T . 'tangle', ['courseid' => $courseid]);
            return;
        }
        if ($charids) {
            [$insql, $params] = $DB->get_in_or_equal($charids);
            $DB->delete_records_select(self::T . 'tangle', "courseid = ? AND charid $insql", array_merge([$courseid], $params));
        }
    }
}
