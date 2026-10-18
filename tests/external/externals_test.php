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
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\regions;

/**
 * Tests for the web services of the game: what they return, who can call them, and the flows of a class.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\external\get_world
 * @covers     \local_oksigeniatesape\external\create_character
 * @covers     \local_oksigeniatesape\external\make_character
 * @covers     \local_oksigeniatesape\external\update_look
 * @covers     \local_oksigeniatesape\external\get_class
 * @covers     \local_oksigeniatesape\external\save_region
 * @covers     \local_oksigeniatesape\external\award
 * @covers     \local_oksigeniatesape\external\validate
 * @covers     \local_oksigeniatesape\external\save_challenge
 * @covers     \local_oksigeniatesape\external\delete_challenge
 * @covers     \local_oksigeniatesape\external\set_status
 * @covers     \local_oksigeniatesape\external\set_step
 * @covers     \local_oksigeniatesape\external\get_log
 * @covers     \local_oksigeniatesape\external\undo
 * @covers     \local_oksigeniatesape\local\view
 */
#[\PHPUnit\Framework\Attributes\CoversClass(get_world::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(create_character::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(make_character::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(update_look::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_class::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(save_region::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(award::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(validate::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(save_challenge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(delete_challenge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_log::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(undo::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_oksigeniatesape\local\view::class)]
final class externals_test extends \advanced_testcase {
    /**
     * A course with the game on, a teacher, three students and two teams.
     *
     * @return \stdClass
     */
    private function make_class(): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        $w->teacher = $dg->create_and_enrol($w->course, 'editingteacher');
        $w->ana = $dg->create_and_enrol($w->course, 'student', ['firstname' => 'Ana', 'lastname' => 'Arrieta']);
        $w->leo = $dg->create_and_enrol($w->course, 'student', ['firstname' => 'Leo', 'lastname' => 'Benítez']);
        $w->noa = $dg->create_and_enrol($w->course, 'student', ['firstname' => 'Noa', 'lastname' => 'Cabral']);
        $w->owls = $dg->create_group(['courseid' => $w->course->id, 'name' => 'Owls']);
        $w->foxes = $dg->create_group(['courseid' => $w->course->id, 'name' => 'Foxes']);
        $dg->create_group_member(['groupid' => $w->owls->id, 'userid' => $w->ana->id]);
        $dg->create_group_member(['groupid' => $w->owls->id, 'userid' => $w->leo->id]);
        $dg->create_group_member(['groupid' => $w->foxes->id, 'userid' => $w->noa->id]);
        regions::save($w->course->id, ['enabled' => 1]);
        return $w;
    }

    /**
     * Calls a web service as a user and checks what it returns against its description.
     *
     * @param string $class Name of the class in this namespace.
     * @param \stdClass $user
     * @param array $args
     * @return mixed
     */
    private function call(string $class, \stdClass $user, array $args) {
        $this->setUser($user);
        $class = __NAMESPACE__ . '\\' . $class;
        $result = $class::execute(...$args);
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    public function test_student_makes_a_character_and_sees_the_world(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $world = $this->call('get_world', $w->ana, [$w->course->id]);
        $this->assertTrue($world['enabled']);
        $this->assertArrayNotHasKey('character', array_filter($world));
        $world = $this->call('create_character', $w->ana, [$w->course->id, 'healer', 'f', 'elf', '{"hair":"braid-2"}']);
        $this->assertEquals('healer', $world['character']['role']);
        $this->assertEquals(10, $world['character']['member']['energy']);
        $this->assertEquals('Owls', $world['team']['name']);
        $this->assertCount(2, $world['team']['members']);
        $world = $this->call('update_look', $w->ana, [$w->course->id, '{"hair":"short-1"}', 'dwarf']);
        $this->assertEquals('dwarf', $world['character']['lineage']);
        $this->assertEquals('{"hair":"short-1"}', $world['character']['appearance']);
    }

    public function test_classmates_never_see_why(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->call('create_character', $w->ana, [$w->course->id, 'healer', 'f']);
        $this->call('create_character', $w->leo, [$w->course->id, 'scout', 'm']);
        $this->call('set_status', $w->teacher, [$w->course->id, $w->leo->id, 'pause', 'Needs a break']);
        $world = $this->call('get_world', $w->ana, [$w->course->id]);
        $leo = array_values(array_filter($world['team']['members'], fn($m) => $m['userid'] == $w->leo->id))[0];
        // Not that he rests either: only that he is not playing.
        $this->assertEquals('away', $leo['character']['member']['status']);
        $this->assertEquals('', $leo['character']['member']['statusreason']);
        $this->assertEquals('Leo', $leo['name']);
        // Leo sees his own reason.
        $world = $this->call('get_world', $w->leo, [$w->course->id]);
        $this->assertEquals('Needs a break', $world['character']['member']['statusreason']);
        // The teacher too.
        $class = $this->call('get_class', $w->teacher, [$w->course->id]);
        $leo = array_values(array_filter($class['students'], fn($s) => $s['userid'] == $w->leo->id))[0];
        $this->assertEquals('Needs a break', $leo['character']['member']['statusreason']);
    }

    public function test_teacher_flow(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->call('create_character', $w->ana, [$w->course->id, 'healer', 'f']);
        $this->call('create_character', $w->leo, [$w->course->id, 'scout', 'm']);

        $class = $this->call('get_class', $w->teacher, [$w->course->id]);
        $this->assertCount(3, $class['students']);
        $this->assertEquals('Arrieta', explode(' ', $class['students'][0]['fullname'])[1]);
        $this->assertCount(2, $class['teams']);
        $this->assertTrue($class['region']['enabled']);

        // A touch of knowledge for the Owls; Noa has no character yet.
        $results = $this->call('award', $w->teacher, [$w->course->id, [$w->ana->id, $w->leo->id, $w->noa->id], 'saber', 15]);
        $this->assertEquals(['given', 'given', 'nocharacter'], array_column($results, 'status'));
        $this->assertEquals(15, characters::get($w->ana->id)->saber);
        // How each one is left, for the panel to draw it at once.
        $this->assertEquals((int) characters::get($w->ana->id)->level, $results[0]['level']);
        $this->assertEquals('active', $results[0]['state']);
        $this->assertArrayNotHasKey('level', $results[2]);

        // A classroom challenge, done by the team.
        $challenge = $this->call('save_challenge', $w->teacher, [$w->course->id, 0, 'Model of a plant cell', 'With plasticine',
            20, 2, '1,2,3,4', true]);
        $results = $this->call('validate', $w->teacher, [$w->course->id, $challenge['id'], [$w->ana->id, $w->leo->id],
            'merit', 'Beautiful']);
        $this->assertEquals(['merit', 'merit'], array_column($results, 'status'));
        $world = $this->call('get_world', $w->leo, [$w->course->id]);
        $this->assertEquals('merit', $world['challenges'][0]['status']);
        $this->assertEquals('Beautiful', $world['challenges'][0]['note']);
        $this->assertEquals(35, $world['character']['saber']);

        // The log, and undoing the touch for Leo.
        $log = $this->call('get_log', $w->teacher, [$w->course->id, $w->leo->id]);
        $touch = array_values(array_filter($log, fn($e) => $e['reason'] === 'manual'))[0];
        $this->assertEquals(fullname($w->teacher), $touch['actor']);
        $undo = $this->call('undo', $w->teacher, [$w->course->id, $touch['id']]);
        $this->assertEquals(-15, $undo['amount']);
        $this->assertEquals(20, characters::get($w->leo->id)->saber);

        // Step, energy and recovery.
        $this->assertEquals(['step' => 3], $this->call('set_step', $w->teacher, [$w->course->id, $w->ana->id, 3]));
        regions::save($w->course->id, ['dailyenergylosscap' => 0]);
        $this->call('award', $w->teacher, [$w->course->id, [$w->ana->id], 'energy', -10]);
        $state = $this->call('set_status', $w->teacher, [$w->course->id, $w->ana->id, 'recover']);
        $this->assertEquals(['status' => 'active', 'energy' => 5], $state);

        // Settings, and the challenge deleted.
        $region = $this->call('save_region', $w->teacher, [$w->course->id, null, 'animals', null, 60, null, null, false, 1, 0]);
        $this->assertEquals('animals', $region['family']);
        $this->assertEquals(60, $region['weeklycap']);
        $this->assertFalse($region['leaderboard']);
        $this->assertEquals(0, $region['completionrule']);
        $this->assertTrue($region['enabled']);
        $this->assertEquals(['ok' => true], $this->call('delete_challenge', $w->teacher, [$w->course->id, $challenge['id']]));
    }

    public function test_the_teacher_makes_the_character_of_a_student_without_a_device(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->call('make_character', $w->teacher, [$w->course->id, $w->noa->id, 'scout', 'f', 'dwarf']);
        $world = $this->call('get_world', $w->noa, [$w->course->id]);
        $this->assertEquals('scout', $world['character']['role']);
        $this->assertEquals('dwarf', $world['character']['lineage']);
        $this->assertEquals(10, $world['character']['member']['energy']);
        // Once only, and only for students of the course.
        try {
            $this->call('make_character', $w->teacher, [$w->course->id, $w->noa->id, 'sage', 'f', '']);
            $this->fail('Made twice');
        } catch (\moodle_exception $e) {
            $this->assertEquals('characterexists', $e->errorcode);
        }
        $stranger = $this->getDataGenerator()->create_user();
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('make_character', $w->teacher, [$w->course->id, $stranger->id, 'sage', 'm', '']);
    }

    public function test_students_cannot_teach(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->expectException(\required_capability_exception::class);
        $this->call('award', $w->ana, [$w->course->id, [$w->ana->id], 'saber', 100]);
    }

    public function test_only_students_of_the_course(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $stranger = $this->getDataGenerator()->create_user();
        characters::create($stranger->id, 'sage', 'm');
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('award', $w->teacher, [$w->course->id, [$stranger->id], 'saber', 10]);
    }

    public function test_only_energy_is_taken(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('award', $w->teacher, [$w->course->id, [$w->ana->id], 'saber', -10]);
    }

    public function test_entries_of_other_courses(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $other = $this->make_class();
        $this->call('create_character', $other->ana, [$other->course->id, 'sage', 'f']);
        $this->call('award', $other->teacher, [$other->course->id, [$other->ana->id], 'gems', 3]);
        $entry = $this->call('get_log', $other->teacher, [$other->course->id])[0];
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('undo', $w->teacher, [$w->course->id, $entry['id']]);
    }
}
