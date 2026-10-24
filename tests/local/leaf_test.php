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
 * Tests for the leaf factory: recipes of light, water and air, and questions, counted again by the server.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minigames
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minigames::class)]
final class leaf_test extends \advanced_testcase {
    /**
     * What a student does in each round: right, or a wrong recipe or answer.
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
                $n = $r['target'];
                $events[] = $bad ? ['r' => $r['id'], 'l' => $r['caps']['light'], 'w' => 1, 'a' => 1] :
                    ['r' => $r['id'], 'l' => $n, 'w' => $n, 'a' => $n];
            }
        }
        return $events;
    }

    public function test_the_leaf_makes_as_much_as_the_least_lets_it(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        characters::create($user->id, 'scout', 'f');
        $mission = missions::get($c);
        $game = minigames::config($mission, 'fabricahoja', 1);
        $this->assertSame('leaf', $game['type']);
        $this->assertSame(['recipe', 'recipe', 'question', 'question', 'recipe'], array_column($game['rounds'], 'kind'));
        $this->assertSame(2, $game['rounds'][1]['caps']['light']);
        // A recipe with the keys out of what the round lets: not a play.
        try {
            minigames::play($c, $user->id, 'fabricahoja', [['r' => 'nube', 'l' => 3, 'w' => 3, 'a' => 3]]);
            $this->fail('The cloud lets two of light');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidgameplay', $e->errorcode);
        }
        // Three rounds wrong: stuck. One wrong: it works, and the gems of the first time.
        $this->assertFalse(minigames::play($c, $user->id, 'fabricahoja', $this->events($game, ['receta', 'noche', 'tarro']))['success']);
        $result = minigames::play($c, $user->id, 'fabricahoja', $this->events($game, ['nube']));
        $this->assertTrue($result['success']);
        $this->assertSame([4, 5], [$result['caught'], $result['gems']]);
        // The teacher sees what the class does wrong, round by round.
        $results = minigames::results($mission);
        $leaf = array_values(array_filter($results, fn($g) => $g['code'] === 'fabricahoja'))[0];
        $texts = array_column($leaf['confusions'], 'text');
        $recipe = fn($round, $light) => get_string('minigame_leaf_wrong', 'local_oksigeniatesape', (object) ['round' => $round,
            'what' => get_string('minigame_leaf_recipe', 'local_oksigeniatesape', (object) ['light' => $light, 'water' => 1,
            'air' => 1])]);
        $this->assertNotEmpty(array_intersect([$recipe('La receta', 4), $recipe('La nube del Embrollo', 2),
            $recipe('El tarro lleno', 4)], $texts));
    }
}
