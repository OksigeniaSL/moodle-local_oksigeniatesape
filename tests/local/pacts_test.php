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
use local_oksigeniatesape\external\choose_pact;
use local_oksigeniatesape\external\decide_pact;

/**
 * Tests for the commitments of the teams.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\pacts
 */
#[\PHPUnit\Framework\Attributes\CoversClass(pacts::class)]
final class pacts_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    /**
     * A course with the game on, the pilot mission, a team of three and a teacher.
     *
     * @return \stdClass course, mission, group, users (three), teacher.
     */
    private function make_class(): \stdClass {
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->mission = missions::install($w->course->id, 'livingthings', 'valley', 'es', self::NOW);
        $w->group = $gen->create_group(['courseid' => $w->course->id]);
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->users = [];
        foreach (['protector', 'healer', 'sage'] as $role) {
            $user = $gen->create_and_enrol($w->course, 'student');
            $gen->create_group_member(['groupid' => $w->group->id, 'userid' => $user->id]);
            engine::member(characters::create($user->id, $role, 'f'), $w->course->id, self::NOW);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * The member of a student in the course, fresh.
     *
     * @param \stdClass $w
     * @param \stdClass $user
     * @return \stdClass
     */
    private function member(\stdClass $w, \stdClass $user): \stdClass {
        return engine::member(characters::get($user->id), $w->course->id, self::NOW);
    }

    public function test_each_member_chooses_one_of_the_chapter_the_class_is_in(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->assertSame('claro', pacts::chapter(missions::get($w->course->id))->code);
        $options = pacts::options(missions::get($w->course->id), 'claro');
        $this->assertCount(4, $options);
        pacts::choose($w->course->id, $w->users[0]->id, 0, self::NOW);
        // While it is not marked, it can be changed.
        $pact = pacts::choose($w->course->id, $w->users[0]->id, 2, self::NOW);
        $this->assertSame($options[2], $pact->text);
        $names = [$w->users[0]->id => 'Ana', $w->users[1]->id => 'Leo', $w->users[2]->id => 'Eva'];
        $view = pacts::for_team($w->course->id, $w->group, $w->users[0]->id, $names);
        $this->assertSame('Claro del Despertar', $view['chapter']);
        $this->assertSame(2, $view['mine']);
        $this->assertSame('open', $view['status']);
        $this->assertSame([['name' => 'Ana', 'text' => $options[2], 'status' => 'open', 'me' => true]], $view['list']);
        // A teammate sees it too, and has none yet.
        $other = pacts::for_team($w->course->id, $w->group, $w->users[1]->id, $names);
        $this->assertSame(-1, $other['mine']);
        $this->assertFalse($other['list'][0]['me']);
        // Once marked, it stays.
        pacts::decide((int) $pact->id, 'kept', $w->teacher->id, self::NOW);
        try {
            pacts::choose($w->course->id, $w->users[0]->id, 1, self::NOW);
            $this->fail('A marked commitment was changed');
        } catch (\moodle_exception $e) {
            $this->assertSame('pactdecided', $e->errorcode);
        }
    }

    public function test_without_a_team_there_is_no_commitment(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $loner = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        engine::member(characters::create($loner->id, 'scout', 'm'), $w->course->id, self::NOW);
        $this->expectException(\moodle_exception::class);
        pacts::choose($w->course->id, $loner->id, 0, self::NOW);
    }

    public function test_a_commitment_not_kept_costs_energy_and_taking_it_back_returns_it(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $pact = pacts::choose($w->course->id, $w->users[0]->id, 0, self::NOW);
        pacts::decide((int) $pact->id, 'broken', $w->teacher->id, self::NOW);
        $this->assertEquals(engine::ENERGY_MAX - pacts::LOSS, $this->member($w, $w->users[0])->energy);
        // The student sees it marked; the team, without a mark.
        $names = [$w->users[0]->id => 'Ana', $w->users[1]->id => 'Leo', $w->users[2]->id => 'Eva'];
        $this->assertSame('broken', pacts::for_team($w->course->id, $w->group, $w->users[0]->id, $names)['list'][0]['status']);
        $this->assertSame('', pacts::for_team($w->course->id, $w->group, $w->users[1]->id, $names)['list'][0]['status']);
        $where = ['reason' => 'commitment', 'refid' => $pact->id, 'resource' => 'energy'];
        $this->assertEquals(1, $DB->count_records(self::T . 'ledger', $where));
        // It was kept after all: the energy comes back.
        pacts::decide((int) $pact->id, 'kept', $w->teacher->id, self::NOW);
        $this->assertEquals(engine::ENERGY_MAX, $this->member($w, $w->users[0])->energy);
        $this->assertEquals('kept', $DB->get_field(self::T . 'pact', 'status', ['id' => $pact->id]));
        $this->assertEquals(0, pacts::pending($w->course->id));
    }

    public function test_a_team_that_keeps_them_all_gets_breath_once(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        foreach ($w->users as $user) {
            $DB->set_field(self::T . 'member', 'breath', 1, ['id' => $this->member($w, $user)->id]);
        }
        $first = pacts::choose($w->course->id, $w->users[0]->id, 0, self::NOW);
        $second = pacts::choose($w->course->id, $w->users[1]->id, 1, self::NOW);
        pacts::decide((int) $first->id, 'kept', $w->teacher->id, self::NOW);
        // One kept and one pending: not yet.
        $this->assertEquals(1, $this->member($w, $w->users[2])->breath);
        pacts::decide((int) $second->id, 'kept', $w->teacher->id, self::NOW);
        // Everybody in the team, also who chose none.
        foreach ($w->users as $user) {
            $this->assertEquals(1 + pacts::BREATH, $this->member($w, $user)->breath);
        }
        $names = [$w->users[2]->id => 'Eva'];
        $this->assertTrue(pacts::for_team($w->course->id, $w->group, $w->users[2]->id, $names)['done']);
        // Marking again changes nothing; one not kept after all takes the breath back.
        pacts::decide((int) $second->id, 'kept', $w->teacher->id, self::NOW);
        $this->assertEquals(1 + pacts::BREATH, $this->member($w, $w->users[2])->breath);
        pacts::decide((int) $second->id, 'broken', $w->teacher->id, self::NOW);
        $this->assertEquals(1, $this->member($w, $w->users[2])->breath);
        $this->assertFalse(pacts::for_team($w->course->id, $w->group, $w->users[2]->id, $names)['done']);
    }

    public function test_the_teacher_sees_the_chapter_and_what_is_pending_from_earlier_ones(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $kept = pacts::choose($w->course->id, $w->users[0]->id, 0, self::NOW);
        pacts::choose($w->course->id, $w->users[1]->id, 1, self::NOW);
        pacts::decide((int) $kept->id, 'kept', $w->teacher->id, self::NOW);
        // The class lights the lantern of the clearing: the chapter moves on.
        $DB->set_field(self::T . 'chapter', 'litat', self::NOW, ['missionid' => $w->mission->id, 'code' => 'claro']);
        $view = pacts::for_teacher($w->course->id);
        $this->assertSame('Biblioteca de los Reinos', $view['chapter']);
        $this->assertCount(1, $view['list']);
        $this->assertSame((int) $w->users[1]->id, $view['list'][0]['userid']);
        $this->assertSame('Claro del Despertar', $view['list'][0]['chapter']);
        $this->assertEquals(1, pacts::pending($w->course->id));
        // In the new chapter there are new ones to choose.
        $pact = pacts::choose($w->course->id, $w->users[0]->id, 0, self::NOW);
        $this->assertSame('biblioteca', $pact->chapter);
    }

    public function test_the_services_check_who_does_what(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->setUser($w->users[0]);
        $world = external_api::clean_returnvalue(
            choose_pact::execute_returns(),
            choose_pact::execute($w->course->id, 1)
        );
        $this->assertSame(1, $world['team']['pacts']['mine']);
        $pactid = (int) $GLOBALS['DB']->get_field(self::T . 'pact', 'id', ['courseid' => $w->course->id]);
        try {
            decide_pact::execute($w->course->id, $pactid, 'kept');
            $this->fail('A student marked a commitment');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->setUser($w->teacher);
        $result = external_api::clean_returnvalue(
            decide_pact::execute_returns(),
            decide_pact::execute($w->course->id, $pactid, 'kept')
        );
        $this->assertSame(0, $result['pactspending']);
        $this->assertSame('kept', $result['pacts']['list'][0]['status']);
    }

    public function test_the_teacher_marks_a_whole_team_and_then_unmarks_who_did_not(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $ids = [];
        foreach ($w->users as $i => $user) {
            $ids[] = (int) pacts::choose($w->course->id, $user->id, $i, self::NOW)->id;
        }
        $this->setUser($w->teacher);
        $result = external_api::clean_returnvalue(
            decide_pact::execute_returns(),
            decide_pact::execute($w->course->id, 0, 'kept', $ids)
        );
        $this->assertSame(0, $result['pactspending']);
        foreach ($ids as $id) {
            $this->assertSame('kept', $DB->get_field(self::T . 'pact', 'status', ['id' => $id]));
        }
        // Who did not keep it is unmarked one by one, as before.
        decide_pact::execute($w->course->id, $ids[2], 'broken');
        $this->assertSame('broken', $DB->get_field(self::T . 'pact', 'status', ['id' => $ids[2]]));
        // A commitment of another course is not marked, nor any of the list.
        $other = $this->make_class();
        $foreign = (int) pacts::choose($other->course->id, $other->users[0]->id, 0, self::NOW)->id;
        try {
            decide_pact::execute($w->course->id, 0, 'open', [$ids[0], $foreign]);
            $this->fail('A commitment of another course was marked');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame('kept', $DB->get_field(self::T . 'pact', 'status', ['id' => $ids[0]]));
        }
    }
}
