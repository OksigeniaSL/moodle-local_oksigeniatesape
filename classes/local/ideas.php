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
 * The box of ideas: students tell their teacher an idea for the game or the class. Only the teachers of the course
 * read them; nothing leaves Moodle. A student can send it without their name: the teacher does not see who sent it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ideas {
    /** @var string Table of the ideas. */
    private const TABLE = 'local_oksigeniatesape_idea';

    /** @var int Longest idea, in characters. */
    public const MAXLENGTH = 1000;

    /** @var int Ideas a student can send in a day in a course. */
    public const DAILY = 3;

    /**
     * A student sends an idea to the teachers of a course.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $message
     * @param bool $anonymous The teacher will not see who sent it.
     * @param int|null $now
     * @return \stdClass
     */
    public static function send(int $courseid, int $userid, string $message, bool $anonymous, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $message = trim(clean_param($message, PARAM_TEXT));
        if ($message === '') {
            throw new \invalid_parameter_exception('An idea needs some words');
        }
        $today = $DB->count_records_select(
            self::TABLE,
            'courseid = ? AND charid = ? AND timecreated >= ?',
            [$courseid, $char->id, engine::day_start($now, $courseid)]
        );
        if ($today >= self::DAILY) {
            throw new \moodle_exception('ideastoday', 'local_oksigeniatesape', '', self::DAILY);
        }
        $idea = (object) [
            'courseid' => $courseid,
            'charid' => $char->id,
            'anonymous' => $anonymous ? 1 : 0,
            'message' => \core_text::substr($message, 0, self::MAXLENGTH),
            'status' => 'new',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $idea->id = $DB->insert_record(self::TABLE, $idea);
        return $idea;
    }

    /**
     * The ideas a student has sent in a course, newest first.
     *
     * @param int $courseid
     * @param int $charid
     * @return array
     */
    public static function mine(int $courseid, int $charid): array {
        global $DB;
        $ideas = $DB->get_records(
            self::TABLE,
            ['courseid' => $courseid, 'charid' => $charid],
            'timecreated DESC, id DESC',
            '*',
            0,
            10
        );
        return array_values(array_map(fn($i) => [
            'id' => (int) $i->id,
            'message' => (string) $i->message,
            'anonymous' => (bool) $i->anonymous,
            'status' => $i->status,
            'time' => (int) $i->timecreated,
        ], $ideas));
    }

    /**
     * The ideas of a course for its teachers, newest first, with who sent them unless they chose not to say.
     *
     * @param int $courseid
     * @return array
     */
    public static function list(int $courseid): array {
        global $DB;
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $ideas = $DB->get_records_sql(
            'SELECT i.*, c.userid, ' . $names . '
               FROM {' . self::TABLE . '} i
               JOIN {local_oksigeniatesape_char} c ON c.id = i.charid
               JOIN {user} u ON u.id = c.userid
              WHERE i.courseid = ?
           ORDER BY i.timecreated DESC, i.id DESC',
            [$courseid],
            0,
            100
        );
        return array_values(array_map(fn($i) => [
            'id' => (int) $i->id,
            'message' => (string) $i->message,
            'fullname' => $i->anonymous ? '' : fullname($i),
            'userid' => $i->anonymous ? 0 : (int) $i->userid,
            'status' => $i->status,
            'time' => (int) $i->timecreated,
        ], $ideas));
    }

    /**
     * How many ideas of a course nobody has read yet.
     *
     * @param int $courseid
     * @return int
     */
    public static function unread(int $courseid): int {
        global $DB;
        return $DB->count_records(self::TABLE, ['courseid' => $courseid, 'status' => 'new']);
    }

    /**
     * A teacher marks an idea as read, or deletes it.
     *
     * @param int $courseid
     * @param int $ideaid
     * @param string $action read or delete.
     */
    public static function handle(int $courseid, int $ideaid, string $action): void {
        global $DB;
        $idea = $DB->get_record(self::TABLE, ['id' => $ideaid, 'courseid' => $courseid], '*', MUST_EXIST);
        if ($action === 'delete') {
            $DB->delete_records(self::TABLE, ['id' => $idea->id]);
        } else if ($action === 'read') {
            $DB->update_record(self::TABLE, (object) ['id' => $idea->id, 'status' => 'read', 'timemodified' => time()]);
        } else {
            throw new \invalid_parameter_exception('Unknown action: ' . $action);
        }
    }
}
