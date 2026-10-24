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
 * Tests for the museum of the class: the entries of the field guide of the whole class fill a shelf for each group, with
 * a light from three books, and no entry says who made it.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\museum
 */
#[\PHPUnit\Framework\Attributes\CoversClass(museum::class)]
final class museum_test extends \advanced_testcase {
    public function test_the_entries_of_the_class_fill_the_shelves_of_their_groups(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $this->assertNull(museum::of_course($course->id));
        $data = $gen->create_module('data', ['course' => $course->id]);
        $dgen = $gen->get_plugin_generator('mod_data');
        $dgen->create_field((object) ['name' => 'Nombre', 'type' => 'text'], $data);
        $dgen->create_field((object) ['name' => 'Grupo', 'type' => 'menu', 'param1' => "Ave\nPlanta\nHongo"], $data);
        $dgen->create_field((object) ['name' => 'Cómo es', 'type' => 'textarea'], $data);
        $dgen->create_field((object) ['name' => 'Dónde vive', 'type' => 'text'], $data);
        $dgen->create_field((object) ['name' => 'Cadena', 'type' => 'menu', 'param1' => "Productor\nHerbívoro"], $data);
        $ids = $DB->get_records_menu('data_fields', ['dataid' => $data->id], 'id', 'name, id');
        activities::save($course->id, (int) $data->cmid, ['saber' => 30]);
        // Not a guide of the game yet, it has no museum; now it has one, with its shelves empty.
        $this->assertSame([['group' => 'Ave', 'lit' => false, 'books' => []], ['group' => 'Planta', 'lit' => false,
            'books' => []], ['group' => 'Hongo', 'lit' => false, 'books' => []]], museum::of_course($course->id)['shelves']);
        $entries = [['Hornero', 'Ave', 'Herbívoro'], ['Tucán', 'Ave', ''], ['Lapacho', 'Planta', 'Productor'],
            ['Garza', 'Ave', ''], ['Yacaré', '', ''], ['', 'Ave', '']];
        foreach ($entries as [$name, $group, $chain]) {
            $this->setUser($gen->create_and_enrol($course, 'student'));
            $dgen->create_entry($data, [$ids['Nombre'] => $name, $ids['Grupo'] => $group, $ids['Cómo es'] => '<b>Pequeño</b>',
                $ids['Dónde vive'] => 'En el monte', $ids['Cadena'] => $chain]);
        }
        $museum = museum::of_course($course->id);
        // Five entries with a name; the one without a group, on a shelf of its own.
        $this->assertSame(5, $museum['entries']);
        $shelves = array_column($museum['shelves'], null, 'group');
        $this->assertSame(['Hornero', 'Tucán', 'Garza'], array_column($shelves['Ave']['books'], 'name'));
        $this->assertTrue($shelves['Ave']['lit']);
        $this->assertFalse($shelves['Planta']['lit']);
        $this->assertSame([], $shelves['Hongo']['books']);
        $this->assertSame(['Yacaré'], array_column($shelves['']['books'], 'name'));
        $this->assertSame([true, false, false], array_column($shelves['Ave']['books'], 'golden'));
        $first = $shelves['Ave']['books'][0];
        $this->assertSame(['name' => 'Hornero', 'golden' => true, 'looks' => 'Pequeño', 'lives' => 'En el monte',
            'chain' => 'Herbívoro'], $first);
        // Where the guide asks for approval, only the approved entries are books.
        $DB->set_field('data', 'approval', 1, ['id' => $data->id]);
        // The first entry, the hornero.
        $hornero = array_key_first($DB->get_records('data_records', ['dataid' => $data->id], 'id', 'id'));
        $DB->set_field('data_records', 'approved', 1, ['id' => $hornero]);
        $museum = museum::of_course($course->id);
        $this->assertSame(1, $museum['entries']);
        $this->assertSame(['Hornero'], array_merge(...array_map(fn($s) => array_column($s['books'], 'name'), $museum['shelves'])));
    }
    public function test_a_class_shares_its_library_and_other_regions_visit_it(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $mine = $gen->create_course();
        $theirs = $gen->create_course(['fullname' => 'Cuarto B']);
        regions::save($mine->id, ['enabled' => 1]);
        regions::save($theirs->id, ['enabled' => 1]);
        $data = $gen->create_module('data', ['course' => $theirs->id]);
        $dgen = $gen->get_plugin_generator('mod_data');
        $dgen->create_field((object) ['name' => 'Nombre', 'type' => 'text'], $data);
        $dgen->create_field((object) ['name' => 'Grupo', 'type' => 'menu', 'param1' => "Ave\nPlanta"], $data);
        $ids = $DB->get_records_menu('data_fields', ['dataid' => $data->id], 'id', 'name, id');
        activities::save($theirs->id, (int) $data->cmid, ['saber' => 30]);
        $this->setUser($gen->create_and_enrol($theirs, 'student'));
        $dgen->create_entry($data, [$ids['Nombre'] => 'Hornero', $ids['Grupo'] => 'Ave']);
        // Not shared: nobody else sees it.
        $this->assertSame([], museum::shared($mine->id));
        try {
            museum::visit($mine->id, $theirs->id);
            $this->fail('A library not shared');
        } catch (\moodle_exception $e) {
            $this->assertSame('nolibrary', $e->errorcode);
        }
        // Shared by its teacher: the other region sees it by the name of its course, and reads its shelves.
        regions::save($theirs->id, ['rules' => ['library' => 1]]);
        $this->assertSame([['courseid' => (int) $theirs->id, 'name' => 'Cuarto B']], museum::shared($mine->id));
        $this->assertSame([], museum::shared($theirs->id));
        $visit = museum::visit($mine->id, $theirs->id);
        $this->assertSame('Cuarto B', $visit['name']);
        $this->assertSame(1, $visit['museum']['entries']);
        $this->assertSame('Hornero', $visit['museum']['shelves'][0]['books'][0]['name']);
        // Without the game on, or hidden, it is not shared any more.
        regions::save($theirs->id, ['enabled' => 0]);
        $this->assertSame([], museum::shared($mine->id));
    }
}
