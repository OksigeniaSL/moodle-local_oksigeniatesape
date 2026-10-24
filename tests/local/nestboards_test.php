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
 * Tests for Defensa del nido on the class screen, with the tablets of a team as controls.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\nestboards
 */
#[\PHPUnit\Framework\Attributes\CoversClass(nestboards::class)]
final class nestboards_test extends \advanced_testcase {
    /** @var int A fixed moment, at midday. */
    private const NOW = 1800014400;

    /**
     * The net the animal of the turn needs, and who holds it.
     *
     * @param \stdClass $live
     * @return array [net, charid holding it, a wrong net, charid holding that]
     */
    private function turn(\stdClass $live): array {
        global $DB;
        $live = $DB->get_record('local_oksigeniatesape_live', ['id' => $live->id]);
        $n = json_decode($live->state, true)['nest'];
        $game = minigames::config(missions::get((int) $live->courseid), nestboards::GAME, 1);
        $need = array_column($game['animals'], 'net', 'id')[$n['animals'][$n['index']]];
        $holder = 0;
        $wrong = '';
        $wrongholder = 0;
        foreach ($n['hands'] as $charid => $hand) {
            foreach ($hand as $net) {
                if ($net === $need) {
                    $holder = (int) $charid;
                } else if (!$wrong) {
                    [$wrong, $wrongholder] = [$net, (int) $charid];
                }
            }
        }
        return [$need, $holder, $wrong, $wrongholder];
    }

    public function test_a_team_defends_the_nest_with_a_net_each(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es', self::NOW);
        $members = [];
        foreach (['Garza' => 2, 'Jaguar' => 1] as $name => $count) {
            $group = $gen->create_group(['courseid' => $c, 'name' => $name]);
            $groups[$name] = $group;
            for ($i = 0; $i < $count; $i++) {
                $user = $gen->create_and_enrol($course, 'student');
                $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
                $members[$name][] = [$user, engine::member(characters::create($user->id, 'scout', 'f'), $c, self::NOW)];
            }
        }
        $live = nestboards::start($c, (int) $groups['Garza']->id, self::NOW);
        // Only the tablets of the team see it.
        $this->assertNull(nestboards::device($live, $members['Jaguar'][0][1], self::NOW));
        $this->assertSame('lobby', nestboards::device($live, $members['Garza'][0][1], self::NOW)['phase']);
        foreach ($members['Garza'] as [$user, $member]) {
            live::join($live, $member, $user->id, self::NOW + 1);
        }
        $live = nestboards::go($live, self::NOW + 5);
        $board = nestboards::board($live, self::NOW + 5);
        $this->assertSame(['play', nestboards::ANIMALS, nestboards::EGGS], [$board['phase'], $board['total'], $board['eggs']]);
        // Every net in some hand, and the hands between the two.
        $this->assertSame([], array_filter(array_column($board['nets'], 'who'), fn($w) => $w === ''));
        $hands = array_map(fn($m) => count(nestboards::device($live, $m[1], self::NOW + 5)['nets']), $members['Garza']);
        $this->assertSame(count($board['nets']), array_sum($hands));
        $chars = array_combine(array_map(fn($m) => (int) $m[1]->charid, $members['Garza']), array_column($members['Garza'], 1));
        // A net that is not in the hand, no.
        [$need, $holder] = $this->turn($live);
        $other = array_values(array_diff(array_keys($chars), [$holder]))[0];
        $hand = array_column(nestboards::device($live, $chars[$other], self::NOW + 6)['nets'], 'id');
        if (!in_array($need, $hand, true)) {
            try {
                nestboards::throw($live, $chars[$other], $need, 0, self::NOW + 6);
                $this->fail('Not their net');
            } catch (\moodle_exception $e) {
                $this->assertSame('nestnotnow', $e->errorcode);
            }
        }
        // First animal: the right net. Second: a wrong one. Third: nobody in time. The rest: right.
        $live = nestboards::throw($live, $chars[$holder], $need, 0, self::NOW + 7);
        $this->assertTrue(nestboards::board($live, self::NOW + 7)['last']['right']);
        [, , $wrong, $wrongholder] = $this->turn($live);
        $live = nestboards::throw($live, $chars[$wrongholder], $wrong, 1, self::NOW + 8);
        $board = nestboards::board($live, self::NOW + 8);
        $this->assertSame([false, nestboards::EGGS - 1], [$board['last']['right'], $board['eggs']]);
        $this->assertNotSame('', $board['last']['hint']);
        $live = nestboards::tick($live, self::NOW + 8 + nestboards::SECS);
        $board = nestboards::board($live, self::NOW + 30);
        $this->assertSame([3, nestboards::EGGS - 2, ''], [$board['index'], $board['eggs'], $board['last']['net']]);
        $gems = (int) $DB->get_field('local_oksigeniatesape_char', 'gems', ['id' => $holder]);
        for ($i = 3; $i < nestboards::ANIMALS; $i++) {
            [$need, $holder] = $this->turn($live);
            $live = nestboards::throw($live, $chars[$holder], $need, $i, self::NOW + 40 + $i);
        }
        $board = nestboards::board($live, self::NOW + 60);
        $this->assertSame(['end', 'won', nestboards::ANIMALS - 2], [$board['phase'], $board['outcome'], $board['caught']]);
        $this->assertTrue(nestboards::device($live, $members['Garza'][0][1], self::NOW + 60)['gem']);
        $this->assertGreaterThan(0, (int) $DB->get_field('local_oksigeniatesape_live', 'timeend', ['id' => $live->id]));
        // Another one the same day: no second gem.
        $live = nestboards::start($c, (int) $groups['Garza']->id, self::NOW + 100);
        foreach ($members['Garza'] as [$user, $member]) {
            live::join($live, $member, $user->id, self::NOW + 101);
        }
        $live = nestboards::go($live, self::NOW + 105);
        for ($i = 0; $i < nestboards::ANIMALS; $i++) {
            [$need, $holder] = $this->turn($live);
            $live = nestboards::throw($live, $chars[$holder], $need, $i, self::NOW + 110 + $i);
        }
        $this->assertFalse(nestboards::device($live, $members['Garza'][0][1], self::NOW + 200)['gem']);
        $this->assertSame(1, $DB->count_records('local_oksigeniatesape_found', ['charid' => $members['Garza'][0][1]->charid,
            'kind' => 'nestboard']));
    }
}
