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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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
     * @param array $data name, and any of description, saber, gems, steps, teamwork, itemid, objectives, chapterid;
     *     id to change an existing one.
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
        $challenge->timemodified = $now;
        if (empty($challenge->id)) {
            $challenge->id = $DB->insert_record(self::T . 'challenge', $challenge);
        } else {
            $DB->update_record(self::T . 'challenge', $challenge);
        }
        return self::get((int) $challenge->id);
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
                engine::saber($member, (int) $challenge->saber, 'classroom', $actorid, 0, $validationid, $now);
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
