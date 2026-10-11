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

use local_oksigeniatesape\autoplayer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/autoplayer.php');

/**
 * Tests for playing the scenes again on the server.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\scene_runner
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scene_runner::class)]
final class scene_runner_test extends \advanced_testcase {
    /**
     * The nine core scenes of the pilot mission.
     *
     * @return array
     */
    public static function scenes_provider(): array {
        $codes = ['C1-1', 'C1-2', 'C2-1', 'C2-2', 'C3-1', 'C3-2', 'C3-3', 'C4-1', 'C4-2', 'C4-3', 'C5-1', 'C5-2', 'C5-3', 'C6-1',
            'C6-2'];
        return array_combine($codes, array_map(fn($c) => [$c], $codes));
    }

    /**
     * Every scene of the mission can be played to a good ending.
     *
     * @param string $code
     * @dataProvider scenes_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenes_provider')]
    public function test_good_play_of_every_scene(string $code): void {
        $scene = autoplayer::scene($code);
        $result = (new scene_runner($scene))->run(autoplayer::play($scene));
        $this->assertSame('success', $result['outcome'], $code);
        $this->assertGreaterThan(0, $result['points']);
        $this->assertNotEmpty($result['effects']);
        foreach ($result['objectives'] as $objective => $o) {
            $this->assertSame($o['total'], $o['ok'], "$code $objective");
        }
    }

    /**
     * A first try with mix-ups put right on the way is taken by the server, ends as the good play, and the mix-ups
     * count against the objectives.
     *
     * @param string $code
     * @dataProvider scenes_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('scenes_provider')]
    public function test_tangled_play_of_every_scene(string $code): void {
        $scene = autoplayer::scene($code);
        $good = (new scene_runner($scene))->run(autoplayer::play($scene));
        $events = \local_oksigeniatesape\local\autoplayer::tangled($scene, 'seed', 100);
        $result = (new scene_runner($scene))->run($events);
        $this->assertSame($good['ending'], $result['ending'], $code);
        $this->assertLessThanOrEqual($good['points'], $result['points'], $code);
    }

    public function test_the_nest_sleeps_with_too_few_living_things(): void {
        $scene = autoplayer::scene('C1-2');
        $runner = new scene_runner($scene);
        $events = [['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'cristal', 'humo']]];
        $result = $runner->run($events);
        $this->assertSame('duerme', $result['ending']);
        $this->assertSame('retry', $result['outcome']);
        $this->assertSame('elegir', $result['retry']);
        $this->assertSame(['SV-01' => ['ok' => 3, 'total' => 5]], $result['objectives']);
        // Again from where the ending sent back.
        $events = [['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'cristal']]];
        $result = (new scene_runner($scene))->run($events, 'elegir');
        $this->assertSame('despierta', $result['ending']);
    }

    public function test_wrong_answers_lead_back_to_the_question(): void {
        $scene = autoplayer::scene('C6-2');
        $events = autoplayer::play($scene);
        // Two wrong answers to the first claim of the Embrollo before the good one.
        array_unshift($events, ['n' => 'e1', 'pick' => ['mal']], ['n' => 'e1', 'pick' => ['medio']]);
        $result = (new scene_runner($scene))->run($events);
        $this->assertSame('fin', $result['ending']);
        $this->assertSame(3, count(array_keys($result['path'], 'e1', true)));
    }

    public function test_classify_counts_the_first_try(): void {
        $scene = autoplayer::scene('C2-2');
        $events = autoplayer::play($scene);
        $first = $events[0];
        $wrong = array_values(array_filter(
            array_column($scene['nodes'][$first['n']]['bins'], 'id'),
            fn($b) => $b !== $first['bin']
        ))[0];
        array_splice($events, 0, 0, [['n' => $first['n'], 'item' => $first['item'], 'bin' => $wrong]]);
        $good = (new scene_runner($scene))->run(autoplayer::play($scene));
        $result = (new scene_runner($scene))->run($events);
        $this->assertSame($good['points'] - 1, $result['points']);
        $objective = array_key_first($result['objectives']);
        $this->assertSame($good['objectives'][$objective]['ok'] - 1, $result['objectives'][$objective]['ok']);
    }

    public function test_classify_takes_the_same_wrong_group_tapped_again(): void {
        $scene = autoplayer::scene('C2-2');
        $events = autoplayer::play($scene);
        $first = $events[0];
        $wrong = array_values(array_filter(
            array_column($scene['nodes'][$first['n']]['bins'], 'id'),
            fn($b) => $b !== $first['bin']
        ))[0];
        // A child taps the same wrong group six times before the right one: more taps than there are groups.
        $taps = array_fill(0, 6, ['n' => $first['n'], 'item' => $first['item'], 'bin' => $wrong]);
        array_splice($events, 0, 0, $taps);
        $good = (new scene_runner($scene))->run(autoplayer::play($scene));
        $result = (new scene_runner($scene))->run($events);
        $this->assertSame($good['points'] - 1, $result['points']);
        $this->assertSame($good['ending'], $result['ending']);
    }

    public function test_order_with_a_stuck_step(): void {
        $scene = autoplayer::scene('C4-3');
        $events = autoplayer::play($scene);
        array_splice($events, 0, 0, [['n' => $events[0]['n'], 'step' => 'hoja']]);
        $result = (new scene_runner($scene))->run($events);
        $good = (new scene_runner($scene))->run(autoplayer::play($scene));
        $this->assertSame($good['points'] - 3, $result['points']);
    }

    public function test_extra_question(): void {
        $scene = autoplayer::scene('C3-2');
        $events = autoplayer::play($scene);
        $p3 = array_values(array_filter($events, fn($e) => $e['n'] === 'p3'));
        // Asking about the bones won a second answer to the last question.
        $this->assertCount(2, $p3);
        $without = $events;
        unset($without[max(array_keys(array_column($events, 'n'), 'p3', true))]);
        $without = array_values($without);
        $first = array_search('p1', array_column($without, 'n'), true);
        $without[$first] = ['n' => 'p1', 'pick' => ['pelo']];
        $result = (new scene_runner($scene))->run($without);
        $this->assertSame('bien', $result['ending']);
    }

    public function test_items_open_options(): void {
        $scene = autoplayer::scene('C5-2');
        $events = autoplayer::play($scene, ['lantern']);
        $this->assertContains(['n' => 'farol', 'pick' => ['farol']], $events);
        $result = (new scene_runner($scene, ['lantern']))->run($events);
        $this->assertContains(['gems' => 1], $result['inline']);
        // Without the lantern that option is not there.
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run($events);
    }

    /**
     * What does not fit the scene.
     *
     * @return array
     */
    public static function bad_plays_provider(): array {
        return [
            'event of another node' => [[['n' => 'otro', 'pick' => ['seta']]], null],
            'unknown option' => [[['n' => 'elegir', 'pick' => ['dragon', 'seta', 'lombriz', 'musgo', 'hormiga']]], null],
            'too few options' => [[['n' => 'elegir', 'pick' => ['seta']]], null],
            'the same option twice' => [[['n' => 'elegir', 'pick' => ['seta', 'seta', 'lombriz', 'musgo', 'hormiga']]], null],
            'events after the end' => [[['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'semilla']],
                ['n' => 'elegir', 'pick' => ['seta']]], null],
            'not a starting node' => [[], 'resultado'],
            'nothing to play' => [[], null],
        ];
    }

    /**
     * Plays that do not fit the scene are refused.
     *
     * @param array $events
     * @param string|null $from
     * @dataProvider bad_plays_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bad_plays_provider')]
    public function test_bad_plays(array $events, ?string $from): void {
        $this->expectException(\moodle_exception::class);
        (new scene_runner(autoplayer::scene('C1-2')))->run($events, $from);
    }
}
