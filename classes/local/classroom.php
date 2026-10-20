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
 * Classroom challenges: what happens off the screen (a drawing, a model, a talk, a reading) and counts in the game
 * when the teacher says so, with no Moodle activity behind it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classroom {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] What the teacher can say: done, done with a mention, or not yet. */
    public const STATUSES = ['done', 'merit', 'notyet'];

    /** @var int Extra gems for a mention (a mention never gives more saber). */
    public const MERIT_GEMS = 5;

    /**
     * Makes or changes a classroom challenge.
     *
     * @param int $courseid
     * @param array $data name, and any of description, saber, gems, steps, teamwork, itemid, objectives, chapterid,
     *     parts (role and text of each, up to four); id to change an existing one.
     * @param int|null $now
     * @return \stdClass
     */
    public static function save(int $courseid, array $data, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!empty($data['id'])) {
            $challenge = self::get((int) $data['id']);
            if ($challenge->courseid != $courseid) {
                throw new \invalid_parameter_exception('The challenge belongs to another course');
            }
        } else {
            $challenge = (object) ['courseid' => $courseid, 'cmid' => 0, 'kind' => 'classroom', 'timecreated' => $now];
        }
        $name = trim((string) ($data['name'] ?? $challenge->name ?? ''));
        if ($name === '') {
            throw new \invalid_parameter_exception('A classroom challenge needs a name');
        }
        $challenge->name = \core_text::substr($name, 0, 255);
        if (array_key_exists('description', $data)) {
            $challenge->description = $data['description'];
        }
        foreach (['saber', 'gems', 'itemid', 'chapterid'] as $number) {
            if (array_key_exists($number, $data)) {
                $challenge->$number = max(0, (int) $data[$number]);
            }
        }
        if (array_key_exists('teamwork', $data)) {
            $challenge->teamwork = $data['teamwork'] ? 1 : 0;
        }
        if (array_key_exists('steps', $data)) {
            $steps = array_map('intval', explode(',', (string) $data['steps']));
            $steps = array_unique(array_filter($steps, fn($s) => $s >= 1 && $s <= 4));
            sort($steps);
            $challenge->steps = $steps ? implode(',', $steps) : '1,2,3,4';
        }
        if (array_key_exists('objectives', $data)) {
            $challenge->objectives = \core_text::substr((string) $data['objectives'], 0, 255);
        }
        if (array_key_exists('parts', $data)) {
            $parts = self::clean_parts((array) $data['parts']);
            $challenge->parts = $parts ? json_encode($parts) : null;
            // A task with parts is done by the team.
            $challenge->teamwork = $parts ? 1 : ($challenge->teamwork ?? 0);
        }
        $challenge->timemodified = $now;
        if (empty($challenge->id)) {
            $challenge->id = $DB->insert_record(self::T . 'challenge', $challenge);
        } else {
            $DB->update_record(self::T . 'challenge', $challenge);
        }
        return self::get((int) $challenge->id);
    }

    /**
     * The parts of a team task, as they can be kept: up to four, each with a role or none and a text.
     *
     * @param array $parts
     * @return array Of role and text.
     */
    public static function clean_parts(array $parts): array {
        $clean = [];
        foreach ($parts as $part) {
            $text = trim(clean_param((string) ($part['text'] ?? ''), PARAM_TEXT));
            $role = (string) ($part['role'] ?? '');
            if ($text !== '' && count($clean) < 4) {
                $clean[] = ['role' => in_array($role, characters::ROLES, true) ? $role : '',
                    'text' => \core_text::substr($text, 0, 200)];
            }
        }
        return $clean;
    }

    /**
     * The parts of a team task.
     *
     * @param \stdClass $challenge
     * @return array Of role and text.
     */
    public static function parts(\stdClass $challenge): array {
        return json_decode((string) ($challenge->parts ?? ''), true) ?: [];
    }

    /**
     * A member of a team marks a part of a team task as done, or takes it back: theirs, or the one of a role missing
     * that day, which anyone can cover. Once done, only who marked it takes it back.
     *
     * @param int $challengeid
     * @param int $userid
     * @param int $part From 0.
     * @param bool $done
     * @param int|null $now
     */
    public static function mark_part(int $challengeid, int $userid, int $part, bool $done, ?int $now = null): void {
        global $DB;
        $challenge = self::get($challengeid);
        $char = characters::get($userid);
        $team = $char ? teams::of((int) $challenge->courseid, $userid) : null;
        if (!$char || !$team || !isset(self::parts($challenge)[$part])) {
            throw new \moodle_exception('partnotyours', 'local_oksigeniatesape');
        }
        $key = ['challengeid' => $challenge->id, 'groupid' => $team->id, 'part' => $part];
        $marked = $DB->get_record(self::T . 'challengepart', $key);
        if ($done && !$marked) {
            $DB->insert_record(self::T . 'challengepart', (object) ($key + ['courseid' => $challenge->courseid,
                'charid' => $char->id, 'timecreated' => $now ?? time()]));
        } else if (!$done && $marked) {
            if ((int) $marked->charid !== (int) $char->id) {
                throw new \moodle_exception('partnotyours', 'local_oksigeniatesape');
            }
            $DB->delete_records(self::T . 'challengepart', ['id' => $marked->id]);
        }
    }

    /**
     * The parts of a team task as a team sees them: each with its role, its text, whether it is done and by whom.
     *
     * @param \stdClass $challenge
     * @param int $groupid
     * @param int $userid Who looks.
     * @return array Of role, text, done, by (the name) and mine (they marked it).
     */
    public static function team_parts(\stdClass $challenge, int $groupid, int $userid): array {
        global $DB;
        $marked = $DB->get_records(
            self::T . 'challengepart',
            ['challengeid' => $challenge->id, 'groupid' => $groupid],
            '',
            'part, charid'
        );
        $char = characters::get($userid);
        $region = regions::get((int) $challenge->courseid);
        $list = [];
        foreach (self::parts($challenge) as $i => $part) {
            $by = isset($marked[$i]) ? (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $marked[$i]->charid]) : 0;
            $user = $by ? \core_user::get_user($by) : null;
            $list[] = $part + ['done' => isset($marked[$i]),
                'by' => $user && $region ? view::display_name($user, characters::get($by), $region) : '',
                'mine' => $char && isset($marked[$i]) && (int) $marked[$i]->charid === (int) $char->id];
        }
        return $list;
    }

    /**
     * Which parts of a team task each team has done, for the teacher.
     *
     * @param \stdClass $challenge
     * @return array groupid => indexes of the parts done.
     */
    public static function parts_done(\stdClass $challenge): array {
        global $DB;
        $done = [];
        foreach ($DB->get_records(self::T . 'challengepart', ['challengeid' => $challenge->id], 'part') as $row) {
            $done[(int) $row->groupid][] = (int) $row->part;
        }
        return $done;
    }

    /**
     * A classroom challenge.
     *
     * @param int $challengeid
     * @return \stdClass
     */
    public static function get(int $challengeid): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'challenge', ['id' => $challengeid, 'cmid' => 0], '*', MUST_EXIST);
    }

    /**
     * The classroom challenges of a course.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    public static function list(int $courseid): array {
        global $DB;
        return $DB->get_records(self::T . 'challenge', ['courseid' => $courseid, 'cmid' => 0], 'timecreated, id');
    }

    /**
     * Deletes a classroom challenge and the teacher's word on it. What it gave stays in the log.
     *
     * @param int $challengeid
     */
    public static function delete(int $challengeid): void {
        global $DB;
        $challenge = self::get($challengeid);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records(self::T . 'validation', ['challengeid' => $challenge->id]);
        $DB->delete_records(self::T . 'challengepart', ['challengeid' => $challenge->id]);
        $DB->delete_records(self::T . 'challenge', ['id' => $challenge->id]);
        $transaction->allow_commit();
    }

    /**
     * The teacher's word on a challenge for some students: what it gives when done, and back when it is not.
     *
     * Students who have not made their character yet are left out (they can make it at any time).
     *
     * @param int $challengeid
     * @param int[] $userids
     * @param string $status One of STATUSES.
     * @param string|null $note What the teacher tells them; null keeps the note they had.
     * @param int $actorid The teacher.
     * @param int|null $now
     * @return array For each user id: the status kept, or nocharacter.
     */
    public static function validate(
        int $challengeid,
        array $userids,
        string $status,
        ?string $note,
        int $actorid,
        ?int $now = null
    ): array {
        global $DB;
        if (!in_array($status, self::STATUSES, true)) {
            throw new \invalid_parameter_exception('Unknown status: ' . $status);
        }
        $now = $now ?? time();
        $challenge = self::get($challengeid);
        $results = [];
        foreach ($userids as $userid) {
            $char = characters::get((int) $userid);
            if (!$char) {
                $results[$userid] = 'nocharacter';
                continue;
            }
            $transaction = $DB->start_delegated_transaction();
            $member = engine::member($char, (int) $challenge->courseid, $now);
            $validation = $DB->get_record(self::T . 'validation', ['challengeid' => $challenge->id, 'charid' => $char->id]);
            $before = $validation ? $validation->status : 'notyet';
            if (!$validation) {
                $validation = (object) [
                    'challengeid' => $challenge->id,
                    'charid' => $char->id,
                    'courseid' => $challenge->courseid,
                    'status' => $status,
                    'timecreated' => $now,
                ];
            }
            $validation->status = $status;
            if ($note !== null) {
                $validation->note = trim($note) === '' ? null : $note;
            }
            $validation->validatedby = $actorid;
            $validation->timemodified = $now;
            if (empty($validation->id)) {
                $validation->id = $DB->insert_record(self::T . 'validation', $validation);
            } else {
                $DB->update_record(self::T . 'validation', $validation);
            }
            self::reward($challenge, $member, (int) $validation->id, $before, $status, $actorid, $now);
            // The task of a place done fills the reserve of the team, once.
            $team = (int) $challenge->chapterid && !(int) $challenge->cmid && $status !== 'notyet' ?
                teams::of((int) $challenge->courseid, (int) $userid) : null;
            if ($team) {
                reserve::credit((int) $challenge->courseid, (int) $team->id, 'task-' . $challenge->id, reserve::TASK, $now);
            }
            $transaction->allow_commit();
            $results[$userid] = $status;
        }
        return $results;
    }

    /**
     * The teacher's word on a challenge, for each character.
     *
     * @param int $challengeid
     * @return \stdClass[] Keyed by character id.
     */
    public static function validations(int $challengeid): array {
        global $DB;
        $records = $DB->get_records(self::T . 'validation', ['challengeid' => $challengeid]);
        $bychar = [];
        foreach ($records as $record) {
            $bychar[$record->charid] = $record;
        }
        return $bychar;
    }

    /**
     * Gives or takes back what a challenge gives, from one word of the teacher to the next.
     *
     * @param \stdClass $challenge
     * @param \stdClass $member
     * @param int $validationid
     * @param string $before
     * @param string $after
     * @param int $actorid
     * @param int $now
     */
    private static function reward(
        \stdClass $challenge,
        \stdClass $member,
        int $validationid,
        string $before,
        string $after,
        int $actorid,
        int $now
    ): void {
        $wasdone = $before !== 'notyet';
        $isdone = $after !== 'notyet';
        if ($isdone && !$wasdone) {
            if ($challenge->saber > 0) {
                $mission = $challenge->chapterid > 0;
                engine::saber($member, (int) $challenge->saber, 'classroom', $actorid, 0, $validationid, $now, $mission);
            }
            if ($challenge->gems > 0) {
                engine::gems($member, (int) $challenge->gems, 'classroom', $actorid, 0, $validationid, $now);
            }
            if ($challenge->itemid > 0) {
                engine::item($member, (int) $challenge->itemid, 'challenge', $actorid, 0, $now);
            }
        }
        if ($wasdone && !$isdone) {
            self::undo_entries($member, $validationid, ['classroom', 'merit'], $actorid, $now);
        }
        if ($after === 'merit' && $before !== 'merit') {
            engine::gems($member, self::MERIT_GEMS, 'merit', $actorid, 0, $validationid, $now);
        }
        if ($before === 'merit' && $after === 'done') {
            self::undo_entries($member, $validationid, ['merit'], $actorid, $now);
        }
    }

    /**
     * Undoes what a validation gave (items found stay with the student).
     *
     * @param \stdClass $member
     * @param int $validationid
     * @param string[] $reasons
     * @param int $actorid
     * @param int $now
     */
    private static function undo_entries(\stdClass $member, int $validationid, array $reasons, int $actorid, int $now): void {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($reasons);
        $entries = $DB->get_fieldset_select(
            self::T . 'ledger',
            'id',
            "charid = ? AND courseid = ? AND refid = ? AND undoneby = 0 AND resource <> 'item' AND reason $insql",
            array_merge([$member->charid, $member->courseid, $validationid], $params)
        );
        foreach ($entries as $entryid) {
            engine::undo((int) $entryid, $actorid, $now);
        }
    }
}
