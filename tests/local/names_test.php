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
use local_oksigeniatesape\external\decide_name;
use local_oksigeniatesape\external\get_world;

/**
 * Tests for the hero names and the names of the creatures.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\names
 */
#[\PHPUnit\Framework\Attributes\CoversClass(names::class)]
final class names_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on and two students with a creature.
     *
     * @param int $nicknames 0 Moodle names, 1 from the list, 2 also their own.
     * @return \stdClass course, users (two), teacher.
     */
    private function make_class(int $nicknames): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1, 'nicknames' => $nicknames]);
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->users = [];
        foreach (['sage', 'scout'] as $role) {
            $user = $gen->create_and_enrol($w->course, 'student');
            $char = characters::create($user->id, $role, 'f');
            $DB->set_field(self::T . 'char', 'creature', json_encode(scenes::creature($char)), ['id' => $char->id]);
            engine::member($char, $w->course->id);
            $w->users[] = $user;
        }
        return $w;
    }

    public function test_the_lists_are_always_the_same_and_start_with_the_name_it_was_born_with(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $char = characters::get($w->users[0]->id);
        $heroes = names::hero_options($char);
        $this->assertCount(names::OPTIONS, $heroes);
        $this->assertSame($heroes, names::hero_options($char));
        $creatures = names::creature_options($char);
        $this->assertCount(names::OPTIONS, $creatures);
        $this->assertSame(scenes::creature($char)['name'], $creatures[0]);
    }

    public function test_a_name_from_the_list_counts_at_once(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $char = characters::get($w->users[0]->id);
        names::choose($w->course->id, $w->users[0]->id, 'hero', 3);
        $this->assertSame(names::hero_options($char)[3], characters::get($w->users[0]->id)->nickname);
        names::choose($w->course->id, $w->users[0]->id, 'creature', 2);
        $traits = json_decode(characters::get($w->users[0]->id)->creature, true);
        $this->assertSame(names::creature_options($char)[2], $traits['name']);
        // A classmate cannot take the same hero name in the course.
        $DB = $GLOBALS['DB'];
        $DB->set_field(self::T . 'char', 'nickname', names::hero_options($char)[3], ['userid' => $w->users[1]->id]);
        $DB->set_field(self::T . 'char', 'nickname', '', ['userid' => $w->users[0]->id]);
        try {
            names::choose($w->course->id, $w->users[0]->id, 'hero', 3);
            $this->fail('Two classmates with the same hero name');
        } catch (\moodle_exception $e) {
            $this->assertSame('nametaken', $e->errorcode);
        }
    }

    public function test_hero_names_and_own_names_need_the_course_to_allow_them(): void {
        $this->resetAfterTest();
        $w = $this->make_class(0);
        try {
            names::choose($w->course->id, $w->users[0]->id, 'hero', 0);
            $this->fail('A hero name where the course does not allow them');
        } catch (\moodle_exception $e) {
            $this->assertSame('nonames', $e->errorcode);
        }
        // The creature can always take a name from the list, but not an own one.
        names::choose($w->course->id, $w->users[0]->id, 'creature', 1);
        $this->expectException(\moodle_exception::class);
        names::choose($w->course->id, $w->users[0]->id, 'creature', -1, 'Trufa');
    }

    public function test_an_own_name_waits_for_a_teacher_and_classmates_do_not_see_it(): void {
        $this->resetAfterTest();
        $w = $this->make_class(2);
        $born = names::creature_options(characters::get($w->users[0]->id))[0];
        names::choose($w->course->id, $w->users[0]->id, 'creature', -1, '  Trufa   Mora ');
        names::choose($w->course->id, $w->users[0]->id, 'hero', -1, 'Rayo');
        $char = characters::get($w->users[0]->id);
        $this->assertSame('', (string) $char->nickname);
        $this->assertSame('Rayo', $char->nicknamepending);
        $this->assertSame($born, json_decode($char->creature, true)['name']);
        $pending = names::pending($w->course->id);
        $this->assertCount(2, $pending);
        $this->assertContains(['userid' => (int) $w->users[0]->id, 'what' => 'creature', 'name' => 'Trufa Mora'], $pending);
        // Not even in the creature of a teammate.
        $this->assertSame(['heropending' => 'Rayo', 'creaturepending' => 'Trufa Mora'], array_intersect_key(
            view::world($w->course->id, $w->users[0]->id)['names'],
            ['heropending' => 1, 'creaturepending' => 1]
        ));
        $group = $this->getDataGenerator()->create_group(['courseid' => $w->course->id]);
        foreach ($w->users as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        $mate = view::world($w->course->id, $w->users[1]->id)['team']['members'];
        $seen = array_values(array_filter($mate, fn($m) => $m['userid'] == $w->users[0]->id))[0];
        $this->assertStringNotContainsString('Trufa', $seen['character']['creature']);
        // The teacher takes one and turns down the other.
        $this->setUser($w->teacher);
        external_api::clean_returnvalue(
            decide_name::execute_returns(),
            decide_name::execute($w->course->id, $w->users[0]->id, 'creature', true)
        );
        $result = external_api::clean_returnvalue(
            decide_name::execute_returns(),
            decide_name::execute($w->course->id, $w->users[0]->id, 'hero', false)
        );
        $this->assertSame([], $result['names']);
        $char = characters::get($w->users[0]->id);
        $this->assertSame('Trufa Mora', json_decode($char->creature, true)['name']);
        $this->assertSame('', (string) $char->nickname);
        // The approved own name stays among the options.
        $this->assertSame('Trufa Mora', names::creature_options($char)[0]);
        // The world of the student still validates.
        $this->setUser($w->users[0]);
        external_api::clean_returnvalue(get_world::execute_returns(), view::world($w->course->id, $w->users[0]->id));
    }

    public function test_own_names_are_letters_only(): void {
        $this->assertSame("Ñandú O'Neill", names::clean("  Ñandú   O'Neill "));
        foreach (['A', 'R2D2', 'Hola!', str_repeat('a', names::MAX + 1), '<b>x</b>'] as $bad) {
            try {
                names::clean($bad);
                $this->fail('Took ' . $bad);
            } catch (\moodle_exception $e) {
                $this->assertSame('badname', $e->errorcode);
            }
        }
    }
}
