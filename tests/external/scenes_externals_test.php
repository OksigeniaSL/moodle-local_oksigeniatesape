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

namespace local_oksigeniatesape\external;

use core_external\external_api;
use local_oksigeniatesape\autoplayer;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\regions;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/autoplayer.php');

/**
 * Tests for the web services of missions and scenes.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\external\get_missions
 * @covers     \local_oksigeniatesape\external\install_mission
 * @covers     \local_oksigeniatesape\external\get_scene
 * @covers     \local_oksigeniatesape\external\play_scene
 * @covers     \local_oksigeniatesape\external\get_results
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_missions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(install_mission::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_scene::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(play_scene::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_results::class)]
final class scenes_externals_test extends \advanced_testcase {
    /**
     * Calls a web service as a user and checks what it returns against its description.
     *
     * @param string $class
     * @param \stdClass $user
     * @param array $args
     * @return mixed
     */
    private function call(string $class, \stdClass $user, array $args) {
        $this->setUser($user);
        $class = __NAMESPACE__ . '\\' . $class;
        return external_api::clean_returnvalue($class::execute_returns(), $class::execute(...$args));
    }

    public function test_install_play_and_see_the_results(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $teacher = $dg->create_and_enrol($course, 'editingteacher');
        $student = $dg->create_and_enrol($course, 'student');
        regions::save($course->id, ['enabled' => 1]);

        $missions = $this->call('get_missions', $teacher, [$course->id]);
        $this->assertArrayNotHasKey('installed', $missions);
        $pilot = $missions['packages'][0];
        $this->assertSame('livingthings', $pilot['code']);
        $this->assertSame(27, $pilot['scenes']);
        $this->assertSame(['valley', 'azara'], array_column($pilot['skins'], 'code'));
        $missions = $this->call('install_mission', $teacher, [$course->id, 'livingthings', 'valley', 'es']);
        $this->assertSame('valley', $missions['installed']['skin']);

        // The student sees the map, with every chapter and its core scenes.
        $world = $this->call('create_character', $student, [$course->id, 'scout', 'm']);
        $this->assertCount(6, $world['mission']['chapters']);
        $this->assertSame('Claro del Despertar', $world['mission']['chapters'][0]['name']);
        $nest = $world['mission']['chapters'][0]['scenes'][0];
        $this->assertSame('C1-2', $nest['code']);
        $this->assertFalse($nest['done']);

        $scene = $this->call('get_scene', $student, [$course->id, $nest['id']]);
        $this->assertSame('es', $scene['variant']);
        $this->assertSame('La druida mayor', json_decode($scene['speakers'], true)['druida-mayor']);
        // The place of its chapter, for the painting of the art pack.
        $this->assertSame('claro', $scene['place']);
        $events = autoplayer::play(json_decode($scene['definition'], true));
        $result = $this->call('play_scene', $student, [$course->id, $nest['id'], json_encode($events)]);
        $this->assertSame('success', $result['outcome']);
        $this->assertSame(20, $result['rewards']['saber']);
        $this->assertSame(['Claro del Despertar'], $result['rewards']['lanterns']);
        $this->assertSame(['Ser vivo'], $result['rewards']['pages']);
        $this->assertTrue($result['rewards']['hatched']);
        $this->assertTrue($result['world']['mission']['chapters'][0]['scenes'][0]['done']);
        $this->assertTrue($result['world']['mission']['chapters'][0]['mine']);
        $this->assertSame('Ser vivo', $result['world']['mission']['pages'][0]['title']);
        $this->assertNotEmpty($result['world']['character']['creature']);

        $results = $this->call('get_results', $teacher, [$course->id]);
        $nests = array_filter($results['chapters'][0]['scenes'], fn($sc) => $sc['code'] === 'C1-2');
        $this->assertSame(1, reset($nests)['done']);
        $this->assertTrue($results['chapters'][0]['lit']);
    }

    public function test_teachers_install_and_students_play(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $student = $dg->create_and_enrol($course, 'student');
        regions::save($course->id, ['enabled' => 1]);
        $this->expectException(\required_capability_exception::class);
        $this->call('install_mission', $student, [$course->id, 'livingthings', 'valley', 'es']);
    }

    public function test_bad_events(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $student = $dg->create_and_enrol($course, 'student');
        regions::save($course->id, ['enabled' => 1]);
        \local_oksigeniatesape\local\missions::install($course->id, 'livingthings', 'valley', 'es');
        characters::create($student->id, 'sage', 'm');
        $scene = $DB->get_record('local_oksigeniatesape_scene', ['code' => 'C1-2']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('play_scene', $student, [$course->id, $scene->id, '{"not": "a list"}']);
    }

    public function test_scenes_of_other_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $mine = $dg->create_course();
        $other = $dg->create_course();
        $student = $dg->create_and_enrol($mine, 'student');
        regions::save($mine->id, ['enabled' => 1]);
        \local_oksigeniatesape\local\missions::install($other->id, 'livingthings', 'valley', 'es');
        $scene = $DB->get_record('local_oksigeniatesape_scene', ['code' => 'C1-2']);
        $this->expectException(\dml_missing_record_exception::class);
        $this->call('get_scene', $student, [$mine->id, $scene->id]);
    }
}
