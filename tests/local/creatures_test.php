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
 * Tests for the creature: its branch, chosen once at its second stage, with the coat of the branch; the places it knows
 * (the lanterns its student lit) and its sniff in the minigames.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\creatures
 */
#[\PHPUnit\Framework\Attributes\CoversClass(creatures::class)]
final class creatures_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_at_its_second_stage_the_creature_chooses_its_way_once(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        engine::member($char, $course->id);
        // Not before it hatches, nor at its first stage.
        $this->assertFalse(creatures::branch_pending($char));
        $this->assertSame(0, creatures::sniffs($char));
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode(scenes::creature($char)),
            'creaturestage' => 1]);
        $char = characters::get($user->id);
        $this->assertFalse(creatures::branch_pending($char));
        $this->assertSame(0, creatures::sniffs($char));
        // At its second stage it chooses: air, and it wears feathers from then on.
        $DB->set_field(self::T . 'char', 'creaturestage', 2, ['id' => $char->id]);
        $char = characters::get($user->id);
        $this->assertTrue(creatures::branch_pending($char));
        $this->assertSame(1, creatures::sniffs($char));
        $this->setUser($user);
        $this->assertTrue(view::world($course->id, $user->id)['character']['branchpending']);
        $char = creatures::choose_branch($char, 'air');
        $this->assertSame('air', creatures::branch(characters::get($user->id)));
        $this->assertSame('feathers', creatures::traits(characters::get($user->id))['coat']);
        $this->assertFalse(view::world($course->id, $user->id)['character']['branchpending']);
        // Once; and the coat is the one of its way.
        try {
            creatures::choose_branch(characters::get($user->id), 'water');
            $this->fail('A second way');
        } catch (\moodle_exception $e) {
            $this->assertSame('nobranch', $e->errorcode);
        }
        try {
            minigames::set_coat($course->id, $user->id, 'scales');
            $this->fail('Another coat than the one of its way');
        } catch (\moodle_exception $e) {
            $this->assertSame('nocoat', $e->errorcode);
        }
        // The places it knows: those whose lantern its student lit.
        $this->assertSame([], creatures::known($char, $mission));
        $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $course->id,
            'missionid' => $mission->id, 'kind' => 'lantern', 'code' => 'claro', 'timecreated' => time()]);
        $this->assertSame(['claro'], creatures::known($char, $mission));
        // In the minigames, from its second stage, it sniffs once a play.
        $this->assertSame(1, minigames::config(missions::get($course->id), 'enredobosque', 2, 'scout', [], 1)['sniffs']);
    }
}
