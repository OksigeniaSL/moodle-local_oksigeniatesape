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
use local_oksigeniatesape\external\get_class;
use local_oksigeniatesape\external\get_world;
use local_oksigeniatesape\external\play_tangle;
use local_oksigeniatesape\external\review_tangle;

/**
 * Tests for Be the Embrollo: the tangles the teams write.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\tangles
 */
#[\PHPUnit\Framework\Attributes\CoversClass(tangles::class)]
final class tangles_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    /** @var array A tangle as a team writes it. */
    private const BAT = ['claim' => '¡El murciélago es un pájaro, que vuela!', 'truth' => 'Es un mamífero: tiene pelo y sus crías maman.',
        'trap' => 'Es un insecto muy grande.', 'why' => 'Los mamíferos tienen pelo y dan leche a sus crías; las aves, plumas.'];

    /**
     * A course with the game on, the pilot mission, two teams of two and a teacher.
     *
     * @return \stdClass course, teams (two), users (four: two in each team), teacher.
     */
    private function make_class(): \stdClass {
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        missions::install($w->course->id, 'livingthings', 'valley', 'es', self::NOW);
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->teams = [$gen->create_group(['courseid' => $w->course->id]), $gen->create_group(['courseid' => $w->course->id])];
        $w->users = [];
        foreach (['protector', 'healer', 'sage', 'scout'] as $i => $role) {
            $user = $gen->create_and_enrol($w->course, 'student');
            $gen->create_group_member(['groupid' => $w->teams[intdiv($i, 2)]->id, 'userid' => $user->id]);
            engine::member(characters::create($user->id, $role, $i % 2 ? 'm' : 'f'), $w->course->id, self::NOW);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * The gems of a student.
     *
     * @param \stdClass $user
     * @return int
     */
    private function gems(\stdClass $user): int {
        return (int) characters::get($user->id)->gems;
    }

    public function test_a_team_writes_its_tangle_the_teacher_approves_it_and_another_team_undoes_it(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $c = $w->course->id;
        [$ana, $bruno, $carla, $dani] = $w->users;
        // Written and written again while it waits: the same tangle, nobody else sees it.
        $first = tangles::write($c, $ana->id, ['claim' => 'Algo', 'truth' => 'Otra cosa', 'why' => 'Porque sí'], self::NOW);
        $tangle = tangles::write($c, $bruno->id, self::BAT, self::NOW + 60);
        $this->assertSame((int) $first->id, (int) $tangle->id);
        $this->assertSame('pending', $tangle->status);
        $this->assertSame([], tangles::for_student($c, $carla->id));
        $this->assertSame(1, tangles::pending($c));
        $this->assertSame('pending', view::tangles($c, $ana->id, self::NOW + 60)['mine']['status']);
        $this->assertTrue(view::tangles($c, $ana->id, self::NOW + 60)['canwrite']);
        // Approved: each one of the team gets their gems, and the other team sees it on its map.
        $before = [$this->gems($ana), $this->gems($carla)];
        tangles::review($c, (int) $tangle->id, true, (int) $w->teacher->id, self::NOW + 120);
        $this->assertSame($before[0] + tangles::GEMS_WRITTEN, $this->gems($ana));
        $this->assertSame($before[1], $this->gems($carla));
        $this->assertFalse(view::tangles($c, $ana->id, self::NOW + 120)['canwrite']);
        $this->assertSame([], tangles::for_student($c, $bruno->id));
        $seen = tangles::for_student($c, $carla->id);
        $this->assertCount(1, $seen);
        $this->assertSame(self::BAT['claim'], $seen[0]['claim']);
        $this->assertCount(3, $seen[0]['options']);
        $this->assertContains(self::BAT['truth'], $seen[0]['options']);
        // Undone at the first try: a gem, once; then it is gone from the map.
        $right = array_search(self::BAT['truth'], $seen[0]['options'], true);
        $result = tangles::undo($c, $carla->id, (int) $tangle->id, $right, self::NOW + 180);
        $this->assertTrue($result['right']);
        $this->assertSame(tangles::GEMS_UNDONE, $result['gems']);
        $this->assertSame(self::BAT['why'], $result['why']);
        $this->assertSame([], tangles::for_student($c, $carla->id));
        $again = tangles::undo($c, $carla->id, (int) $tangle->id, $right, self::NOW + 200);
        $this->assertSame(0, $again['gems']);
        // Got wrong by the other: no gem, and it counts as tried.
        $wrong = ($right + 1) % 3;
        $this->assertFalse(tangles::undo($c, $dani->id, (int) $tangle->id, $wrong, self::NOW + 240)['right']);
        $mine = tangles::for_team($c, $ana->id, self::NOW + 300);
        $this->assertSame(['tried' => 2, 'undone' => 1], ['tried' => $mine['tried'], 'undone' => $mine['undone']]);
        // Its own team cannot undo it; and one approved a week.
        try {
            tangles::undo($c, $ana->id, (int) $tangle->id, 0);
            $this->fail('Its own team');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('another team', $e->getMessage());
        }
        try {
            tangles::write($c, $ana->id, self::BAT, self::NOW + 400);
            $this->fail('Two in a week');
        } catch (\moodle_exception $e) {
            $this->assertSame('tanglewait', $e->errorcode);
        }
        // For the teacher: how it went, without names of who tried.
        $teacher = tangles::for_teacher($c);
        $this->assertSame([], $teacher['pending']);
        $this->assertSame(2, $teacher['approved'][0]['tried']);
        $this->assertFalse($teacher['approved'][0]['inbattle']);
        // On the class screen, in a session.
        $board = tangles::for_board($c, 7);
        $this->assertSame(self::BAT['claim'], $board['claim']);
        $this->assertContains(self::BAT['truth'], $board['options']);
    }

    public function test_the_services_write_approve_and_undo(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $c = $w->course->id;
        [$ana, , $carla] = $w->users;
        $this->setUser($ana);
        $mine = external_api::clean_returnvalue(play_tangle::execute_returns(), play_tangle::execute(
            $c,
            'write',
            self::BAT['claim'],
            self::BAT['truth'],
            '',
            self::BAT['why']
        ));
        $this->assertSame('pending', $mine['tangles']['mine']['status']);
        $this->setUser($w->teacher);
        $class = external_api::clean_returnvalue(get_class::execute_returns(), get_class::execute($c));
        $this->assertCount(1, $class['tangles']['pending']);
        $done = external_api::clean_returnvalue(
            review_tangle::execute_returns(),
            review_tangle::execute($c, $class['tangles']['pending'][0]['id'], 'approve')
        );
        $this->assertCount(1, $done['tangles']['approved']);
        $this->setUser($carla);
        $world = external_api::clean_returnvalue(get_world::execute_returns(), get_world::execute($c));
        $one = $world['tangles']['others'][0];
        $this->assertCount(2, $one['options']);
        $undone = external_api::clean_returnvalue(play_tangle::execute_returns(), play_tangle::execute(
            $c,
            'undo',
            '',
            '',
            '',
            '',
            $one['id'],
            array_search(self::BAT['truth'], $one['options'], true)
        ));
        $this->assertTrue($undone['result']['right']);
        $this->assertSame([], $undone['tangles']['others']);
    }

    public function test_a_tangle_given_back_and_one_taken_to_the_battle(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $c = $w->course->id;
        [$ana, , $carla] = $w->users;
        // Given back: the team can write another, and nobody gets gems.
        $tangle = tangles::write($c, $ana->id, self::BAT, self::NOW);
        $gems = $this->gems($ana);
        tangles::review($c, (int) $tangle->id, false, (int) $w->teacher->id, self::NOW + 60);
        $this->assertSame($gems, $this->gems($ana));
        $this->assertSame('rejected', view::tangles($c, $ana->id, self::NOW + 60)['mine']['status']);
        $this->assertTrue(view::tangles($c, $ana->id, self::NOW + 60)['canwrite']);
        $this->assertSame([], tangles::for_student($c, $carla->id));
        // Without what is true or why, it is not a tangle.
        try {
            tangles::write($c, $ana->id, ['claim' => 'Algo', 'truth' => '', 'why' => ''], self::NOW + 120);
            $this->fail('Empty');
        } catch (\moodle_exception $e) {
            $this->assertSame('tangleempty', $e->errorcode);
        }
        // Another one, approved and taken to the battle: a question of the place of now, with what is true first.
        $tangle = tangles::write($c, $ana->id, self::BAT, self::NOW + 180);
        tangles::review($c, (int) $tangle->id, true, (int) $w->teacher->id, self::NOW + 240);
        $count = count(edits::view($c)['questions']);
        $question = tangles::to_battle($c, (int) $tangle->id);
        $questions = edits::view($c)['questions'];
        $this->assertCount($count + 1, $questions);
        $this->assertSame('claro', $question['chapter']);
        $this->assertSame(self::BAT['truth'], $question['options'][0]);
        $this->assertCount(3, $question['options']);
        $this->assertStringContainsString('«¡El murciélago es un pájaro, que vuela!»', $question['text']);
        $this->assertTrue(tangles::for_teacher($c)['approved'][0]['inbattle']);
        // When the course is emptied, they go.
        tangles::remove_in_course($c);
        $this->assertSame([], tangles::for_teacher($c)['approved']);
    }
}
