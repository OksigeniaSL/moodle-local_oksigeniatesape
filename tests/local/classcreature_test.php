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
 * Tests for the creature of the class in infant school: it grows with what the class does together.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\classcreature
 */
#[\PHPUnit\Framework\Attributes\CoversClass(classcreature::class)]
final class classcreature_test extends \advanced_testcase {
    public function test_the_creature_of_the_class_grows_with_the_class(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        // Out of infant school, there is none and nothing grows.
        classcreature::grow($c, 'lantern');
        $this->assertNull(classcreature::view($c));
        regions::save($c, ['rules' => ['stage' => array_search('early', regions::STAGES, true)]]);
        $view = classcreature::view($c);
        $this->assertSame(
            [get_string('classcreature_name', 'local_oksigeniatesape'), 0, 1, 10],
            [$view['name'], $view['grown'], $view['stage'], $view['next']]
        );
        // Two lanterns: ten, the second stage, when it changed.
        $chapter = (object) ['id' => 1, 'code' => 'claro'];
        sessions::lit($c, $chapter);
        classcreature::grow($c, 'lantern', 1000);
        $view = classcreature::view($c);
        $this->assertSame([10, 2, 10, 25, 1000], [$view['grown'], $view['stage'], $view['from'], $view['next'], $view['grewat']]);
        // A battle and a scene, and a name.
        classcreature::grow($c, 'battle');
        classcreature::grow($c, 'scene');
        classcreature::grow($c, 'nothing');
        classcreature::name($c, '  Chispa  ');
        $view = classcreature::view($c);
        $this->assertSame(['Chispa', 15, 2], [$view['name'], $view['grown'], $view['stage']]);
        // The world of a student has it, without a character.
        $user = $gen->create_and_enrol($course, 'student');
        $world = view::world($c, (int) $user->id);
        $this->assertSame('Chispa', $world['classcreature']['name']);
        $this->assertSame(1000, view::board($c)['classcreature']['grewat']);
    }
}
