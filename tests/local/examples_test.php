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
 * The examples of other subjects (missions/examples) play with the engines of the games, with the same data.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minigames
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minigames::class)]
final class examples_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const PREFIX = 'local_oksigeniatesape_';

    /**
     * A class whose mission has an example in it, and a student.
     *
     * @param string $file The file of the example.
     * @return array The course, the student, and the mission.
     */
    private function class_with(string $file): array {
        global $CFG, $DB;
        $example = json_decode(file_get_contents($CFG->dirroot . '/local/oksigeniatesape/missions/examples/' . $file), true);
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $definition = json_decode((string) $DB->get_field(self::PREFIX . 'mission', 'definition', ['id' => $mission->id]), true);
        $definition['games'] = array_merge($definition['games'], $example['games']);
        $definition['things'] = array_merge($definition['things'] ?? [], $example['things'] ?? []);
        $DB->set_field(self::PREFIX . 'mission', 'definition', json_encode($definition), ['id' => $mission->id]);
        missions::forget();
        $user = $generator->create_and_enrol($course, 'student');
        characters::create($user->id, 'scout', 'f');
        engine::member(characters::get($user->id), $course->id);
        return [$course, $user, missions::get($course->id)];
    }

    public function test_the_nest_with_primes_and_composites(): void {
        $this->resetAfterTest();
        [$course, $user, $mission] = $this->class_with('nido-primos.json');
        $member = engine::member(characters::get($user->id), $course->id);
        $game = minigames::config($mission, 'nidoprimos', (int) $member->step);
        $nets = array_unique(array_column($game['animals'], 'net'));
        sort($nets);
        $this->assertSame(['composite', 'prime'], $nets);
        $this->assertGreaterThanOrEqual($game['goal'], count($game['animals']));
        $events = array_map(fn($a) => ['a' => $a['id'], 'net' => $a['net']], $game['animals']);
        $this->assertTrue(minigames::play($course->id, $user->id, 'nidoprimos', $events)['success']);
        // Each number, in the net of what it is: a wrong one slips.
        $wrong = array_map(fn($a) => ['a' => $a['id'], 'net' => $a['net'] === 'prime' ? 'composite' : 'prime'], $game['animals']);
        $this->assertFalse(minigames::play($course->id, $user->id, 'nidoprimos', $wrong)['success']);
    }

    public function test_the_terrarium_with_words(): void {
        $this->resetAfterTest();
        [$course, $user, $mission] = $this->class_with('terrario-palabras.json');
        $member = engine::member(characters::get($user->id), $course->id);
        $game = minigames::config($mission, 'terrariopalabras', (int) $member->step);
        $this->assertSame('terrarium', $game['type']);
        $alive = array_column($game['things'], 'alive', 'id');
        $this->assertTrue($alive['cantar']);
        $this->assertFalse($alive['mesa']);
        // The verbs go in; a week: they change, and that is the goal.
        $events = [];
        foreach (array_slice(array_keys(array_filter($alive)), 0, $game['goal']) as $id) {
            $events[] = ['i' => $id, 'in' => 1];
        }
        $events[] = ['w' => 1];
        $this->assertTrue(minigames::play($course->id, $user->id, 'terrariopalabras', $events)['success']);
    }
}
