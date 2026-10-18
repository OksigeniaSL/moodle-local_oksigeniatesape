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
 * What the teacher learns from the scenes: per scene, how many played it and finished it, how the class did on each
 * objective, and the mix-ups that came up most (wrong answers chosen, items put in the wrong group at the first try).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class results {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Mix-ups shown per scene. */
    public const CONFUSIONS = 3;

    /** @var int The knots left hanging are those of the battles of this many last days: the review of the week. */
    public const KNOT_DAYS = 7;

    /**
     * The results of the mission of a course.
     *
     * @param int $courseid
     * @return array|null
     */
    public static function mission(int $courseid): ?array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $data = $mission->data;
        $names = $data['objectives'] ?? [];
        $info = [];
        foreach ($data['chapters'] ?? [] as $chapter) {
            $info[$chapter['code']] = $chapter;
        }
        $chapters = [];
        foreach ($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder') as $chapter) {
            $scenes = [];
            foreach ($DB->get_records(self::T . 'scene', ['chapterid' => $chapter->id], 'code') as $scene) {
                $scenes[] = self::scene($scene, $mission, $names);
            }
            $chapters[] = [
                'code' => $chapter->code,
                'name' => missions::text($info[$chapter->code]['name'] ?? $chapter->code, $mission->skin),
                'lit' => (bool) $chapter->litat,
                'done' => $DB->count_records(self::T . 'found', ['missionid' => $mission->id, 'kind' => 'chapter',
                    'code' => $chapter->code]),
                'scenes' => $scenes,
            ];
        }
        return [
            'title' => missions::text($data['title'] ?? $mission->code, $mission->skin),
            'players' => $DB->count_records(self::T . 'member', ['courseid' => $courseid, 'leftat' => 0]),
            'chapters' => $chapters,
            'games' => minigames::results($mission),
            'knots' => self::knots($courseid, $mission),
        ];
    }

    /**
     * The knots the class left hanging in the battles by turns of the last week, most first: what to go over again. With
     * how many times each one was left hanging and how many it was broken, its idea and why it is wrong.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param int|null $now
     * @return array
     */
    public static function knots(int $courseid, \stdClass $mission, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $counts = [];
        $battles = $DB->get_records_select(
            self::T . 'live',
            "courseid = ? AND kind = 'battle' AND timeend > ?",
            [$courseid, $now - self::KNOT_DAYS * DAYSECS],
            'timeend',
            'id, state'
        );
        foreach ($battles as $battle) {
            $state = json_decode($battle->state, true) ?: [];
            if (empty($state['knots'])) {
                continue;
            }
            foreach (['hanging', 'broken'] as $how) {
                foreach (array_unique((array) ($state[$how] ?? [])) as $code) {
                    $counts[$code][$how] = ($counts[$code][$how] ?? 0) + 1;
                }
            }
        }
        $list = [];
        foreach ($counts as $code => $count) {
            $knot = knots::get($mission, (string) $code);
            if ($knot && !empty($count['hanging'])) {
                $list[] = ['code' => (string) $code, 'name' => $knot['name'], 'claim' => $knot['claim'], 'why' => $knot['why'],
                    'hanging' => $count['hanging'], 'broken' => $count['broken'] ?? 0];
            }
        }
        usort($list, fn($a, $b) => [$b['hanging'], $a['broken']] <=> [$a['hanging'], $b['broken']]);
        return $list;
    }

    /**
     * The mix-ups of one play of a scene: each wrong option chosen, and each item put in a wrong group at its first try.
     *
     * @param array $def The scene, as it is played in the course.
     * @param array $events Of the play.
     * @return array Key (node|option, or node|item|group) and how many times.
     */
    public static function mixups(array $def, array $events): array {
        $counts = [];
        $seen = [];
        foreach ($events as $event) {
            $node = $def['nodes'][$event['n'] ?? ''] ?? null;
            if (!$node || (isset($event['pick']) && !empty($node['dilemma']))) {
                continue;
            }
            if (isset($event['pick'])) {
                foreach ($node['options'] ?? [] as $option) {
                    if (in_array($option['id'], (array) $event['pick'], true) && ($option['correct'] ?? null) === false) {
                        $key = $event['n'] . '|' . $option['id'];
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            } else if (isset($event['item'], $event['bin']) && empty($seen[$event['n'] . $event['item']])) {
                // Only the first try of each item.
                $seen[$event['n'] . $event['item']] = true;
                foreach ($node['items'] ?? [] as $item) {
                    if ($item['id'] === $event['item'] && $item['bin'] !== $event['bin']) {
                        $key = $event['n'] . '|' . $item['id'] . '|' . $event['bin'];
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            }
        }
        return $counts;
    }

    /**
     * The mix-ups of a class vote: each wrong option, with how many phones voted it.
     *
     * @param array $def The scene, as it is played in the course.
     * @param array $votes Of live::votes().
     * @return array As mixups().
     */
    public static function voted(array $def, array $votes): array {
        $counts = [];
        foreach ($votes as $vote) {
            $node = $def['nodes'][$vote['node']] ?? null;
            if (!$node || !empty($node['dilemma'])) {
                continue;
            }
            $wrong = [];
            foreach ($node['options'] ?? [] as $option) {
                if (($option['correct'] ?? null) === false) {
                    $wrong[$option['id']] = true;
                }
            }
            foreach ($vote['ids'] as $i => $id) {
                if (isset($wrong[$id]) && !empty($vote['tally'][$i])) {
                    $key = $vote['node'] . '|' . $id;
                    $counts[$key] = ($counts[$key] ?? 0) + (int) $vote['tally'][$i];
                }
            }
        }
        return $counts;
    }

    /**
     * The results of one scene.
     *
     * @param \stdClass $scene
     * @param \stdClass $mission
     * @param array $names Names of the objectives.
     * @return array
     */
    private static function scene(\stdClass $scene, \stdClass $mission, array $names): array {
        global $DB;
        $def = edits::scene($scene, $mission);
        $attempts = $DB->get_records(self::T . 'sceneattempt', ['sceneid' => $scene->id], 'id');
        $objectives = [];
        $players = [];
        $counts = [];
        $decided = [];
        $games = [];
        foreach ($attempts as $attempt) {
            $players[$attempt->charid] = true;
            // A game played as a team is one game: what it shows of the class counts once.
            if ($attempt->teamplay) {
                if (isset($games[$attempt->teamplay])) {
                    continue;
                }
                $games[$attempt->teamplay] = true;
            }
            foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                $objectives[$code] = $objectives[$code] ?? ['ok' => 0, 'total' => 0];
                $objectives[$code]['ok'] += (int) $o['ok'];
                $objectives[$code]['total'] += (int) $o['total'];
            }
            $path = json_decode((string) $attempt->path, true) ?: [];
            foreach ($path['events'] ?? [] as $event) {
                $node = $def['nodes'][$event['n'] ?? ''] ?? null;
                if ($node && isset($event['pick']) && !empty($node['dilemma'])) {
                    // A dilemma: what the class decided, without right or wrong.
                    foreach ((array) $event['pick'] as $id) {
                        $decided[$event['n']][$id] = ($decided[$event['n']][$id] ?? 0) + 1;
                    }
                }
            }
            foreach (self::mixups($def, $path['events'] ?? []) as $key => $count) {
                $counts[$key] = ($counts[$key] ?? 0) + $count;
            }
        }
        // What the whole class chose wrong on the class screen counts too, as nobody's try.
        foreach ($DB->get_fieldset_select(self::T . 'classplay', 'counts', 'sceneid = ?', [$scene->id]) as $json) {
            foreach (json_decode((string) $json, true) ?: [] as $key => $count) {
                $counts[$key] = ($counts[$key] ?? 0) + (int) $count;
            }
        }
        arsort($counts);
        $confusions = [];
        foreach (array_slice($counts, 0, self::CONFUSIONS, true) as $key => $count) {
            [$question, $text] = self::describe($def, $key, $mission->skin, $mission->variant);
            $confusions[] = ['question' => $question, 'text' => $text, 'count' => $count];
        }
        $dilemmas = [];
        foreach ($decided as $node => $picks) {
            arsort($picks);
            $options = [];
            foreach ($picks as $id => $count) {
                $options[] = ['text' => self::describe($def, $node . '|' . $id, $mission->skin, $mission->variant)[1],
                    'count' => $count];
            }
            [$question] = self::describe($def, $node . '|' . array_key_first($picks), $mission->skin, $mission->variant);
            $dilemmas[] = ['question' => $question, 'options' => $options];
        }
        $list = [];
        foreach ($objectives as $code => $o) {
            $list[] = ['code' => $code, 'name' => (string) ($names[$code] ?? $code), 'ok' => $o['ok'], 'total' => $o['total']];
        }
        return [
            'id' => (int) $scene->id,
            'code' => $scene->code,
            'title' => (string) ($def['title'] ?? $scene->code),
            'players' => count($players),
            'done' => $DB->count_records(self::T . 'found', ['missionid' => $mission->id, 'kind' => 'scene',
                'code' => $scene->code]),
            'attempts' => count($attempts),
            'objectives' => $list,
            'confusions' => $confusions,
            'dilemmas' => $dilemmas,
        ];
    }

    /**
     * A mix-up in words: the question, and the wrong option chosen or the item and the group it was put in.
     *
     * @param array $def The scene.
     * @param string $key node|option or node|item|bin.
     * @param string $skin Story of the mission.
     * @param string $variant Local examples.
     * @return string[] The question and the mix-up.
     */
    private static function describe(array $def, string $key, string $skin, string $variant): array {
        $parts = explode('|', $key);
        $node = $def['nodes'][$parts[0]];
        $text = function ($text) use ($def, $skin, $variant): string {
            return preg_replace_callback('/\{\{local:([a-z0-9_-]+)\}\}/', fn($m) => (string) ($def['locals'][$m[1]][$variant]
                ?? $m[0]), missions::text($text ?? '', $skin, $variant));
        };
        $question = $text($node['prompt'] ?? '');
        if (count($parts) === 2) {
            foreach ($node['options'] as $option) {
                if ($option['id'] === $parts[1]) {
                    return [$question, $text($option['label'])];
                }
            }
        }
        $label = $parts[1];
        $bin = $parts[2] ?? '';
        foreach ($node['items'] ?? [] as $item) {
            if ($item['id'] === $parts[1]) {
                $label = $text($item['label']);
            }
        }
        foreach ($node['bins'] ?? [] as $b) {
            if ($b['id'] === $bin) {
                $bin = $text($b['label']);
            }
        }
        return [$question, $label . ' → ' . $bin];
    }
}
