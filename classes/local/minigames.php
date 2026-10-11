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
 * Minigames of a mission. What is learnt is in how they are played; they are short and give what the mission needs
 * (items, the coat of the creature), once. The client sends what the student did and the server counts it again.
 *
 * Defensa del nido: animals come down towards the nest; the right net is the one of their group, or of the group that
 * contains it when that net is not in the step. A wrong net, or none, lets the animal pass and hurts the shield.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class minigames {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Coats a creature can wear. */
    public const COATS = ['fur', 'feathers', 'scales'];

    /** @var int Mix-ups shown to the teacher per game. */
    public const CONFUSIONS = 3;

    /**
     * A minigame of the mission of a course, as a student of a step plays it: texts in the skin and the local
     * examples, the nets and the animals with their right net.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param int $step
     * @return array|null
     */
    public static function config(\stdClass $mission, string $code, int $step): ?array {
        $game = $mission->data['games'][$code] ?? null;
        if (!$game) {
            return null;
        }
        $level = self::level($game, $step);
        $nets = [];
        foreach ($level['nets'] as $net) {
            $nets[] = ['id' => $net, 'label' => (string) $game['nets'][$net]['label'],
                'hint' => (string) ($game['nets'][$net]['hint'] ?? '')];
        }
        $animals = [];
        foreach ($level['animals'] as $id) {
            $animal = $game['animals'][$id];
            $label = $animal['label'];
            $animals[] = [
                'id' => $id,
                'label' => is_array($label) ? (string) ($label[$mission->variant] ?? reset($label)) : (string) $label,
                'net' => self::net($game, (string) $animal['group'], $level['nets']),
                'why' => (string) ($animal['why'] ?? ''),
            ];
        }
        return [
            'code' => $code,
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => $nets,
            'animals' => $animals,
            'goal' => (int) $level['goal'],
            'shield' => (int) $level['shield'],
            'speed' => (int) $level['speed'],
        ];
    }

    /**
     * Plays a minigame: counts what the student did, keeps it for the teacher and gives the reward the first time it
     * is won.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param array $events Each one: a (the animal) and net (the net thrown; empty if it reached the nest).
     * @param int|null $now
     * @return array success, caught, missed, goal, tries, first, items, coat.
     */
    public static function play(int $courseid, int $userid, string $code, array $events, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $courseid, $now);
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $mission = missions::get($courseid);
        $game = $mission ? self::config($mission, $code, (int) $member->step) : null;
        if (!$game) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        $right = array_column($game['animals'], 'net', 'id');
        $nets = array_column($game['nets'], 'id');
        $seen = [];
        $caught = 0;
        $missed = 0;
        $wrong = [];
        foreach ($events as $event) {
            $animal = (string) ($event['a'] ?? '');
            $net = (string) ($event['net'] ?? '');
            if (!isset($right[$animal]) || isset($seen[$animal]) || ($net !== '' && !in_array($net, $nets, true))) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$animal] = true;
            if ($net === $right[$animal]) {
                $caught++;
            } else {
                $missed++;
                if ($net !== '') {
                    $wrong[$animal] = $net;
                }
            }
        }
        $success = $caught >= $game['goal'] && $missed < $game['shield'];
        $transaction = $DB->start_delegated_transaction();
        $tries = $DB->count_records(self::T . 'minigame', ['charid' => $char->id, 'courseid' => $courseid, 'game' => $code]) + 1;
        $DB->insert_record(self::T . 'minigame', (object) [
            'charid' => $char->id,
            'courseid' => $courseid,
            'cmid' => 0,
            'game' => $code,
            'success' => $success ? 1 : 0,
            'tries' => $tries,
            'detail' => json_encode(['step' => (int) $member->step, 'caught' => $caught, 'missed' => $missed,
                'wrong' => $wrong]),
            'timecreated' => $now,
        ]);
        $result = ['success' => $success, 'caught' => $caught, 'missed' => $missed, 'goal' => $game['goal'],
            'tries' => $tries, 'first' => false, 'items' => [], 'coat' => false];
        $key = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'game', 'code' => $code];
        if ($success && !$DB->record_exists(self::T . 'found', $key)) {
            $DB->insert_record(self::T . 'found', (object) ($key + ['courseid' => $courseid, 'timecreated' => $now]));
            $result['first'] = true;
            $reward = $mission->data['games'][$code]['reward'] ?? [];
            $itemid = empty($reward['item']) ? 0 : missions::item_id((string) $reward['item']);
            if ($itemid && engine::item($member, $itemid, 'minigame', 0, 0, $now)) {
                $result['items'][] = (string) $reward['item'];
            }
            $result['coat'] = !empty($reward['coat']);
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Whether a student can dress their creature: they won a minigame that gives the coat.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return bool
     */
    public static function has_coat(\stdClass $char, int $courseid): bool {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return false;
        }
        foreach ($mission->data['games'] ?? [] as $code => $game) {
            if (
                !empty($game['reward']['coat']) && $DB->record_exists(self::T . 'found', ['charid' => $char->id,
                    'missionid' => $mission->id, 'kind' => 'game', 'code' => $code])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dresses the creature of a student with the coat they choose (fur, feathers or scales; empty to take it off).
     *
     * @param int $courseid
     * @param int $userid
     * @param string $coat
     * @return \stdClass The character.
     */
    public static function set_coat(int $courseid, int $userid, string $coat): \stdClass {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $allowed = $coat === '' || (in_array($coat, self::COATS, true) && self::has_coat($char, $courseid));
        if (empty($char->creature) || !$allowed) {
            throw new \moodle_exception('nocoat', 'local_oksigeniatesape');
        }
        $traits = json_decode((string) $char->creature, true) ?: [];
        $traits['coat'] = $coat;
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode($traits),
            'timemodified' => time()]);
        return characters::get($userid);
    }

    /**
     * How the class did in the minigames of the mission, for the teacher: who played, who won, tries and the most
     * common mix-ups (an animal and the net thrown at it).
     *
     * @param \stdClass $mission With data.
     * @return array
     */
    public static function results(\stdClass $mission): array {
        global $DB;
        $list = [];
        foreach ($mission->data['games'] ?? [] as $code => $game) {
            $rows = $DB->get_records(self::T . 'minigame', ['courseid' => $mission->courseid, 'game' => $code], 'id');
            $players = [];
            $won = [];
            $counts = [];
            foreach ($rows as $row) {
                $players[$row->charid] = true;
                if ($row->success) {
                    $won[$row->charid] = true;
                }
                foreach ((json_decode((string) $row->detail, true)['wrong'] ?? []) as $animal => $net) {
                    $key = $animal . '|' . $net;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
            arsort($counts);
            $confusions = [];
            foreach (array_slice($counts, 0, self::CONFUSIONS, true) as $key => $count) {
                [$animal, $net] = explode('|', $key);
                $label = $game['animals'][$animal]['label'] ?? $animal;
                $label = is_array($label) ? ($label[$mission->variant] ?? reset($label)) : $label;
                $confusions[] = ['text' => $label . ' → ' . ($game['nets'][$net]['label'] ?? $net), 'count' => $count];
            }
            $list[] = [
                'code' => $code,
                'title' => missions::text($game['title'] ?? $code, $mission->skin),
                'chapter' => (string) self::chapter_of($mission, $code),
                'players' => count($players),
                'won' => count($won),
                'tries' => count($rows),
                'confusions' => $confusions,
            ];
        }
        return $list;
    }

    /**
     * The chapter of the mission that has a minigame.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @return string The code of the chapter, or empty.
     */
    public static function chapter_of(\stdClass $mission, string $code): string {
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            if (in_array($code, $chapter['games'] ?? [], true)) {
                return (string) $chapter['code'];
            }
        }
        return '';
    }

    /**
     * The level of a game for a step: the one of the step, or the nearest below (or the first).
     *
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function level(array $game, int $step): array {
        for ($s = $step; $s >= 1; $s--) {
            if (isset($game['levels'][(string) $s])) {
                return $game['levels'][(string) $s];
            }
        }
        return reset($game['levels']);
    }

    /**
     * The right net for a group among the nets of a level.
     *
     * @param array $game
     * @param string $group
     * @param array $nets
     * @return string
     */
    private static function net(array $game, string $group, array $nets): string {
        $seen = [];
        while (!in_array($group, $nets, true)) {
            if (isset($seen[$group]) || !isset($game['parents'][$group])) {
                return '';
            }
            $seen[$group] = true;
            $group = (string) $game['parents'][$group];
        }
        return $group;
    }
}
