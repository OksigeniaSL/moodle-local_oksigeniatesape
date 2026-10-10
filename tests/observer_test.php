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
use local_oksigeniatesape\local\regions;

/**
 * Tests for what the game takes from Moodle: completed activities.
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
}
