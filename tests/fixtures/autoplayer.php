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

namespace local_oksigeniatesape;

/**
 * Plays a scene the right way, as a student who knows the answers would, for the tests.
 *
 * @package    local_oksigeniatesape
 * @category   test
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
     * @return array
     */
    public static function play(array $scene, array $items = [], array $roles = []): array {
        $runner = new \local_oksigeniatesape\local\scene_runner($scene, $items, $roles);
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
                    $node = $n['rules'][0]['next'];
                    break;
                case 'choice':
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
                    foreach ($n['steps'] as $step) {
                        $events[] = ['n' => $node, 'step' => $step['id']];
                    }
                    $node = $n['next'];
                    break;
                case 'ending':
                    return $events;
            }
        }
        throw new \coding_exception('The scene does not end');
    }

    /**
     * A scene of the mission that comes with the plugin.
     *
     * @param string $code
     * @return array
     */
    public static function scene(string $code): array {
        global $CFG;
        return json_decode(file_get_contents($CFG->dirroot . '/local/oksigeniatesape/missions/livingthings/scenes/' . $code .
            '.json'), true);
    }
}
