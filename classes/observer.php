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

namespace local_oksigeniatesape;

use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;

/**
 * Turns what happens in Moodle into progress in the game.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Tables of what was played in a course, by courseid. */
    private const PLAYED_TABLES = ['member', 'team', 'ledger', 'validation', 'inventory', 'sceneattempt', 'found', 'minigame',
        'idea', 'event', 'live', 'livein', 'livetoken', 'nextclass', 'pact'];

    /** @var string[] Tables of how the game of a course is set up, by courseid. */
    private const SETUP_TABLES = ['region', 'challenge', 'power', 'mission'];

    /**
     * An activity completed (or no longer completed): saber from its challenge, or from the rule of the course for
     * activities that are not challenges. Once per activity; if the completion is taken back, so is the saber.
     *
     * Only students who have made their character and play in the course get it.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function completion_updated(\core\event\course_module_completion_updated $event): void {
        global $DB;
        $courseid = (int) $event->courseid;
        $userid = (int) $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;
        $region = regions::get($courseid);
        $char = characters::get($userid);
        if (!$region || !$region->enabled || !$char) {
            return;
        }
        if (!has_capability('local/oksigeniatesape:play', \context_course::instance($courseid), $userid)) {
            return;
        }
        $completion = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        $completed = in_array((int) $completion->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
        $given = $DB->get_fieldset_select(
            self::T . 'ledger',
            'id',
            "charid = ? AND courseid = ? AND cmid = ? AND reason = 'completion' AND undoneby = 0",
            [$char->id, $courseid, $cmid]
        );
        if (!$completed) {
            foreach ($given as $entryid) {
                engine::undo((int) $entryid, 0);
            }
            return;
        }
        if ($given) {
            return;
        }
        $member = engine::member($char, $courseid);
        $challenge = $DB->get_record(self::T . 'challenge', ['cmid' => $cmid, 'courseid' => $courseid]);
        $saber = $challenge ? (int) $challenge->saber : regions::rule($region, 'completion');
        if ($saber > 0) {
            // The activities of the chapters of a mission are measured with it: they go under no weekly cap.
            engine::saber($member, $saber, 'completion', 0, $cmid, 0, null, $challenge && $challenge->chapterid > 0);
        }
        if ($challenge && $challenge->gems > 0) {
            engine::gems($member, (int) $challenge->gems, 'completion', 0, $cmid);
        }
        if ($challenge && $challenge->itemid > 0) {
            engine::item($member, (int) $challenge->itemid, 'challenge', 0, $cmid);
        }
    }

    /**
     * An activity deleted: it stops being a challenge.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;
        $DB->delete_records(self::T . 'challenge', ['cmid' => $event->contextinstanceid]);
    }

    /**
     * A user account deleted: their character and everything of theirs go too (students are minors).
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        \local_oksigeniatesape\privacy\provider::delete_deleted_user((int) $event->objectid);
    }

    /**
     * A course deleted: the region goes with it, and everything played there. The characters stay, with what they
     * earned: they belong to the students, not to the course.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $courseid = (int) $event->objectid;
        self::clear_played($courseid);
        $missions = $DB->get_fieldset_select(self::T . 'mission', 'id', 'courseid = ?', [$courseid]);
        if ($missions) {
            [$insql, $params] = $DB->get_in_or_equal($missions);
            $DB->delete_records_select(self::T . 'scene', "missionid $insql", $params);
            $DB->delete_records_select(self::T . 'chapter', "missionid $insql", $params);
        }
        foreach (self::SETUP_TABLES as $table) {
            $DB->delete_records(self::T . $table, ['courseid' => $courseid]);
        }
    }

    /**
     * A course reset that takes the students out (the course ready for next year): what was played goes, and the game
     * stays set up as it was (settings, mission, challenges, powers), with the lanterns of the class off again. The
     * characters stay with their students.
     *
     * @param \core\event\course_reset_ended $event
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event): void {
        global $DB;
        $options = (array) ($event->other['reset_options'] ?? []);
        if (empty($options['unenrol_users'])) {
            return;
        }
        $courseid = (int) $event->courseid;
        self::clear_played($courseid);
        $missions = $DB->get_fieldset_select(self::T . 'mission', 'id', 'courseid = ?', [$courseid]);
        if ($missions) {
            [$insql, $params] = $DB->get_in_or_equal($missions);
            $DB->set_field_select(self::T . 'chapter', 'litat', 0, "missionid $insql", $params);
        }
    }

    /**
     * Deletes what was played in a course: progress, requests of powers, events, battles, plans.
     *
     * @param int $courseid
     */
    private static function clear_played(int $courseid): void {
        global $DB;
        $powers = $DB->get_fieldset_select(self::T . 'power', 'id', 'courseid = ?', [$courseid]);
        if ($powers) {
            [$insql, $params] = $DB->get_in_or_equal($powers);
            $DB->delete_records_select(self::T . 'powerreq', "powerid $insql", $params);
        }
        foreach (self::PLAYED_TABLES as $table) {
            $DB->delete_records(self::T . $table, ['courseid' => $courseid]);
        }
    }
}
