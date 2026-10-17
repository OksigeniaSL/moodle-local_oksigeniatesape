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
     * The scenes of the pilot mission: those of the routes and the quests.
     *
     * @return array
     */
    public static function scenes_provider(): array {
        $codes = ['C1-1', 'C1-2', 'C2-1', 'C2-2', 'C3-1', 'C3-2', 'C3-3', 'C4-1', 'C4-2', 'C4-3', 'C5-1', 'C5-2', 'C5-3', 'C6-1',
            'C6-2', 'C1-3', 'C1-4', 'C2-3', 'C2-4', 'C3-4', 'C3-5', 'C4-4', 'C4-5', 'C5-4', 'C5-5', 'C6-3', 'C6-4'];
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
     * A first try with mix-ups put right on the way is taken by the server, ends as the good play (or in the partial
     * ending, where the scene counts what was right at the first try), and the mix-ups count against the objectives.
     * Quests ask for more: a play wrong at every first try can be sent back, one with some mix-ups counts.
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
        $this->assertLessThanOrEqual($good['points'], $result['points'], $code);
        if (!empty($scene['quest'])) {
            // A quest asks for more: wrong at every first try it can send back, but with a few mix-ups it counts.
            $some = (new scene_runner($scene))->run(\local_oksigeniatesape\local\autoplayer::tangled($scene, 'a', 30));
            $this->assertContains($some['outcome'], ['success', 'partial'], $code);
            return;
        }
        if ($result['outcome'] !== 'partial') {
            $this->assertSame($good['ending'], $result['ending'], $code);
        }
    }

    public function test_the_nest_sleeps_with_too_few_living_things(): void {
        $scene = autoplayer::scene('C1-2');
        $runner = new scene_runner($scene);
        $events = [
            ['n' => 'antes', 'pick' => ['buscar']],
            ['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'cristal', 'humo']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        $result = $runner->run($events);
        $this->assertSame('duerme', $result['ending']);
        $this->assertSame('retry', $result['outcome']);
        $this->assertSame('elegir', $result['retry']);
        $this->assertSame(['SV-01' => ['ok' => 3, 'total' => 5]], $result['objectives']);
        // Again from where the ending sent back: four of five, the creature hatches asleep.
        $events = [
            ['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'cristal']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        $result = (new scene_runner($scene))->run($events, 'elegir');
        $this->assertSame('dormida', $result['ending']);
        $this->assertSame('partial', $result['outcome']);
        // All five: it wakes up.
        $events = [
            ['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'rana']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        $this->assertSame('despierta', (new scene_runner($scene))->run($events, 'elegir')['ending']);
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

    public function test_classify_all_at_once_and_a_second_trip(): void {
        $scene = autoplayer::scene('C2-2');
        $events = autoplayer::play($scene);
        $node = $events[0]['n'];
        $mine = array_values(array_filter($events, fn($e) => ($e['n'] ?? '') === $node));
        $rest = array_values(array_filter($events, fn($e) => ($e['n'] ?? '') !== $node));
        $bins = array_column($scene['nodes'][$node]['bins'], 'id');
        // Everything placed at once, the first two in a wrong group; then a second trip with those two.
        $trip = $mine;
        foreach ([0, 1] as $i) {
            $trip[$i]['bin'] = array_values(array_diff($bins, [$mine[$i]['bin']]))[0];
        }
        $played = array_merge($trip, [$mine[0], $mine[1]], $rest);
        $good = (new scene_runner($scene))->run($events);
        $result = (new scene_runner($scene))->run($played);
        $this->assertSame($good['ending'], $result['ending']);
        $ok = fn($r) => array_sum(array_column($r['objectives'], 'ok'));
        $this->assertSame($ok($good) - 2, $ok($result));
        // An item that is not in the node is not a play.
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run(array_merge([['n' => $node, 'item' => 'nada', 'bin' => $bins[0]]], $events));
    }

    public function test_looking_before_deciding_well_is_evidence_of_method(): void {
        $scene = autoplayer::scene('C2-2');
        $method = $scene['method'];
        $events = autoplayer::play($scene);
        $node = $events[0]['n'];
        $mine = array_values(array_filter($events, fn($e) => ($e['n'] ?? '') === $node));
        $rest = array_values(array_filter($events, fn($e) => ($e['n'] ?? '') !== $node));
        $look = [['n' => $node, 'look' => 'musgo']];
        $plain = (new scene_runner($scene))->run($events);
        $looked = (new scene_runner($scene))->run(array_merge($look, $events));
        // It costs as a hint, and it is evidence of method when everything of the node was right at the first try.
        $this->assertSame($plain['hints'] + 1, $looked['hints']);
        $this->assertSame(($plain['objectives'][$method]['ok'] ?? 0) + 1, $looked['objectives'][$method]['ok']);
        // Looked at, and then a mistake: no evidence of method, and no evidence against it either.
        $bins = array_column($scene['nodes'][$node]['bins'], 'id');
        $trip = $mine;
        $trip[0]['bin'] = array_values(array_diff($bins, [$mine[0]['bin']]))[0];
        $missed = (new scene_runner($scene))->run(array_merge($look, $trip, [$mine[0]], $rest));
        $this->assertSame($plain['objectives'][$method] ?? null, $missed['objectives'][$method] ?? null);
        // Something with nothing to look at is not a play.
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run(array_merge([['n' => $node, 'look' => 'lombriz']], $events));
    }

    public function test_aided_plays_leave_the_hard_things_out(): void {
        $scene = autoplayer::scene('C2-2');
        $events = autoplayer::play($scene);
        $node = $events[0]['n'];
        $hard = array_column(array_filter($scene['nodes'][$node]['items'], fn($i) => !empty($i['hard'])), 'id');
        $this->assertNotEmpty($hard);
        $without = array_values(array_filter($events, fn($e) => !in_array($e['item'] ?? '', $hard, true)));
        $good = (new scene_runner($scene))->run($events);
        $aided = (new scene_runner($scene))->aided()->run($without);
        $this->assertSame($good['ending'], $aided['ending']);
        $total = fn($r) => array_sum(array_column($r['objectives'], 'total'));
        $this->assertSame($total($good) - count($hard), $total($aided));
        // Who is on the first step plays with aids what was not written for their step.
        $this->assertTrue(scenes::aided((object) ['step' => 1], (object) ['step' => 2]));
        $this->assertTrue(scenes::aided((object) ['step' => 1], (object) ['step' => 0]));
        $this->assertFalse(scenes::aided((object) ['step' => 1], (object) ['step' => 1]));
        $this->assertFalse(scenes::aided((object) ['step' => 2], (object) ['step' => 2]));
        // Without the aids, the hard things are expected.
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run($without);
    }

    /**
     * A small scene: a question that may leave a flag, a branch on it, and a sorting with hints.
     *
     * @return array
     */
    private static function memory_scene(): array {
        return [
            'id' => 'T-1', 'start' => 'q', 'nodes' => [
                'q' => ['type' => 'choice', 'prompt' => 'Who goes?', 'next' => 'b', 'options' => [
                    ['id' => 'brave', 'label' => 'Me', 'correct' => true, 'points' => 2, 'effects' => [['flag' => 'brave']]],
                    ['id' => 'shy', 'label' => 'Not me', 'correct' => true, 'points' => 1],
                    ['id' => 'old', 'label' => 'As last time', 'requires' => ['flag' => 'camped']],
                    ['id' => 'trail', 'label' => 'The trail', 'requires' => ['role' => 'scout']],
                ]],
                'b' => ['type' => 'branch', 'rules' => [['if' => ['flag' => 'brave'], 'next' => 'sort']], 'else' => 'quiet'],
                'quiet' => ['type' => 'ending', 'outcome' => 'partial', 'effects' => [['saber' => 5]]],
                'sort' => ['type' => 'classify', 'prompt' => 'Sort', 'hints' => ['One', 'Two'], 'next' => 'end',
                    'bins' => [['id' => 'x', 'label' => 'X'], ['id' => 'y', 'label' => 'Y']],
                    'items' => [['id' => 'i', 'label' => 'I', 'bin' => 'x', 'objective' => 'O']]],
                'end' => ['type' => 'ending', 'outcome' => 'success', 'effects' => [['saber' => 10]]],
            ],
        ];
    }

    public function test_flags_open_options_and_change_the_way(): void {
        $scene = self::memory_scene();
        $brave = (new scene_runner($scene))->run([['n' => 'q', 'pick' => ['brave']], ['n' => 'sort', 'item' => 'i', 'bin' => 'x']]);
        $this->assertSame('end', $brave['ending']);
        $this->assertSame('quiet', (new scene_runner($scene))->run([['n' => 'q', 'pick' => ['shy']]])['ending']);
        // What another scene left opens an option; the own role, another.
        $this->assertSame('quiet', (new scene_runner($scene, [], [], ['camped']))->run([['n' => 'q', 'pick' => ['old']]])['ending']);
        $this->assertSame('quiet', (new scene_runner($scene, [], [], [], 'scout'))->run([['n' => 'q', 'pick' => ['trail']]])['ending']);
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene, [], ['scout']))->run([['n' => 'q', 'pick' => ['trail']]]);
    }

    public function test_a_wrong_guess_puts_the_owl_to_sleep_and_the_questions_do_not_score(): void {
        $scene = autoplayer::scene('C3-2');
        $events = autoplayer::play($scene);
        $good = (new scene_runner($scene))->run($events);
        $this->assertSame('bien', $good['ending']);
        // Asking is not scored: the guess and why it is right are.
        $this->assertSame(['ok' => 1, 'total' => 1], $good['objectives']['SV-04']);
        $this->assertSame(['ok' => 3, 'total' => 3], $good['objectives']['SV-07']);
        // A wrong guess does not lead back to the guess: the owl falls asleep, and the questions start again.
        $guess = array_search('adivinar', array_column($events, 'n'), true);
        $wrong = array_slice($events, 0, $guess);
        $wrong[] = ['n' => 'adivinar', 'pick' => ['tortuga']];
        $result = (new scene_runner($scene))->run($wrong);
        $this->assertSame('dormido', $result['ending']);
        $this->assertSame('retry', $result['outcome']);
    }

    public function test_a_dilemma_is_a_decision_and_not_evidence_of_an_objective(): void {
        $scene = [
            'id' => 'T-2', 'start' => 'd', 'nodes' => [
                'd' => ['type' => 'choice', 'prompt' => 'The frog?', 'dilemma' => true, 'objective' => 'O', 'next' => 'q',
                    'options' => [
                        ['id' => 'nest', 'label' => 'To the nest', 'points' => 1, 'effects' => [['flag' => 'frog-nest']]],
                        ['id' => 'pond', 'label' => 'To the pond', 'points' => 1],
                    ]],
                'q' => ['type' => 'choice', 'prompt' => 'Alive?', 'objective' => 'O', 'next' => 'end', 'options' => [
                    ['id' => 'yes', 'label' => 'Yes', 'correct' => true, 'points' => 2],
                    ['id' => 'no', 'label' => 'No', 'correct' => false],
                ]],
                'end' => ['type' => 'ending', 'outcome' => 'success'],
            ],
        ];
        $result = (new scene_runner($scene))->run([['n' => 'd', 'pick' => ['pond']], ['n' => 'q', 'pick' => ['yes']]]);
        // Only the question counts for the objective; the dilemma, whatever was decided, does not.
        $this->assertSame(['O' => ['ok' => 1, 'total' => 1]], $result['objectives']);
        $this->assertSame('end', $result['ending']);
    }

    public function test_hints_are_counted_once_each(): void {
        $scene = self::memory_scene();
        $events = [['n' => 'q', 'pick' => ['brave']], ['n' => 'sort', 'hint' => 0], ['n' => 'sort', 'hint' => 1],
            ['n' => 'sort', 'item' => 'i', 'bin' => 'x']];
        $this->assertSame(2, (new scene_runner($scene))->run($events)['hints']);
        $twice = [['n' => 'q', 'pick' => ['brave']], ['n' => 'sort', 'hint' => 0], ['n' => 'sort', 'hint' => 0],
            ['n' => 'sort', 'item' => 'i', 'bin' => 'x']];
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run($twice);
    }

    public function test_the_shield_stops_the_points_of_the_first_mistake(): void {
        $scene = self::memory_scene();
        $events = [['n' => 'q', 'pick' => ['brave']], ['n' => 'sort', 'item' => 'i', 'bin' => 'y'],
            ['n' => 'sort', 'item' => 'i', 'bin' => 'x']];
        $plain = (new scene_runner($scene))->run($events);
        $shielded = (new scene_runner($scene, [], [], [], 'protector'))->run($events);
        $this->assertSame($plain['points'] + 1, $shielded['points']);
        $this->assertTrue($shielded['shielded']);
        // The teacher still sees the mistake.
        $this->assertSame($plain['objectives'], $shielded['objectives']);
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
            // From the question of the nest, where its retry ending sends back.
            'unknown option' => [[['n' => 'elegir', 'pick' => ['dragon', 'seta', 'lombriz', 'musgo', 'hormiga']]], 'elegir'],
            'too few options' => [[['n' => 'elegir', 'pick' => ['seta']]], 'elegir'],
            'the same option twice' => [[['n' => 'elegir', 'pick' => ['seta', 'seta', 'lombriz', 'musgo', 'hormiga']]], 'elegir'],
            'events after the end' => [[['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'semilla']],
                ['n' => 'elegir', 'pick' => ['seta']]], 'elegir'],
            'the option of another role' => [[['n' => 'antes', 'pick' => ['guardia']], ['n' => 'elegir', 'pick' => ['seta',
                'lombriz', 'musgo', 'hormiga', 'semilla']]], null],
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
