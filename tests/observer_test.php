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
use local_oksigeniatesape\local\demo;
use local_oksigeniatesape\local\regions;

/**
 * Tests for what the game takes from Moodle: completed activities, deleted activities and courses.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\observer
 */
#[\PHPUnit\Framework\Attributes\CoversClass(observer::class)]
final class observer_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with completion, two activities that students mark as done, and an enrolled student.
     *
     * @return \stdClass
     */
    private function make_course(): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $CFG->enablecompletion = 1;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course(['enablecompletion' => 1]);
        $w->page1 = $dg->create_module('page', ['course' => $w->course->id], ['completion' => COMPLETION_TRACKING_MANUAL]);
        $w->page2 = $dg->create_module('page', ['course' => $w->course->id], ['completion' => COMPLETION_TRACKING_MANUAL]);
        $w->student = $dg->create_and_enrol($w->course, 'student');
        regions::save($w->course->id, ['enabled' => 1]);
        return $w;
    }

    /**
     * Marks an activity as done or not done for a user.
     *
     * @param \stdClass $course
     * @param \stdClass $module
     * @param int $userid
     * @param int $state
     */
    private function complete(\stdClass $course, \stdClass $module, int $userid, int $state = COMPLETION_COMPLETE): void {
        $cm = get_coursemodule_from_id('page', $module->cmid);
        (new \completion_info($course))->update_state($cm, $state, $userid);
    }

    /**
     * Saber of a user's character.
     *
     * @param int $userid
     * @return int
     */
    private function saber(int $userid): int {
        return (int) characters::get($userid)->saber;
    }

    public function test_completion_gives_the_rule_or_the_challenge(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_course();
        characters::create($w->student->id, 'protector', 'm');
        $DB->insert_record(self::T . 'challenge', (object) [
            'cmid' => $w->page2->cmid,
            'courseid' => $w->course->id,
            'kind' => 'task',
            'saber' => 25,
            'gems' => 3,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        // An activity that is not a challenge gives the saber of the rule (10 unless the teacher changes it).
        $this->complete($w->course, $w->page1, $w->student->id);
        $this->assertEquals(10, $this->saber($w->student->id));
        // A challenge gives its own.
        $this->complete($w->course, $w->page2, $w->student->id);
        $char = characters::get($w->student->id);
        $this->assertEquals(35, $char->saber);
        $this->assertEquals(3, $char->gems);
        // Once per activity.
        $this->complete($w->course, $w->page1, $w->student->id, COMPLETION_INCOMPLETE);
        $this->assertEquals(25, $this->saber($w->student->id));
        $this->complete($w->course, $w->page1, $w->student->id);
        $this->assertEquals(35, $this->saber($w->student->id));
        $this->assertEquals(1, $DB->count_records(self::T . 'ledger', ['cmid' => $w->page1->cmid, 'undoneby' => 0,
            'reason' => 'completion']));
    }

    public function test_rule_of_the_course(): void {
        $this->resetAfterTest();
        $w = $this->make_course();
        characters::create($w->student->id, 'sage', 'f');
        regions::save($w->course->id, ['rules' => ['completion' => 0]]);
        $this->complete($w->course, $w->page1, $w->student->id);
        $this->assertEquals(0, $this->saber($w->student->id));
    }

    public function test_nothing_without_character_or_game(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_course();
        $this->complete($w->course, $w->page1, $w->student->id);
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger'));
        characters::create($w->student->id, 'sage', 'f');
        regions::save($w->course->id, ['enabled' => 0]);
        $this->complete($w->course, $w->page2, $w->student->id);
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger'));
    }

    public function test_deleted_activity_stops_being_a_challenge(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_course();
        $DB->insert_record(self::T . 'challenge', (object) [
            'cmid' => $w->page1->cmid,
            'courseid' => $w->course->id,
            'kind' => 'task',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        course_delete_module($w->page1->cmid);
        $this->assertFalse($DB->record_exists(self::T . 'challenge', ['cmid' => $w->page1->cmid]));
    }

    public function test_deleted_user_takes_their_character_with_them(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($w->course, 'editingteacher');
        $char = characters::create($w->student->id, 'sage', 'f');
        $member = \local_oksigeniatesape\local\engine::member($char, $w->course->id);
        \local_oksigeniatesape\local\engine::saber($member, 10, 'manual', $teacher->id);
        delete_user($teacher);
        $this->assertFalse($DB->record_exists(self::T . 'ledger', ['actorid' => $teacher->id]));
        delete_user($w->student);
        $this->assertFalse($DB->record_exists(self::T . 'char', ['id' => $char->id]));
        $this->assertFalse($DB->record_exists(self::T . 'member', ['charid' => $char->id]));
        $this->assertFalse($DB->record_exists(self::T . 'ledger', ['charid' => $char->id]));
    }

    public function test_a_reset_for_next_year_keeps_the_game_set_up(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = demo::create_class('NEXTYEAR', 6, 3, 'storyheroes', 2);
        $courseid = (int) $class->course->id;
        $mission = \local_oksigeniatesape\local\missions::get($courseid);
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id]);
        $setup = [];
        foreach (['region', 'challenge', 'power', 'mission'] as $table) {
            $setup[$table] = $DB->count_records(self::T . $table, ['courseid' => $courseid]);
        }
        // A reset that only clears, for instance, the forum posts leaves the game alone.
        $context = \context_course::instance($courseid);
        $reset = fn(array $options) => \core\event\course_reset_ended::create(['context' => $context,
            'courseid' => $courseid, 'other' => ['reset_options' => $options]])->trigger();
        $reset(['reset_forum_all' => 1]);
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'member', ['courseid' => $courseid]));
        $reset(['unenrol_users' => [$DB->get_field('role', 'id', ['shortname' => 'student'])]]);
        foreach (['member', 'ledger', 'idea', 'team'] as $table) {
            $this->assertEquals(0, $DB->count_records(self::T . $table, ['courseid' => $courseid]), $table);
        }
        foreach ($setup as $table => $count) {
            $this->assertEquals($count, $DB->count_records(self::T . $table, ['courseid' => $courseid]), $table);
        }
        $this->assertEquals(0, $DB->count_records_select(self::T . 'chapter', 'missionid = ? AND litat > 0', [$mission->id]));
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'char'));
    }

    public function test_deleted_course_takes_its_game_with_it(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $gone = demo::create_class('GONE', 6, 3, 'storyheroes', 2);
        $kept = demo::create_class('KEPT', 6, 3, 'storyheroes', 2);
        $now = time();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_oksigeniatesape');
        $item = $gen->create_item();
        foreach ([$gone, $kept] as $class) {
            $courseid = (int) $class->course->id;
            $event = ['courseid' => $courseid, 'day' => $now, 'code' => 'calm', 'timecreated' => $now, 'timemodified' => $now];
            $DB->insert_record(self::T . 'event', (object) $event);
            $live = ['courseid' => $courseid, 'kind' => 'battle', 'timecreated' => $now, 'timemodified' => $now];
            $liveid = $DB->insert_record(self::T . 'live', (object) $live);
            $char = characters::get((int) $class->students[0]->id);
            $in = ['liveid' => $liveid, 'courseid' => $courseid, 'charid' => $char->id, 'round' => 0, 'timecreated' => $now];
            $DB->insert_record(self::T . 'livein', (object) $in);
            $gen->create_inventory(['charid' => $char->id, 'itemid' => $item->id, 'courseid' => $courseid]);
        }
        $chars = $DB->count_records(self::T . 'char');
        $missions = $DB->get_records_menu(self::T . 'mission', null, '', 'courseid, id');
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'powerreq'));
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'chapter', ['missionid' => $missions[$gone->course->id]]));

        delete_course($gone->course, false);

        $tables = ['region', 'member', 'team', 'ledger', 'power', 'inventory', 'mission', 'idea', 'event', 'live', 'livein'];
        foreach ($tables as $table) {
            $this->assertEquals(0, $DB->count_records(self::T . $table, ['courseid' => $gone->course->id]), $table);
            $this->assertGreaterThan(0, $DB->count_records(self::T . $table, ['courseid' => $kept->course->id]), $table);
        }
        foreach (['chapter', 'scene'] as $table) {
            $this->assertEquals(0, $DB->count_records(self::T . $table, ['missionid' => $missions[$gone->course->id]]));
            $this->assertGreaterThan(0, $DB->count_records(self::T . $table, ['missionid' => $missions[$kept->course->id]]));
        }
        $orphans = $DB->count_records_sql(
            'SELECT COUNT(1) FROM {' . self::T . 'powerreq} r LEFT JOIN {' . self::T . 'power} p ON p.id = r.powerid
              WHERE p.id IS NULL'
        );
        $this->assertEquals(0, $orphans);
        $this->assertGreaterThan(0, $DB->count_records(self::T . 'powerreq'));
        // The characters are the students' own: they stay, with what they earned.
        $this->assertEquals($chars, $DB->count_records(self::T . 'char'));
    }
}
