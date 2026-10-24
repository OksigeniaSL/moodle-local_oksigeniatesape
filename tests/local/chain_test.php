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
 * Tests for Eslabón: the chains from the sun and the questions, counted again by the server.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minigames
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minigames::class)]
final class chain_test extends \advanced_testcase {
    /**
     * What a student does in each round: right, or a chain out of order or a wrong answer.
     *
     * @param array $game
     * @param string[] $wrong Ids of the rounds to get wrong.
     * @return array
     */
    private function events(array $game, array $wrong = []): array {
        $events = [];
        foreach ($game['rounds'] as $r) {
            $bad = in_array($r['id'], $wrong, true);
            if ($r['kind'] === 'question') {
                $other = array_values(array_diff($r['options'], [$r['answer']]))[0];
                $events[] = ['r' => $r['id'], 'g' => $bad ? $other : $r['answer']];
            } else {
                $events[] = ['r' => $r['id'], 'o' => $bad ? array_reverse($r['order']) : $r['order']];
            }
        }
        return $events;
    }

    public function test_the_chains_go_from_the_sun(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        characters::create($user->id, 'scout', 'f');
        $mission = missions::get($c);
        $game = minigames::config($mission, 'eslabonlago', 1);
        $this->assertSame('chain', $game['type']);
        $this->assertSame(['sol', 'alga', 'pezrio', 'martinpescador'], $game['rounds'][0]['order']);
        $this->assertEqualsCanonicalizing($game['rounds'][0]['order'], array_column($game['rounds'][0]['cards'], 'id'));
        $this->assertSame('sol', $game['rounds'][0]['cards'][array_search('sol', array_column($game['rounds'][0]['cards'], 'id'))]['label']);
        // A chain with a link that is not its own: not a play.
        try {
            minigames::play($c, $user->id, 'eslabonlago', [['r' => 'lago', 'o' => ['sol', 'alga', 'pezrio', 'garza']]]);
            $this->fail('A link of another chain');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidgameplay', $e->errorcode);
        }
        // Three rounds wrong: loose. One wrong: joined, and the gems of the first time.
        $this->assertFalse(minigames::play($c, $user->id, 'eslabonlago', $this->events($game, ['lago', 'prado', 'restos']))['success']);
        $result = minigames::play($c, $user->id, 'eslabonlago', $this->events($game, ['arranca']));
        $this->assertTrue($result['success']);
        $this->assertSame([4, 5], [$result['caught'], $result['gems']]);
        // The teacher sees the chains put wrong, link by link.
        $chain = array_values(array_filter(minigames::results($mission), fn($g) => $g['code'] === 'eslabonlago'))[0];
        $this->assertNotEmpty(array_filter(array_column($chain['confusions'], 'text'), fn($x) => str_contains($x, ' → ')));
    }
}
