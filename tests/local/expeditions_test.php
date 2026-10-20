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

use core_external\external_api;
use local_oksigeniatesape\external\get_expedition;
use local_oksigeniatesape\external\get_missions;
use local_oksigeniatesape\external\get_world;

/**
 * Tests for the expeditions: the missions a course made before the current one stay, lit, to visit.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\missions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(missions::class)]
final class expeditions_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_an_earlier_mission_stays_lit_and_can_be_visited(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $first = missions::install($course->id, 'livingthings', 'valley', 'es');
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $first->id, 'code' => 'claro']);
        $student = $gen->create_and_enrol($course, 'student');
        $char = characters::create($student->id, 'sage', 'f');
        $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $course->id,
            'missionid' => $first->id, 'kind' => 'lantern', 'code' => 'claro', 'timecreated' => time()]);
        $this->assertSame([], missions::past($course->id));
        // A second mission starts a new expedition (a copy of the first, standing for any other mission).
        $second = clone $first;
        unset($second->id);
        $second->code = 'second';
        $second->id = $DB->insert_record(self::T . 'mission', $second);
        $this->assertEquals($second->id, missions::get($course->id)->id);
        $past = view::expeditions($course->id, (int) $char->id);
        $this->assertCount(1, $past);
        $this->assertSame([(int) $first->id, 'livingthings', 1, 6, 1, 0], [$past[0]['id'], $past[0]['code'], $past[0]['lit'],
            $past[0]['total'], $past[0]['mine'], $past[0]['pages']]);
        // The student sees it in their world and visits its map, as the class left it.
        $this->setUser($student);
        $world = external_api::clean_returnvalue(get_world::execute_returns(), get_world::execute($course->id));
        $this->assertCount(1, $world['expeditions']);
        $visit = external_api::clean_returnvalue(
            get_expedition::execute_returns(),
            get_expedition::execute($course->id, $first->id)
        )['mission'];
        $this->assertTrue($visit['chapters'][0]['lit']);
        $this->assertTrue($visit['chapters'][0]['mine']);
        $this->assertNotSame('', $visit['chapters'][0]['letter']);
        // Not one of another course.
        $other = $gen->create_course();
        regions::save($other->id, ['enabled' => 1]);
        $elsewhere = missions::install($other->id, 'livingthings', 'valley', 'es');
        $this->expectException(\dml_missing_record_exception::class);
        get_expedition::execute($course->id, $elsewhere->id);
    }

    public function test_the_teacher_sees_the_expeditions_of_the_course(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $first = missions::install($course->id, 'livingthings', 'valley', 'es');
        $second = clone $first;
        unset($second->id);
        $second->code = 'second';
        $DB->insert_record(self::T . 'mission', $second);
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        $missions = external_api::clean_returnvalue(get_missions::execute_returns(), get_missions::execute($course->id));
        $this->assertSame('second', $missions['installed']['code']);
        $this->assertSame(['livingthings'], array_column($missions['expeditions'], 'code'));
    }
}
