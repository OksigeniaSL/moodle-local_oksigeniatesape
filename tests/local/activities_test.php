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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/oksigeniatesape/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for Moodle activities in the game: those a mission makes, any activity as a challenge, and what students and
 * teachers see.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\activities
 */
#[\PHPUnit\Framework\Attributes\CoversClass(activities::class)]
final class activities_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on, a student with a character and a teacher.
     *
     * @return \stdClass
     */
    private function make_class(): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course(['enablecompletion' => 1]);
        regions::save($w->course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $w->teacher = $dg->create_and_enrol($w->course, 'editingteacher');
        $w->student = $dg->create_and_enrol($w->course, 'student');
        characters::create($w->student->id, 'sage', 'f');
        $w->member = engine::member(characters::get($w->student->id), $w->course->id);
        return $w;
    }

    /**
     * Marks an activity as completed by a student, as Moodle does (with its event).
     *
     * @param \stdClass $course
     * @param int $cmid
     * @param int $userid
     */
    private function complete(\stdClass $course, int $cmid, int $userid): void {
        $cm = get_fast_modinfo($course)->get_cm($cmid);
        $completion = new \completion_info(get_course($course->id));
        $data = $completion->get_data($cm, false, $userid);
        $data->completionstate = COMPLETION_COMPLETE;
        $data->timemodified = time();
        $completion->internal_set_data($cm, $data);
    }

    public function test_a_mission_makes_its_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class();
        $DB->set_field('course', 'enablecompletion', 0, ['id' => $w->course->id]);
        $sections = $DB->count_records('course_sections', ['course' => $w->course->id]);
        $mission = missions::install($w->course->id, 'livingthings', 'valley', 'es', null, true);
        $this->assertSame(1, (int) $DB->get_field('course', 'enablecompletion', ['id' => $w->course->id]));
        $this->assertSame($sections + 4, $DB->count_records('course_sections', ['course' => $w->course->id]));
        $this->assertSame(2, $DB->count_records('forum', ['course' => $w->course->id]));
        $this->assertSame(2, $DB->count_records('assign', ['course' => $w->course->id]));
        $data = $DB->get_record('data', ['course' => $w->course->id], '*', MUST_EXIST);
        $this->assertSame(6, $DB->count_records('data_fields', ['dataid' => $data->id]));
        $this->assertSame(1, (int) $DB->get_field('assign', 'teamsubmission', ['course' => $w->course->id,
            'name' => 'El registro del experimento']));
        $challenges = $DB->get_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$w->course->id]);
        $this->assertCount(5, $challenges);
        $claro = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => 'claro']);
        $this->assertNotEmpty($claro->sectionid);
        $this->assertSame('Claro del Despertar', get_section_name(
            $w->course->id,
            $DB->get_field('course_sections', 'section', ['id' => $claro->sectionid])
        ));
        $this->assertEmpty($DB->get_field(self::T . 'chapter', 'sectionid', ['missionid' => $mission->id,
            'code' => 'biblioteca']));
        $this->assertFalse(missions::activities_missing(missions::get($w->course->id)));

        // Installing again does not make them twice.
        missions::install($w->course->id, 'livingthings', 'azara', 'py', null, true);
        $this->assertSame(2, $DB->count_records('forum', ['course' => $w->course->id]));

        // The student sees them in the places of their chapters, and completing one counts.
        $list = activities::for_student($w->course->id, $w->student->id, $w->member);
        $this->assertCount(5, $list);
        $forum = array_values(array_filter($list, fn($a) => $a['name'] === '¿Está vivo?'))[0];
        $this->assertSame((int) $claro->id, $forum['chapterid']);
        $this->assertSame(15, $forum['saber']);
        $this->assertTrue($forum['tracked']);
        $this->assertFalse($forum['done']);
        $this->complete($w->course, $forum['cmid'], $w->student->id);
        $this->assertSame(15, (int) characters::get($w->student->id)->saber);
        $list = activities::for_student($w->course->id, $w->student->id, $w->member);
        $this->assertTrue(array_values(array_filter($list, fn($a) => $a['cmid'] === $forum['cmid']))[0]['done']);
        $forteacher = array_column(activities::for_teacher($w->course->id, [$w->student->id]), null, 'cmid');
        $this->assertSame(1, $forteacher[$forum['cmid']]['done']);
    }

    public function test_any_activity_can_be_a_challenge(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $w->course->id,
            'completion' => COMPLETION_TRACKING_MANUAL]);
        $this->assertSame([], activities::for_student($w->course->id, $w->student->id, $w->member));
        activities::save($w->course->id, $page->cmid, ['saber' => 500, 'gems' => 3, 'steps' => [3, 4]]);
        $this->assertSame(activities::MAX_SABER, (int) activities::challenge($page->cmid, (int) $w->course->id)->saber);
        $this->assertSame([], activities::for_student($w->course->id, $w->student->id, $w->member));
        activities::save($w->course->id, $page->cmid, ['steps' => [2]]);
        $list = activities::for_student($w->course->id, $w->student->id, $w->member);
        $this->assertCount(1, $list);
        $this->assertTrue($list[0]['challenge']);
        $this->assertSame(0, $list[0]['chapterid']);
        $this->assertSame(3, $list[0]['gems']);
        activities::remove($page->cmid);
        $this->assertNull(activities::challenge($page->cmid, (int) $w->course->id));
    }

    public function test_settings_form_of_an_activity(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $w->course->id]);
        $data = (object) ['coursemodule' => $page->cmid, 'tesape_challenge' => 1, 'tesape_saber' => 30,
            'tesape_gems' => 2, 'tesape_step1' => 0, 'tesape_step2' => 1, 'tesape_step3' => 1, 'tesape_step4' => 0,
            'tesape_teamwork' => 1];
        // A student cannot set it.
        $this->setUser($w->student);
        local_oksigeniatesape_coursemodule_edit_post_actions($data, $w->course);
        $this->assertNull(activities::challenge($page->cmid, (int) $w->course->id));

        $this->setUser($w->teacher);
        local_oksigeniatesape_coursemodule_edit_post_actions($data, $w->course);
        $challenge = activities::challenge($page->cmid, (int) $w->course->id);
        $this->assertSame(30, (int) $challenge->saber);
        $this->assertSame('2,3', $challenge->steps);
        $this->assertSame(1, (int) $challenge->teamwork);
        $this->assertSame('activity', $challenge->kind);

        // Forms without the game leave it as it is; unticked, it stops being a challenge.
        local_oksigeniatesape_coursemodule_edit_post_actions((object) ['coursemodule' => $page->cmid], $w->course);
        $this->assertNotNull(activities::challenge($page->cmid, (int) $w->course->id));
        $data->tesape_challenge = 0;
        local_oksigeniatesape_coursemodule_edit_post_actions($data, $w->course);
        $this->assertNull(activities::challenge($page->cmid, (int) $w->course->id));

        $errors = local_oksigeniatesape_coursemodule_validation(null, ['tesape_challenge' => 1, 'tesape_saber' => 999,
            'tesape_gems' => -1]);
        $this->assertArrayHasKey('tesape_saber', $errors);
        $this->assertArrayHasKey('tesape_gems', $errors);
    }
}
