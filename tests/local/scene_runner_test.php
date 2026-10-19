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
            'C6-2', 'C1-3', 'C1-4', 'C2-3', 'C2-4', 'C3-4', 'C3-5', 'C4-4', 'C4-5', 'C5-4', 'C5-5', 'C6-3', 'C6-4', 'C1-5'];
        return array_combine($codes, array_map(fn($c) => [$c], $codes));
    }

    /**
     * Only right and total of each objective, without how the answers came.
     *
     * @param array $objectives
     * @return array
     */
    private static function scored(array $objectives): array {
        return array_map(fn($o) => array_intersect_key($o, ['ok' => 1, 'total' => 1]), $objectives);
    }

    /**
     * Each answer says how it came: right alone at the first try, right with help (here, the lens), right after seeing
     * the consequence of a mistake, and the same mistake again.
     */
    public function test_each_answer_says_how_it_came(): void {
        $scene = ['id' => 'T', 'start' => 'q', 'nodes' => [
            'q' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-02', 'options' => [
                ['id' => 'a', 'label' => 'A', 'correct' => true],
                ['id' => 'b', 'label' => 'B', 'correct' => false, 'next' => 'q'],
            ], 'next' => 'r'],
            'r' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-02', 'options' => [
                ['id' => 'x', 'label' => 'X', 'correct' => true, 'look' => 'Seen closely'],
                ['id' => 'y', 'label' => 'Y', 'correct' => false],
            ], 'next' => 's'],
            's' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-02', 'options' => [
                ['id' => 'm', 'label' => 'M', 'correct' => true],
                ['id' => 'n', 'label' => 'N', 'correct' => false],
            ], 'next' => 'k'],
            'k' => ['type' => 'classify', 'prompt' => '?', 'objective' => 'SV-03', 'bins' => [['id' => 'p', 'label' => 'P'],
                ['id' => 'o', 'label' => 'O']], 'items' => [['id' => 'i1', 'label' => 'I1', 'bin' => 'p'],
                ['id' => 'i2', 'label' => 'I2', 'bin' => 'o']], 'next' => 'fin'],
            'fin' => ['type' => 'ending', 'outcome' => 'success', 'effects' => []],
        ]];
        $events = [
            ['n' => 'q', 'pick' => ['b']], ['n' => 'q', 'pick' => ['b']], ['n' => 'q', 'pick' => ['a']],
            ['n' => 'r', 'look' => 'x'], ['n' => 'r', 'pick' => ['x']],
            ['n' => 's', 'pick' => ['m']],
            ['n' => 'k', 'item' => 'i1', 'bin' => 'p'], ['n' => 'k', 'item' => 'i2', 'bin' => 'p'], ['n' => 'k', 'item' => 'i2', 'bin' => 'o'],
        ];
        $objectives = (new scene_runner($scene))->run($events)['objectives'];
        $this->assertEquals(['ok' => 3, 'total' => 5, 'twice' => 1, 'after' => 1, 'helped' => 1, 'first' => 1], $objectives['SV-02']);
        $this->assertEquals(['ok' => 1, 'total' => 2, 'first' => 1, 'after' => 1], $objectives['SV-03']);
    }

    /**
     * The creature sniffs a choice: it strikes out the option marked as clearly not it, once a play, with the creature
     * hatched; the evidence says the answer came after it.
     */
    public function test_the_creature_sniffs_out_one_option_once_a_play(): void {
        $scene = ['id' => 'T', 'start' => 'q', 'nodes' => [
            'q' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-01', 'options' => [
                ['id' => 'a', 'label' => 'A', 'correct' => true, 'points' => 2],
                ['id' => 'b', 'label' => 'B', 'correct' => false],
                ['id' => 'c', 'label' => 'C', 'correct' => false, 'obvious' => true],
            ], 'next' => 'r'],
            'r' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-01', 'options' => [
                ['id' => 'x', 'label' => 'X', 'correct' => true, 'points' => 2],
                ['id' => 'y', 'label' => 'Y', 'correct' => false],
                ['id' => 'z', 'label' => 'Z', 'correct' => false],
            ], 'next' => 'fin'],
            'fin' => ['type' => 'ending', 'outcome' => 'success', 'effects' => [['gems' => 1]]],
        ]];
        $this->assertSame('c', scene_runner::sniffs($scene['nodes']['q']));
        // Without an obvious one, the last that is not right.
        $this->assertSame('z', scene_runner::sniffs($scene['nodes']['r']));
        $sniffed = [['n' => 'q', 'sniff' => 1], ['n' => 'q', 'pick' => ['a']], ['n' => 'r', 'pick' => ['x']]];
        $result = (new scene_runner($scene, ['creature']))->run($sniffed);
        $this->assertSame(1, $result['sniffed']);
        $this->assertSame(0, $result['hints']);
        // The first answer had the creature's help; the second was alone.
        $this->assertEquals(['ok' => 2, 'total' => 2, 'sniffed' => 1, 'helped' => 1, 'first' => 1], $result['objectives']['SV-01']);
        $fails = [
            'without the creature' => [[], $sniffed],
            'twice in a play' => [['creature'], [['n' => 'q', 'sniff' => 1], ['n' => 'q', 'pick' => ['a']],
                ['n' => 'r', 'sniff' => 1], ['n' => 'r', 'pick' => ['x']]]],
            'the option it struck out' => [['creature'], [['n' => 'q', 'sniff' => 1], ['n' => 'q', 'pick' => ['c']],
                ['n' => 'r', 'pick' => ['x']]]],
        ];
        foreach ($fails as $why => [$items, $events]) {
            try {
                (new scene_runner($scene, $items))->run($events);
                $this->fail('Sniffed ' . $why);
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidscenepath', $e->errorcode, $why);
            }
        }
        // Not where the scene does not let it, nor in a decision without a right answer, nor with two options.
        $this->expectException(\moodle_exception::class);
        (new scene_runner(['sniff' => false] + $scene, ['creature']))->run($sniffed);
    }

    /**
     * Where the scent of the creature goes wrong, it points at an option that is not right instead of striking one out,
     * and the option can still be chosen.
     */
    public function test_the_scent_of_the_creature_can_go_wrong(): void {
        $scene = ['id' => 'T', 'start' => 'q', 'nodes' => [
            'q' => ['type' => 'choice', 'prompt' => '?', 'objective' => 'SV-01', 'select' => ['min' => 2, 'max' => 2],
                'sniff_wrong' => ['option' => 'pluma', 'say' => 'Huele a pájaro'], 'options' => [
                ['id' => 'seta', 'label' => 'Seta', 'correct' => true],
                ['id' => 'musgo', 'label' => 'Musgo', 'correct' => true],
                ['id' => 'pluma', 'label' => 'Pluma', 'correct' => false],
            ], 'next' => 'fin'],
            'fin' => ['type' => 'ending', 'outcome' => 'success', 'effects' => []],
        ]];
        $this->assertSame('pluma', scene_runner::sniffs($scene['nodes']['q']));
        $result = (new scene_runner($scene, ['creature']))->run([['n' => 'q', 'sniff' => 1], ['n' => 'q', 'pick' => ['seta', 'pluma']]]);
        $this->assertSame(1, $result['sniffed']);
        $this->assertEquals(['ok' => 1, 'total' => 2, 'sniffed' => 2, 'helped' => 1], $result['objectives']['SV-01']);
    }

    /**
     * A branch at random: the tablet draws the way and says which; it has to be one of them.
     */
    public function test_a_branch_at_random_takes_the_way_the_tablet_drew(): void {
        $scene = ['id' => 'T', 'start' => 'b', 'nodes' => [
            'b' => ['type' => 'branch', 'pick' => ['x', 'y']],
            'x' => ['type' => 'ending', 'outcome' => 'success', 'effects' => [['gems' => 1]]],
            'y' => ['type' => 'ending', 'outcome' => 'success', 'effects' => [['gems' => 2]]],
        ]];
        $this->assertSame(['b', 'y'], (new scene_runner($scene))->run([['n' => 'b', 'to' => 'y']])['path']);
        $this->expectException(\moodle_exception::class);
        (new scene_runner($scene))->run([['n' => 'b', 'to' => 'z']]);
    }

    public function test_the_creature_does_not_sniff_a_dilemma_or_two_options(): void {
        $dilemma = ['type' => 'choice', 'dilemma' => true, 'options' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']]];
        $this->assertNull(scene_runner::sniffs($dilemma));
        $two = ['type' => 'choice', 'options' => [['id' => 'a', 'correct' => true], ['id' => 'b', 'correct' => false]]];
        $this->assertNull(scene_runner::sniffs($two));
        $many = ['type' => 'choice', 'select' => ['max' => 2], 'options' => [['id' => 'a', 'correct' => true],
            ['id' => 'b', 'correct' => false], ['id' => 'c', 'correct' => false]]];
        $this->assertNull(scene_runner::sniffs($many));
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
        // A scene of decisions without a right answer (the night after a battle lost) gives no points, only its ending.
        $scored = array_filter($scene['nodes'], fn($n) => in_array($n['type'], ['choice', 'classify', 'order'], true)
            && empty($n['dilemma']));
        if ($scored) {
            $this->assertGreaterThan(0, $result['points'], $code);
        }
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
        $this->assertSame(['SV-01' => ['ok' => 3, 'total' => 5]], self::scored($result['objectives']));
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
        $this->assertSame(['ok' => 1, 'total' => 1], self::scored($good['objectives'])['SV-04']);
        $this->assertSame(['ok' => 3, 'total' => 3], self::scored($good['objectives'])['SV-07']);
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
        $this->assertSame(['O' => ['ok' => 1, 'total' => 1]], self::scored($result['objectives']));
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
     * A scene with a path and a tend, for the verbs that tell an order as a journey and a choice as tending a being.
     *
     * @return array
     */
    private static function verbs_scene(): array {
        return ['id' => 'V', 'start' => 'camino', 'nodes' => [
            'camino' => ['type' => 'path', 'prompt' => '?', 'objective' => 'SV-05', 'steps' => [
                ['id' => 'raiz', 'label' => 'R', 'x' => 50, 'y' => 80], ['id' => 'tallo', 'label' => 'T', 'x' => 50, 'y' => 50],
                ['id' => 'hoja', 'label' => 'H', 'x' => 70, 'y' => 20]], 'next' => 'cuidar'],
            'cuidar' => ['type' => 'tend', 'prompt' => '?', 'objective' => 'SV-05', 'give' => 2, 'rounds' => 2, 'items' => [
                ['id' => 'luz', 'label' => 'L', 'correct' => true], ['id' => 'agua', 'label' => 'A', 'correct' => true],
                ['id' => 'leche', 'label' => 'M'], ['id' => 'musica', 'label' => 'S']], 'states' => [
                ['id' => 'verde', 'when' => ['all' => ['luz', 'agua']], 'correct' => true, 'points' => 5],
                ['id' => 'moho', 'when' => ['any' => ['leche']], 'effects' => [['flag' => 'leche']]],
                ['id' => 'casi', 'text' => 'Le falta algo']], 'next' => 'fin'],
            'fin' => ['type' => 'branch', 'rules' => [['if' => ['correct_at_least' => 2, 'in' => 'cuidar'], 'next' => 'bien']],
                'else' => 'medias'],
            'bien' => ['type' => 'ending', 'outcome' => 'success'],
            'medias' => ['type' => 'ending', 'outcome' => 'partial'],
        ]];
    }

    public function test_a_path_is_an_order_told_as_a_journey_and_a_tend_counts_its_rounds(): void {
        $path = [['n' => 'camino', 'step' => 'raiz'], ['n' => 'camino', 'step' => 'tallo'], ['n' => 'camino', 'step' => 'hoja']];
        // Right at the first round: the good state, its points and the good ending.
        $result = (new scene_runner(self::verbs_scene()))->run(array_merge($path, [['n' => 'cuidar', 'give' => ['agua', 'luz']]]));
        $this->assertSame('bien', $result['ending']);
        $this->assertSame(['ok' => 2, 'total' => 2], self::scored($result['objectives'])['SV-05']);
        // A wrong round, then the right one: it counts as after the consequence, and the flag of the state stays.
        $result = (new scene_runner(self::verbs_scene()))->run(array_merge($path, [['n' => 'cuidar', 'give' => ['leche', 'luz']],
            ['n' => 'cuidar', 'give' => ['luz', 'agua']]]));
        $this->assertSame('bien', $result['ending']);
        $this->assertSame(1, $result['objectives']['SV-05']['after']);
        // One round that is not right and going on: the scene follows with what was given (one right thing).
        $result = (new scene_runner(self::verbs_scene()))->run(array_merge($path, [['n' => 'cuidar', 'give' => ['luz', 'musica']]]));
        $this->assertSame('medias', $result['ending']);
        $this->assertSame(['ok' => 1, 'total' => 2], self::scored($result['objectives'])['SV-05']);
        // A step out of order in the path is a mistake, as in an order: right after the consequence.
        $result = (new scene_runner(self::verbs_scene()))->run(array_merge(
            [['n' => 'camino', 'step' => 'hoja']],
            $path,
            [['n' => 'cuidar', 'give' => ['agua', 'luz']]]
        ));
        $this->assertSame(1, $result['objectives']['SV-05']['after']);
        // Two things, not one nor three; and only things that are there.
        foreach ([['luz'], ['luz', 'agua', 'leche'], ['luz', 'piedra']] as $give) {
            try {
                (new scene_runner(self::verbs_scene()))->run(array_merge($path, [['n' => 'cuidar', 'give' => $give]]));
                $this->fail('A round that does not fit');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidscenepath', $e->errorcode);
            }
        }
        // The autoplayer plays them.
        $played = (new scene_runner(self::verbs_scene()))->run(\local_oksigeniatesape\local\autoplayer::play(self::verbs_scene()));
        $this->assertSame('bien', $played['ending']);
    }

    /**
     * A scene with the verbs find, use and justify.
     *
     * @return array
     */
    private static function more_verbs_scene(): array {
        return ['id' => 'W', 'start' => 'buscar', 'method' => 'SV-07', 'nodes' => [
            'buscar' => ['type' => 'find', 'prompt' => '?', 'objective' => 'SV-06', 'find' => 2, 'spots' => [
                ['id' => 'nenufar', 'label' => 'N', 'x' => 20, 'y' => 90, 'correct' => true],
                ['id' => 'arbol', 'label' => 'A', 'x' => 80, 'y' => 20, 'correct' => true],
                ['id' => 'junco', 'label' => 'J', 'x' => 90, 'y' => 70, 'correct' => true],
                ['id' => 'agua', 'label' => 'W', 'x' => 50, 'y' => 60]], 'next' => 'mochila'],
            'mochila' => ['type' => 'use', 'prompt' => '?', 'options' => [
                ['id' => 'red', 'label' => 'R', 'item' => 'net', 'correct' => true, 'objective' => 'SV-06'],
                ['id' => 'farol', 'label' => 'F', 'item' => 'lantern', 'next' => 'mochila'],
                ['id' => 'orilla', 'label' => 'X', 'objective' => 'SV-06', 'points' => 1]], 'next' => 'decidir'],
            'decidir' => ['type' => 'choice', 'prompt' => '?', 'options' => [
                ['id' => 'si', 'label' => 'S', 'correct' => true, 'objective' => 'SV-08'],
                ['id' => 'no', 'label' => 'N', 'correct' => false, 'objective' => 'SV-08']], 'next' => 'porque'],
            'porque' => ['type' => 'justify', 'about' => 'decidir', 'prompt' => '?', 'options' => [
                ['id' => 'dato', 'label' => 'D', 'correct' => true, 'for' => ['si']],
                ['id' => 'otro', 'label' => 'O', 'correct' => true, 'for' => ['si']],
                ['id' => 'bonito', 'label' => 'B', 'correct' => false],
                ['id' => 'contra', 'label' => 'C', 'correct' => true, 'for' => ['no']]], 'next' => 'fin'],
            'fin' => ['type' => 'branch', 'rules' => [['if' => ['correct_at_least' => 2, 'in' => 'buscar'], 'next' => 'bien']],
                'else' => 'medias'],
            'bien' => ['type' => 'ending', 'outcome' => 'success'],
            'medias' => ['type' => 'ending', 'outcome' => 'partial'],
        ]];
    }

    public function test_find_use_and_justify(): void {
        $find = [['n' => 'buscar', 'spot' => 'nenufar'], ['n' => 'buscar', 'spot' => 'arbol']];
        $rest = [['n' => 'mochila', 'pick' => ['red']], ['n' => 'decidir', 'pick' => ['si']],
            ['n' => 'porque', 'pick' => ['dato', 'otro']]];
        // Found at the first try, the net out of the backpack, and the two reasons of what was decided.
        $result = (new scene_runner(self::more_verbs_scene(), ['net']))->run(array_merge($find, $rest));
        $this->assertSame('bien', $result['ending']);
        $scored = self::scored($result['objectives']);
        $this->assertSame(['ok' => 2, 'total' => 2], $scored['SV-06']);
        $this->assertSame(['ok' => 2, 'total' => 2], $scored['SV-07']);
        // Both at the first try: the find and the net.
        $this->assertSame(2, $result['objectives']['SV-06']['first']);
        // Something touched that is not one of them: the find counts after the consequence.
        $result = (new scene_runner(self::more_verbs_scene(), ['net']))->run(array_merge(
            [['n' => 'buscar', 'spot' => 'agua']],
            $find,
            $rest
        ));
        $this->assertSame(1, $result['objectives']['SV-06']['after']);
        // The lantern out of the backpack leads back to the question, without it.
        $result = (new scene_runner(self::more_verbs_scene(), ['net', 'lantern']))->run(array_merge(
            $find,
            [['n' => 'mochila', 'pick' => ['farol']]],
            $rest
        ));
        $this->assertSame('bien', $result['ending']);
        // Decided the other way, the reasons are those of that answer.
        $result = (new scene_runner(self::more_verbs_scene()))->run(array_merge($find, [['n' => 'mochila', 'pick' => ['orilla']],
            ['n' => 'decidir', 'pick' => ['no']], ['n' => 'porque', 'pick' => ['contra', 'bonito']]]));
        $this->assertSame(['ok' => 1, 'total' => 2], self::scored($result['objectives'])['SV-07']);
        // What does not fit: a spot twice, a thing not carried, a reason of the other answer.
        $bad = [
            array_merge([['n' => 'buscar', 'spot' => 'nenufar'], ['n' => 'buscar', 'spot' => 'nenufar']], $rest),
            array_merge($find, [['n' => 'mochila', 'pick' => ['red']]]),
            array_merge($find, [['n' => 'mochila', 'pick' => ['orilla']], ['n' => 'decidir', 'pick' => ['no']],
                ['n' => 'porque', 'pick' => ['dato', 'contra']]]),
        ];
        foreach ($bad as $events) {
            try {
                (new scene_runner(self::more_verbs_scene()))->run($events);
                $this->fail('A play that does not fit');
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidscenepath', $e->errorcode);
            }
        }
        // The autoplayer plays them, with the net and without it.
        foreach ([['net'], []] as $items) {
            $events = \local_oksigeniatesape\local\autoplayer::play(self::more_verbs_scene(), $items);
            $this->assertSame('bien', (new scene_runner(self::more_verbs_scene(), $items))->run($events)['ending']);
        }
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
