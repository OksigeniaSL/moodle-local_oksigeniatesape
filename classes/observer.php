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

use local_oksigeniatesape\local\badges;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;

/**
 * Turns what happens in Moodle into progress in the game.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class observer {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Tables of what was played in a course, by courseid. */
    private const PLAYED_TABLES = ['member', 'team', 'ledger', 'validation', 'inventory', 'sceneattempt', 'found', 'minigame',
        'idea', 'event', 'live', 'livein', 'livetoken', 'nextclass', 'pact', 'daily', 'stain', 'session', 'teamstate',
        'classplay', 'tangle', 'fight'];

    /** @var string[] Tables of how the game of a course is set up, by courseid. */
    private const SETUP_TABLES = ['region', 'challenge', 'power', 'mission', 'objcomp', 'edit', 'own'];

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
        $userid = (int) $event->relateduserid;
        // The game of the student in the course: the course, or the game more of their group.
        $courseid = \local_oksigeniatesape\local\games::key((int) $event->courseid, $userid);
        $cmid = (int) $event->contextinstanceid;
        $region = regions::get($courseid);
        $char = characters::get($userid);
        if (!$region || !$region->enabled || !$char) {
            return;
        }
        if (!has_capability('local/oksigeniatesape:play', \context_course::instance((int) $event->courseid), $userid)) {
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
     * A badge turned on: if it is the badge of the mission of its course, it goes at once to whoever has already lit the
     * lanterns of every place (the teacher may turn it on halfway through the mission, or at the end).
     *
     * @param \core\event\badge_enabled $event
     */
    public static function badge_enabled(\core\event\badge_enabled $event): void {
        // In the game of the course and in each game more.
        foreach ($event->courseid ? \local_oksigeniatesape\local\games::keys((int) $event->courseid) : [] as $key) {
            badges::check_course($key, (int) $event->objectid);
        }
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
     * A student leaves a course (or one of their ways into it): what they did there is marked, and goes some time after.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        foreach (\local_oksigeniatesape\local\games::keys((int) $event->courseid) as $key) {
            \local_oksigeniatesape\local\leaving::left($key, (int) $event->relateduserid);
        }
    }

    /**
     * A student comes into a course: if they had left it, what they did there is theirs again.
     *
     * @param \core\event\user_enrolment_created $event
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        foreach (\local_oksigeniatesape\local\games::keys((int) $event->courseid) as $key) {
            \local_oksigeniatesape\local\leaving::back($key, (int) $event->relateduserid);
        }
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
        // Its game and the games more it had.
        foreach (\local_oksigeniatesape\local\games::keys($courseid) as $key) {
            self::clear_game($key);
        }
        $DB->delete_records(self::T . 'game', ['courseid' => $courseid]);
        \local_oksigeniatesape\local\games::forget();
    }

    /**
     * Takes away all a game had: what was played in it and how it was set up.
     *
     * @param int $key The course, or a game more.
     */
    public static function clear_game(int $key): void {
        global $DB;
        self::clear_played($key);
        // Out of its campaign: the campaign goes when no course is left in it.
        if (regions::get($key)) {
            \local_oksigeniatesape\local\campaigns::leave($key);
        }
        $missions = $DB->get_fieldset_select(self::T . 'mission', 'id', 'courseid = ?', [$key]);
        if ($missions) {
            [$insql, $params] = $DB->get_in_or_equal($missions);
            $DB->delete_records_select(self::T . 'scene', "missionid $insql", $params);
            $DB->delete_records_select(self::T . 'chapter', "missionid $insql", $params);
        }
        foreach (self::SETUP_TABLES as $table) {
            $DB->delete_records(self::T . $table, ['courseid' => $key]);
        }
        regions::forget();
        missions::forget();
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
        // Its game and the games more it has.
        foreach (\local_oksigeniatesape\local\games::keys((int) $event->courseid) as $key) {
            self::clear_played($key);
            $missions = $DB->get_fieldset_select(self::T . 'mission', 'id', 'courseid = ?', [$key]);
            if ($missions) {
                [$insql, $params] = $DB->get_in_or_equal($missions);
                $DB->set_field_select(self::T . 'chapter', 'litat', 0, "missionid $insql", $params);
                $DB->set_field_select(self::T . 'chapter', 'skippedat', 0, "missionid $insql", $params);
            }
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
        // The photos of the classroom go with their pictures.
        \local_oksigeniatesape\local\photos::remove_in_course($courseid);
        foreach (self::PLAYED_TABLES as $table) {
            $DB->delete_records(self::T . $table, ['courseid' => $courseid]);
        }
        // The banner of a team goes with the teams; what each one bought for their character is theirs in every course.
        $DB->delete_records_select(self::T . 'purchase', 'courseid = ? AND groupid > 0', [$courseid]);
    }
}
