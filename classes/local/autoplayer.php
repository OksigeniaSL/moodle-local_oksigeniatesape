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
 * Plays a scene by itself: the right way, as a student who knows the answers would, or as a first try with the mix-ups
 * a child makes and puts right on the way. For the demo class and the tests.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autoplayer {
    /**
     * The events of a good play: the best options available, each item in its group, the steps in order.
     *
     * @param array $scene
     * @param string[] $items Items the student carries.
     * @param string[] $roles Roles in the team.
     * @param string[] $flags Flags of the mission the character carries.
     * @param string $role The character's own role.
     * @param int $way Which of its ways a branch at random takes (counted from 0, round the list).
     * @return array
     */
    public static function play(
        array $scene,
        array $items = [],
        array $roles = [],
        array $flags = [],
        string $role = '',
        int $way = 0
    ): array {
        $runner = new scene_runner($scene, $items, $roles, $flags, $role);
        $events = [];
        $node = $scene['start'];
        $extra = 0;
        $used = [];
        for ($i = 0; $i < 200; $i++) {
            $n = $scene['nodes'][$node];
            switch ($n['type']) {
                case 'story':
                    $node = $n['next'];
                    break;
                case 'branch':
                    if (!empty($n['pick'])) {
                        // A way at random: the one asked for.
                        $events[] = ['n' => $node, 'to' => $n['pick'][$way % count($n['pick'])]];
                        $node = $n['pick'][$way % count($n['pick'])];
                        break;
                    }
                    // The first rule that can hold: flags the character has, or a number of right answers.
                    $node = $n['else'];
                    foreach ($n['rules'] as $rule) {
                        if (!isset($rule['if']['flag']) || in_array($rule['if']['flag'], $flags, true)) {
                            $node = $rule['next'];
                            break;
                        }
                    }
                    break;
                case 'choice':
                case 'use':
                case 'justify':
                    // What was picked in the decision a justify is about, in this play.
                    $about = array_filter($events, fn($e) => $e['n'] === ($n['about'] ?? '') && isset($e['pick']));
                    $n = $runner->as_choice($n, $about ? end($about)['pick'] : []);
                    $options = array_values(array_filter($n['options'], fn($o) => $runner->has($o['requires'] ?? null)
                        && ($o['correct'] ?? null) !== false));
                    usort($options, fn($a, $b) => [!empty($b['correct']), $b['points'] ?? 0]
                        <=> [!empty($a['correct']), $a['points'] ?? 0]);
                    $max = (int) ($n['select']['max'] ?? 1);
                    $picked = array_slice($options, 0, $max);
                    $events[] = ['n' => $node, 'pick' => array_map(fn($o) => $o['id'], $picked)];
                    foreach ($picked as $option) {
                        foreach ($option['effects'] ?? [] as $effect) {
                            $extra += (int) ($effect['extraquestion'] ?? 0);
                            if (isset($effect['flag'])) {
                                $flags[] = (string) $effect['flag'];
                            }
                        }
                    }
                    if ($max > 1) {
                        $node = $n['next'];
                    } else if ($extra > 0 && !empty($n['extra']) && empty($used[$node])) {
                        $extra--;
                        $used[$node] = true;
                    } else {
                        $node = $picked[0]['next'] ?? $n['next'];
                    }
                    break;
                case 'classify':
                    foreach ($n['items'] as $item) {
                        if ($runner->has($item['requires'] ?? null)) {
                            $events[] = ['n' => $node, 'item' => $item['id'], 'bin' => $item['bin']];
                        }
                    }
                    $node = $n['next'];
                    break;
                case 'order':
                case 'path':
                    foreach ($n['steps'] as $step) {
                        $events[] = ['n' => $node, 'step' => $step['id']];
                    }
                    $node = $n['next'];
                    break;
                case 'find':
                    $wanted = array_values(array_filter($n['spots'], fn($s) => !empty($s['correct'])));
                    foreach (array_slice($wanted, 0, (int) ($n['find'] ?? count($wanted))) as $spot) {
                        $events[] = ['n' => $node, 'spot' => $spot['id']];
                    }
                    $node = $n['next'];
                    break;
                case 'tend':
                    // The first things to give that make a good state of the being.
                    $given = self::tend_give($n, $runner, true);
                    $events[] = ['n' => $node, 'give' => $given];
                    $state = scene_runner::tend_state($n, $given) ?? [];
                    $node = $state['next'] ?? $n['next'];
                    break;
                case 'ending':
                    return $events;
            }
        }
        throw new \coding_exception('The scene does not end');
    }

    /**
     * Things to give in a tend that make a good state of the being (or one that is not good): the first combination,
     * of those at hand, that does.
     *
     * @param array $n The tend node.
     * @param scene_runner $runner To know what is at hand.
     * @param bool $good
     * @return string[] Empty if none does.
     */
    private static function tend_give(array $n, scene_runner $runner, bool $good): array {
        $ids = [];
        foreach ($n['items'] ?? [] as $item) {
            if ($runner->has($item['requires'] ?? null)) {
                $ids[] = (string) $item['id'];
            }
        }
        $give = max(1, (int) ($n['give'] ?? 1));
        $pick = function (array $chosen, int $from) use (&$pick, $ids, $give, $n, $good): array {
            if (count($chosen) === $give) {
                $state = scene_runner::tend_state($n, $chosen);
                return $state && !empty($state['correct']) === $good ? $chosen : [];
            }
            for ($i = $from; $i < count($ids); $i++) {
                $found = $pick(array_merge($chosen, [$ids[$i]]), $i + 1);
                if ($found) {
                    return $found;
                }
            }
            return [];
        };
        return $pick([], 0);
    }

    /**
     * A first try: a good play with some mix-ups the scene lets put right on the way (a wrong group before the right
     * one, a step out of order, a wrong answer that leads back to the question). It reaches the ending of the good
     * play, and the teacher sees the mix-ups in the results.
     *
     * @param array $scene
     * @param string $seed Anything that makes the mix-ups of this play the same each time.
     * @param int $chance Percent of the decisions with a mix-up.
     * @param string[] $items Items the student carries.
     * @param string[] $roles Roles in the team.
     * @param string[] $flags Flags of the mission the character carries.
     * @param string $role The character's own role.
     * @return array
     */
    public static function tangled(
        array $scene,
        string $seed,
        int $chance = 35,
        array $items = [],
        array $roles = [],
        array $flags = [],
        string $role = ''
    ): array {
        $events = [];
        foreach (self::play($scene, $items, $roles, $flags, $role) as $k => $event) {
            $n = $scene['nodes'][$event['n']];
            $roll = crc32($seed . '|' . $k) % 100;
            if ($roll < $chance) {
                $wrong = null;
                if (isset($event['bin']) && ($n['retry'] ?? 'item') !== 'none') {
                    // The mix-ups the scene foresees (those with their own feedback) first: the mushroom with the plants.
                    $item = array_values(array_filter($n['items'], fn($it) => $it['id'] === $event['item']))[0] ?? [];
                    $bins = array_keys($item['feedback'] ?? []) ?: array_column($n['bins'], 'id');
                    $bins = array_values(array_diff($bins, [$event['bin']]));
                    $wrong = $bins ? ['item' => $event['item'], 'bin' => $bins[$roll % count($bins)]] : null;
                } else if (isset($event['step'])) {
                    $steps = array_column($n['steps'], 'id');
                    $later = array_slice($steps, array_search($event['step'], $steps, true) + 1);
                    $wrong = $later ? ['step' => $later[$roll % count($later)]] : null;
                } else if (isset($event['give']) && (int) ($n['rounds'] ?? 1) > 1) {
                    // A round that does not work before the one that does.
                    $runner = new scene_runner($scene, $items, $roles, $flags, $role);
                    $bad = self::tend_give($n, $runner, false);
                    $wrong = $bad ? ['give' => $bad] : null;
                } else if (isset($event['pick']) && count($event['pick']) === 1) {
                    // Only the wrong answers that lead back to the question: the play goes on as it was.
                    $back = array_values(array_filter($n['options'], fn($o) => ($o['correct'] ?? null) === false
                        && ($o['next'] ?? null) === $event['n'] && empty($o['requires'])));
                    $wrong = $back ? ['pick' => [$back[$roll % count($back)]['id']]] : null;
                }
                if ($wrong) {
                    $events[] = ['n' => $event['n']] + $wrong;
                }
            }
            $events[] = $event;
        }
        return $events;
    }
}
