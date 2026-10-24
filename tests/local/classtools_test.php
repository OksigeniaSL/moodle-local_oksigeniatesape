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
 * Tests for what the panel of the teacher knows of Oksigenia Classtools: the groupings it made, and where it is.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\view
 */
#[\PHPUnit\Framework\Attributes\CoversClass(view::class)]
final class classtools_test extends \advanced_testcase {
    public function test_the_groupings_made_with_classtools_are_marked(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save((int) $course->id, ['enabled' => 1]);
        $gen->create_grouping(['courseid' => $course->id, 'name' => 'Equipos de clase']);
        $gen->create_grouping(['courseid' => $course->id, 'name' => 'Equipos al azar', 'idnumber' => 'oksigeniaclasstools-1-123']);
        $view = view::teacher((int) $course->id);
        $marks = array_column($view['groupings'], 'classtools', 'name');
        $this->assertEquals(['Equipos de clase' => false, 'Equipos al azar' => true], $marks);
        // Where Classtools is, only if the site has it.
        $installed = (bool) \core_component::get_component_directory('local_oksigeniaclasstools');
        $this->assertSame($installed, str_contains($view['classtools'], '/local/oksigeniaclasstools/index.php?id=' . $course->id));
    }
}
