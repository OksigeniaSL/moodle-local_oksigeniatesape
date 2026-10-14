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
 * The student who leaves a course: what they did there is marked when they leave, stops showing (Moodle does not list
 * them any more), and goes some time after; if they come back before, it is all there. Their character stays: it is
 * theirs, not the course's.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class leaving {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Days that what a student did in a course is kept after they leave it, unless the site says otherwise. */
    public const KEEP_DAYS = 180;

    /**
     * A student left a course, or one of their ways into it: if they are not in it any more, it is noted.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     */
    public static function left(int $courseid, int $userid, ?int $now = null): void {
        global $DB;
        if (is_enrolled(\context_course::instance($courseid), $userid, '', false)) {
            return;
        }
        $member = self::member($courseid, $userid);
        if ($member && !(int) $member->leftat) {
            $DB->set_field(self::T . 'member', 'leftat', $now ?? time(), ['id' => $member->id]);
        }
    }

    /**
     * A student came into a course: if they had left it, what they did there is theirs again.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function back(int $courseid, int $userid): void {
        global $DB;
        $member = self::member($courseid, $userid);
        if ($member && (int) $member->leftat) {
            $DB->set_field(self::T . 'member', 'leftat', 0, ['id' => $member->id]);
        }
    }

    /**
     * Deletes what the students who left a course long ago did there.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function forget(?int $now = null): int {
        global $DB;
        $days = (int) get_config('local_oksigeniatesape', 'keepleft');
        $days = $days > 0 ? $days : self::KEEP_DAYS;
        $where = 'leftat > 0 AND leftat < ?';
        $gone = $DB->get_records_select(self::T . 'member', $where, [($now ?? time()) - $days * DAYSECS], '', 'id, courseid, charid');
        foreach ($gone as $member) {
            \local_oksigeniatesape\privacy\provider::delete_left((int) $member->courseid, (int) $member->charid);
        }
        return count($gone);
    }

    /**
     * The member of a student in a course, if they ever played there.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null
     */
    private static function member(int $courseid, int $userid): ?\stdClass {
        global $DB;
        $member = $DB->get_record_sql(
            'SELECT m.id, m.leftat
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'char} c ON c.id = m.charid
              WHERE c.userid = ? AND m.courseid = ?',
            [$userid, $courseid]
        );
        return $member ?: null;
    }
}
