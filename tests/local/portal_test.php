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
 * Tests for the ways into the game from Moodle: the cover of the course page, going straight in, the activities kept
 * inside the valley and «my world».
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\portal
 */
#[\PHPUnit\Framework\Attributes\CoversClass(portal::class)]
final class portal_test extends \advanced_testcase {
    public function test_the_cover_changes_with_who_looks_and_the_mode(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        // The game off: the teacher sees how to turn it on; the student, nothing.
        $this->setUser($teacher);
        $this->assertStringContainsString(get_string('portal_turnon', 'local_oksigeniatesape'), portal::cover($course));
        $this->setUser($student);
        $this->assertSame('', portal::cover($course));
        // On, with the mission: the big door by default.
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $door = portal::cover($course);
        $this->assertStringContainsString('tesape-door', $door);
        $this->assertStringContainsString(get_string('portal_play', 'local_oksigeniatesape'), $door);
        $this->assertStringContainsString('Claro del Despertar', $door);
        $this->assertStringContainsString(get_string('portal_newcharacter', 'local_oksigeniatesape'), $door);
        regions::save($course->id, ['portal' => 'card']);
        $this->assertStringContainsString('tesape-card', portal::cover($course));
        // Only in the menu: no cover for anybody.
        regions::save($course->id, ['portal' => 'menu']);
        $this->assertSame('', portal::cover($course));
        $this->setUser($teacher);
        $this->assertSame('', portal::cover($course));
        // Straight into the valley: students go in, the teacher sees the cover and is told so.
        regions::save($course->id, ['portal' => 'direct']);
        $this->assertFalse(portal::goes_straight_in($course->id));
        $cover = portal::cover($course);
        $this->assertStringContainsString(get_string('portal_teach', 'local_oksigeniatesape'), $cover);
        $this->assertStringContainsString(get_string('portal_direct_note', 'local_oksigeniatesape'), $cover);
        $this->setUser($student);
        $this->assertTrue(portal::goes_straight_in($course->id));
        $this->assertSame('', portal::cover($course));
        // Not a cover of this game.
        $this->expectException(\invalid_parameter_exception::class);
        regions::save($course->id, ['portal' => 'banner']);
    }

    public function test_the_activities_of_the_mission_can_stay_inside_the_valley(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es', null, true);
        $student = $gen->create_and_enrol($course, 'student');
        // Without the site allowing it, it cannot be done.
        set_config('allowstealth', 0);
        try {
            regions::save($course->id, ['hideacts' => 1]);
            $this->fail('Kept inside without stealth');
        } catch (\moodle_exception $e) {
            $this->assertSame('nostealth', $e->errorcode);
        }
        set_config('allowstealth', 1);
        regions::save($course->id, ['hideacts' => 1]);
        $where = 'missionid = ? AND sectionid > 0';
        $sectionids = $DB->get_fieldset_select('local_oksigeniatesape_chapter', 'sectionid', $where, [$mission->id]);
        $this->assertNotEmpty($sectionids);
        $modinfo = get_fast_modinfo($course->id, $student->id);
        $cms = array_filter($modinfo->get_cms(), fn($cm) => in_array($cm->section, $sectionids));
        $this->assertNotEmpty($cms);
        foreach ($cms as $cm) {
            // Available to the student, not on the course page.
            $this->assertTrue($cm->uservisible, $cm->name);
            $this->assertFalse($cm->is_visible_on_course_page(), $cm->name);
        }
        foreach ($sectionids as $sectionid) {
            $this->assertEquals(0, $DB->get_field('course_sections', 'visible', ['id' => $sectionid]));
        }
        // And back on the course page.
        regions::save($course->id, ['hideacts' => 0]);
        $modinfo = get_fast_modinfo($course->id, $student->id);
        foreach (array_filter($modinfo->get_cms(), fn($cm) => in_array($cm->section, $sectionids)) as $cm) {
            $this->assertTrue($cm->is_visible_on_course_page(), $cm->name);
        }
    }

    public function test_my_world_lists_the_courses_with_the_game(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $student = $gen->create_user();
        $with = $gen->create_course(['fullname' => 'Ciencias 5ºB']);
        $without = $gen->create_course();
        $gen->enrol_user($student->id, $with->id, 'student');
        $gen->enrol_user($student->id, $without->id, 'student');
        $this->setUser($student);
        $this->assertFalse(portal::has_world($student->id));
        regions::save($with->id, ['enabled' => 1]);
        missions::install($with->id, 'livingthings', 'valley', 'es');
        // Remembered a while: the top bar does not ask on every page.
        $this->assertFalse(portal::has_world($student->id));
        unset($GLOBALS['SESSION']->local_oksigeniatesape_world);
        $this->assertTrue(portal::has_world($student->id));
        $world = portal::world($student->id);
        $this->assertCount(1, $world);
        $this->assertSame('Ciencias 5ºB', $world[0]['name']);
        $this->assertSame('student', $world[0]['role']);
        $this->assertSame('Los seres vivos', $world[0]['mission']);
    }
}
