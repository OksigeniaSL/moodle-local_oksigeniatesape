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
 * Tests for the knot of a team: the tablets of a team against some knots of the Embrollo, each one with the verb of
 * their role.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\teamknots
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teamknots::class)]
final class teamknots_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment, at midday. */
    private const NOW = 1800014400;

    /**
     * The right option of the turn at hand, and a wrong one not struck out.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @return array [right, wrong]
     */
    private function options(\stdClass $huddle, \stdClass $char): array {
        $view = teamknots::view($this->reload($huddle), $char, self::NOW);
        $state = json_decode((string) $this->reload($huddle)->state, true)['knot'];
        $q = questions::get((int) $huddle->courseid, $state['plan'][$state['round']][1], (int) $huddle->id * 10 + $state['round']);
        foreach ($view['options'] as $i => $o) {
            if ($i !== (int) $q['correct'] && !$o['struck']) {
                return [(int) $q['correct'], $i];
            }
        }
        return [(int) $q['correct'], -1];
    }

    /**
     * The session as it is now.
     *
     * @param \stdClass $huddle
     * @return \stdClass
     */
    private function reload(\stdClass $huddle): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'huddle', ['id' => $huddle->id], '*', MUST_EXIST);
    }

    public function test_a_team_unties_a_knot_with_the_verbs_of_their_roles(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        $mission = missions::install($c, 'livingthings', 'valley', 'es', self::NOW);
        $group = $gen->create_group(['courseid' => $c]);
        $users = [];
        $chars = [];
        foreach (['protector', 'scout', 'healer', 'sage'] as $role) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
            $chars[] = characters::create($user->id, $role, 'f');
            engine::member(end($chars), $c, self::NOW);
            $users[] = $user;
        }
        [$ana, $leo, $eva, $tom] = $users;
        [$cana, $cleo, $ceva, $ctom] = array_map(fn($u) => characters::get($u->id), $users);
        // Ana opens it, Leo and Eva come in; Tom does not.
        $huddle = teamknots::open($c, $ana->id, self::NOW);
        $this->assertTrue(teamknots::is_knot($huddle));
        $call = huddles::mine($c, $tom->id, self::NOW + 1);
        $this->assertFalse($call['joined']);
        $this->assertSame('lobby', $call['knot']['phase']);
        $this->assertSame((int) $huddle->id, (int) teamknots::open($c, $leo->id, self::NOW + 1)->id);
        huddles::join($c, $eva->id, self::NOW + 2);
        teamknots::start($this->reload($huddle), $cana, self::NOW + 3);
        $view = teamknots::view($this->reload($huddle), $cleo, self::NOW + 3);
        $this->assertSame('round', $view['phase']);
        $this->assertSame(3, $view['rounds']);
        $this->assertSame(2, $view['towin']);
        $this->assertSame((int) $ana->id, $view['hand']);
        $this->assertTrue($view['canhelp']);
        $this->assertSame('trail', $view['myverb']);
        $this->assertSame('', $view['weakness']);
        $this->assertNotSame('', $view['question']);
        // Turn 1: Leo shows the weakness, Eva cheers; Ana fails once and tries again.
        teamknots::help($this->reload($huddle), $cleo, 'trail', 0, self::NOW + 4);
        teamknots::help($this->reload($huddle), $ceva, 'cheer', 0, self::NOW + 4);
        $this->assertNotSame('', teamknots::view($this->reload($huddle), $ctom, self::NOW + 4)['weakness']);
        foreach ([[$cleo, 'trail', 0], [$cana, 'shield', 0], [$cleo, 'glance', 0]] as [$who, $verb, $round]) {
            try {
                teamknots::help($this->reload($huddle), $who, $verb, $round, self::NOW + 4);
                $this->fail('Not theirs: ' . $verb);
            } catch (\moodle_exception $e) {
                $this->assertSame('huddlenotyours', $e->errorcode);
            }
        }
        [$right, $wrong] = $this->options($huddle, $cana);
        teamknots::answer($this->reload($huddle), $cana, $wrong, 0, self::NOW + 5);
        $view = teamknots::view($this->reload($huddle), $cana, self::NOW + 5);
        $this->assertSame('again', $view['last']);
        $this->assertTrue($view['options'][$wrong]['struck']);
        $this->assertSame(0, $view['round']);
        teamknots::answer($this->reload($huddle), $cana, $right, 0, self::NOW + 6);
        $view = teamknots::view($this->reload($huddle), $cleo, self::NOW + 6);
        $this->assertEquals(
            ['round' => 1, 'cut' => 1, 'last' => 'right', 'hand' => (int) $leo->id, 'myturn' => true],
            array_intersect_key($view, array_flip(['round', 'cut', 'last', 'hand', 'myturn']))
        );
        // Turn 2: Ana cannot answer for Leo; nobody shields, and Leo blows a candle.
        try {
            teamknots::answer($this->reload($huddle), $cana, $right, 1, self::NOW + 7);
            $this->fail('Not their turn');
        } catch (\moodle_exception $e) {
            $this->assertSame('huddlenotyours', $e->errorcode);
        }
        [$right, $wrong] = $this->options($huddle, $cleo);
        teamknots::answer($this->reload($huddle), $cleo, $wrong, 1, self::NOW + 8);
        $view = teamknots::view($this->reload($huddle), $ceva, self::NOW + 8);
        $this->assertEquals(
            ['candles' => 1, 'last' => 'blown', 'hand' => (int) $eva->id],
            array_intersect_key($view, array_flip(['candles', 'last', 'hand']))
        );
        // Turn 3: Ana shields, Eva gets it right: two threads of three, won, a gem each for who played.
        teamknots::help($this->reload($huddle), $cana, 'shield', 2, self::NOW + 9);
        [$right] = $this->options($huddle, $ceva);
        $gems = (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $cana->id]);
        teamknots::answer($this->reload($huddle), $ceva, $right, 2, self::NOW + 10);
        $view = teamknots::view($this->reload($huddle), $cana, self::NOW + 10);
        $this->assertEquals(
            ['phase' => 'end', 'outcome' => 'won', 'cut' => 2, 'gem' => true],
            array_intersect_key($view, array_flip(['phase', 'outcome', 'cut', 'gem']))
        );
        $this->assertSame(['right', 'blown', 'right'], array_column($view['log'], 'result'));
        $this->assertSame($gems + teamknots::GEMS, (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $cana->id]));
        $this->assertFalse(teamknots::view($this->reload($huddle), $ctom, self::NOW + 10)['gem']);
        // Over, it does not hold the team: a new one, lost by the candles, and no second gem the same day.
        $again = teamknots::open($c, $tom->id, self::NOW + 20);
        $this->assertNotSame((int) $huddle->id, (int) $again->id);
        huddles::join($c, $ana->id, self::NOW + 21);
        teamknots::start($this->reload($again), $ctom, self::NOW + 22);
        foreach ([[$ctom, 0], [$cana, 1]] as [$who, $round]) {
            [, $wrong] = $this->options($again, $who);
            teamknots::answer($this->reload($again), $who, $wrong, $round, self::NOW + 23);
        }
        $view = teamknots::view($this->reload($again), $cana, self::NOW + 23);
        $this->assertEquals(
            ['phase' => 'end', 'outcome' => 'lost', 'candles' => 0, 'gem' => false],
            array_intersect_key($view, array_flip(['phase', 'outcome', 'candles', 'gem']))
        );
        // A scene of the team on the tablets holds it: no knot until it ends.
        $sceneid = (int) $DB->get_field(self::T . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C3-2']);
        huddles::open($c, $leo->id, $sceneid, self::NOW + 30);
        try {
            teamknots::open($c, $eva->id, self::NOW + 31);
            $this->fail('Busy');
        } catch (\moodle_exception $e) {
            $this->assertSame('teamknotbusy', $e->errorcode);
        }
    }
}
