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
use local_oksigeniatesape\external\get_board;
use local_oksigeniatesape\external\get_class;

/**
 * Tests for «Today in the valley» and the letters of the Embrollo.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\today
 */
#[\PHPUnit\Framework\Attributes\CoversClass(today::class)]
final class today_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_sessions_of_the_chapter_the_class_is_in_and_the_last_letter(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $today = today::get($course->id, true);
        $this->assertSame('Claro del Despertar', $today['chapter']);
        $this->assertCount(2, $today['sessions']);
        $nest = $DB->get_field(self::T . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $this->assertEquals($nest, $today['sessions'][1]['sceneid']);
        $this->assertSame('El Nido de la Vida', $today['sessions'][1]['scene']);
        $this->assertNotSame('', $today['sessions'][0]['materials']);
        $this->assertArrayNotHasKey('letter', $today);
        // Nobody has played yet: no mix-up.
        $this->assertArrayNotHasKey('mixup', $today);
        // The class lights the lantern of the clearing: the library comes next, with the letter of the clearing.
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id, 'code' => 'claro']);
        $today = today::get($course->id);
        $this->assertSame('biblioteca', $today['code']);
        $this->assertSame('Claro del Despertar', $today['letter']['chapter']);
        $this->assertStringContainsString('pompas', $today['letter']['text']);
        // Under the letter, what waits in the next place.
        $this->assertStringContainsString('Biblioteca de los Reinos', $today['letter']['teaser']);
        // The last chapter brings the final battle.
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id]);
        $DB->set_field(self::T . 'chapter', 'litat', 0, ['missionid' => $mission->id, 'code' => 'torre']);
        $this->assertTrue(today::get($course->id)['sessions'][1]['final']);
    }

    public function test_the_class_screen_the_panel_and_the_map_show_it(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'azara', 'py');
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id, 'code' => 'claro']);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        characters::create($student->id, 'sage', 'f');
        $this->setUser($teacher);
        $board = external_api::clean_returnvalue(get_board::execute_returns(), get_board::execute($course->id));
        $this->assertStringContainsString('Nota suelta', $board['today']['letter']['text']);
        $class = external_api::clean_returnvalue(get_class::execute_returns(), get_class::execute($course->id));
        $this->assertSame('Las cajas de la expedición', $class['today']['chapter']);
        $map = view::world($course->id, $student->id)['mission']['chapters'];
        $this->assertStringContainsString('Nota suelta', $map[0]['letter']);
        $this->assertStringContainsString('cajas de la expedición', $map[0]['teaser']);
        $this->assertSame('', $map[1]['letter']);
        $this->assertSame('', $map[1]['teaser']);
    }
}
