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
 * The hunt for mistakes (type errors): the Embrollo has changed facts in the sheets of the field notebook; the student
 * touches what is wrong and is shown what is right and why. A word touched that was right is a blot of ink, and too many
 * blots lose the game. The sheets are written as in the board of Classtools: [what it says|right/also right|why].
 *
 * The two circles (type venn): each thing of a sheet goes in one circle, in the other or in the middle, where they meet.
 * Sheets are written as in Classtools: «Group: thing, thing» for each circle (with «;» if a thing has commas), a thing
 * in both groups goes in the middle, and «? thing: why». A thing in the wrong place is a slip.
 *
 * Who am I? (type whoami): a shadow gives its clues one by one, from the hardest to the easiest, and the student
 * guesses who it is among four; a wrong guess shows the next clue, and with no clue left the shadow gets away.
 * Written as in Classtools: «= answer / also accepted» and a clue per line.
 *
 * Who is alive? (type whack): living things and things that are not come out of the holes of the clearing for a moment;
 * the student touches only what is alive. A thing touched that is not alive is a slip, with its why; a living thing let
 * go is not caught.
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

    /** @var int Right answers in a row after which the healer gets back the first slip of a play. */
    public const STREAK = 5;

    /**
     * A minigame of the mission of a course, as a student of a step and a role plays it: texts in the skin and the
     * local examples, what there is to play, and the passive of the role (the protector holds one slip more).
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param int $step
     * @param string $role Of the student: protector, healer, sage or scout.
     * @return array|null
     */
    public static function config(\stdClass $mission, string $code, int $step, string $role = ''): ?array {
        $config = self::config_step($mission, $code, $step);
        if (!$config) {
            return null;
        }
        // The two lines at the start: what the Embrollo did and how the creature takes it ({{creature}}, for the client).
        $scene = $mission->data['games'][$code]['scene'] ?? [];
        $config['scene'] = [
            'embrollo' => missions::text($scene['embrollo'] ?? '', $mission->skin, $mission->variant),
            'creature' => missions::text($scene['creature'] ?? '', $mission->skin, $mission->variant),
        ];
        $config['passive'] = in_array($role, characters::ROLES, true) ? $role : '';
        $config['streak'] = self::STREAK;
        if ($config['passive'] === 'protector') {
            if (isset($config['misses'])) {
                $config['misses']++;
            }
            if (!empty($config['shield'])) {
                // In the nest, each animal that gets in is one not caught: the goal comes down with the shield.
                $config['shield']++;
                $config['goal'] = max(1, $config['goal'] - 1);
            }
        }
        return $config;
    }

    /**
     * A minigame as a student of a step plays it, before the passive of their role.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param int $step
     * @return array|null
     */
    private static function config_step(\stdClass $mission, string $code, int $step): ?array {
        $game = $mission->data['games'][$code] ?? null;
        if (!$game) {
            return null;
        }
        if (($game['type'] ?? '') === 'errors') {
            return self::errors_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'whack') {
            return self::whack_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'venn') {
            return self::venn_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'whoami') {
            return self::whoami_config($mission, $code, $game, $step);
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
     * A hunt for mistakes as a student of a step plays it: the sheets of their step, each cut into text and mistakes.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function errors_config(\stdClass $mission, string $code, array $game, int $step): array {
        $sheets = [];
        foreach (self::sheets_for($game, $step) as $sheet) {
            $text = $sheet['text'] ?? '';
            $text = is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
            $sheets[] = ['id' => (string) $sheet['id'], 'title' => missions::text((string) ($sheet['title'] ?? ''), $mission->skin),
                'parts' => self::parts($text)];
        }
        $goal = 0;
        foreach ($sheets as $sheet) {
            $goal += count(array_filter($sheet['parts'], fn($p) => $p['mark'] >= 0));
        }
        return [
            'code' => $code,
            'type' => 'errors',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'sheets' => $sheets,
            'goal' => $goal,
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Who is alive? as a student of a step plays it: the things of their level, each one alive or not, with its why.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function whack_config(\stdClass $mission, string $code, array $game, int $step): array {
        $level = self::level($game, $step);
        $things = [];
        foreach ($level['things'] ?? [] as $id) {
            $thing = $game['things'][$id] ?? null;
            if (!$thing) {
                continue;
            }
            $label = $thing['label'];
            $why = $thing['why'] ?? '';
            $things[] = ['id' => (string) $id,
                'label' => is_array($label) ? (string) ($label[$mission->variant] ?? $label['gen'] ?? reset($label)) : (string) $label,
                'alive' => !empty($thing['alive']),
                'why' => is_array($why) ? (string) ($why[$mission->variant] ?? $why['gen'] ?? reset($why)) : (string) $why];
        }
        return [
            'code' => $code,
            'type' => 'whack',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'things' => $things,
            'goal' => (int) ($level['goal'] ?? 0),
            'misses' => max(1, (int) ($level['misses'] ?? 3)),
            'shield' => 0,
            'speed' => (int) ($level['speed'] ?? 1),
        ];
    }

    /**
     * The two circles as a student of a step plays them: the sheets of their step, each with its two groups and its
     * things.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function venn_config(\stdClass $mission, string $code, array $game, int $step): array {
        $sheets = [];
        $goal = 0;
        foreach (self::sheets_for($game, $step) as $sheet) {
            $venn = self::venn(self::sheet_text($mission, $sheet));
            $goal += count($venn['items']);
            $title = missions::text((string) ($sheet['title'] ?? ''), $mission->skin);
            $sheets[] = ['id' => (string) $sheet['id'], 'title' => $title, 'parts' => []] + $venn;
        }
        return [
            'code' => $code,
            'type' => 'venn',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'sheets' => $sheets,
            'goal' => $goal,
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Who am I? as a student of a step plays it: the riddles of their step, each with its clues and four answers to
     * choose from (its own and three of the other riddles or decoys of the game).
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function whoami_config(\stdClass $mission, string $code, array $game, int $step): array {
        $pool = [];
        foreach ($game['sheets'] ?? [] as $sheet) {
            $pool[] = self::riddle(self::sheet_text($mission, $sheet))['answer'];
        }
        foreach ($game['decoys'] ?? [] as $decoy) {
            $pool[] = is_array($decoy) ? (string) ($decoy[$mission->variant] ?? $decoy['gen'] ?? reset($decoy)) : (string) $decoy;
        }
        $pool = array_values(array_unique(array_filter($pool)));
        $riddles = [];
        foreach (self::sheets_for($game, $step) as $sheet) {
            $riddle = self::riddle(self::sheet_text($mission, $sheet));
            $others = array_values(array_diff($pool, [$riddle['answer']]));
            shuffle($others);
            $options = array_merge([$riddle['answer']], array_slice($others, 0, 3));
            shuffle($options);
            $riddles[] = ['id' => (string) $sheet['id'], 'answer' => $riddle['answer'], 'clues' => $riddle['clues'],
                'options' => $options];
        }
        return [
            'code' => $code,
            'type' => 'whoami',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'riddles' => $riddles,
            'goal' => count($riddles),
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * A riddle of Who am I? from its plain text: the answer (and others accepted) and the clues, hardest first.
     *
     * @param string $text
     * @return array answer, accept and clues.
     */
    public static function riddle(string $text): array {
        $answer = '';
        $accept = [];
        $clues = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($line[0] === '=') {
                $names = array_values(array_filter(array_map('trim', explode('/', substr($line, 1)))));
                $answer = (string) array_shift($names);
                $accept = $names;
                continue;
            }
            $clues[] = $line;
        }
        return ['answer' => $answer, 'accept' => $accept, 'clues' => $clues];
    }

    /**
     * The text of a sheet with the examples of the place of the mission.
     *
     * @param \stdClass $mission
     * @param array $sheet
     * @return string
     */
    private static function sheet_text(\stdClass $mission, array $sheet): string {
        $text = $sheet['text'] ?? '';
        return is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
    }

    /**
     * A sheet of the two circles from its plain text: the first two groups, and each thing once, in a (only the first
     * group), b (only the second) or ab (both), with its why.
     *
     * @param string $text
     * @return array groups (two names) and items (label, zone, why).
     */
    public static function venn(string $text): array {
        $groups = [];
        $in = [];
        $why = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($line[0] === '?') {
                [$thing, $because] = array_pad(explode(':', substr($line, 1), 2), 2, '');
                $why[\core_text::strtolower(trim($thing))] = trim($because);
                continue;
            }
            [$name, $list] = array_pad(explode(':', $line, 2), 2, '');
            if (count($groups) === 2) {
                continue;
            }
            $groups[] = trim($name);
            foreach (explode(str_contains($list, ';') ? ';' : ',', $list) as $thing) {
                $thing = trim($thing);
                if ($thing !== '') {
                    $in['_' . $thing][count($groups) - 1] = true;
                }
            }
        }
        $items = [];
        foreach ($in as $key => $where) {
            $label = substr($key, 1);
            $zone = count($where) > 1 ? 'ab' : (isset($where[0]) ? 'a' : 'b');
            $items[] = ['label' => $label, 'zone' => $zone, 'why' => $why[\core_text::strtolower($label)] ?? ''];
        }
        return ['groups' => $groups, 'items' => $items];
    }

    /**
     * The sheets of a hunt for mistakes for a step: those that list it, or all of them if none does.
     *
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function sheets_for(array $game, int $step): array {
        $all = array_values(array_filter($game['sheets'] ?? [], fn($s) => !empty($s['id'])));
        $mine = array_values(array_filter($all, fn($s) => in_array($step, array_map('intval', $s['steps'] ?? []), true)));
        return $mine ?: $all;
    }

    /**
     * The blots of ink a step can make in a hunt for mistakes: that of the step, or of the nearest below.
     *
     * @param array $game
     * @param int $step
     * @return int
     */
    private static function misses(array $game, int $step): int {
        for ($s = $step; $s >= 1; $s--) {
            if (isset($game['misses'][$s]) || isset($game['misses'][(string) $s])) {
                return max(1, (int) ($game['misses'][$s] ?? $game['misses'][(string) $s]));
            }
        }
        return 3;
    }

    /**
     * A sheet cut into its text and its mistakes, from [what it says|right/also right|why].
     *
     * @param string $text
     * @return array Of text, mark (its number, -1 for plain text), wrong, right (accepted) and why.
     */
    public static function parts(string $text): array {
        preg_match_all('/\[([^\]|]*)\|([^\]|]*)(?:\|([^\]]*))?\]/u', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $parts = [];
        $at = 0;
        foreach ($found as $mark => $m) {
            if ($m[0][1] > $at) {
                $parts[] = ['text' => substr($text, $at, $m[0][1] - $at), 'mark' => -1, 'wrong' => '', 'right' => [], 'why' => ''];
            }
            $parts[] = ['text' => '', 'mark' => $mark, 'wrong' => trim($m[1][0]),
                'right' => array_values(array_filter(array_map('trim', explode('/', $m[2][0])))),
                'why' => trim((string) ($m[3][0] ?? ''))];
            $at = $m[0][1] + strlen($m[0][0]);
        }
        if ($at < strlen($text)) {
            $parts[] = ['text' => substr($text, $at), 'mark' => -1, 'wrong' => '', 'right' => [], 'why' => ''];
        }
        return $parts;
    }

    /**
     * Plays a minigame: counts what the student did, keeps it for the teacher and gives the reward the first time it
     * is won.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param array $events What the student did, as each kind of game counts it.
     * @param int|null $now
     * @param string $playid Made by the device: a play sent again with it counts once.
     * @return array success, caught, missed, goal, tries, first, items, coat, gems.
     */
    public static function play(
        int $courseid,
        int $userid,
        string $code,
        array $events,
        ?int $now = null,
        string $playid = ''
    ): array {
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
        if ($playid !== '') {
            $done = $DB->get_record(self::T . 'minigame', ['charid' => $char->id, 'playid' => $playid]);
            $kept = $done ? (json_decode((string) $done->detail, true)['result'] ?? null) : null;
            if ($kept) {
                // The same play, sent again because the answer was lost on the way: counted once, and what it gave is
                // shown again.
                return $kept;
            }
        }
        $mission = missions::get($courseid);
        $game = $mission ? self::config($mission, $code, (int) $member->step, (string) $char->role) : null;
        if (!$game) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        if (($game['type'] ?? '') === 'errors') {
            [$caught, $missed, $wrong, $success] = self::count_errors($game, $events);
        } else if (($game['type'] ?? '') === 'whack') {
            [$caught, $missed, $wrong, $success] = self::count_whack($game, $events);
        } else if (($game['type'] ?? '') === 'venn') {
            [$caught, $missed, $wrong, $success] = self::count_venn($game, $events);
        } else if (($game['type'] ?? '') === 'whoami') {
            [$caught, $missed, $wrong, $success] = self::count_whoami($game, $events);
        } else {
            [$caught, $missed, $wrong, $success] = self::count_nest($game, $events);
        }
        $transaction = $DB->start_delegated_transaction();
        $tries = $DB->count_records(self::T . 'minigame', ['charid' => $char->id, 'courseid' => $courseid, 'game' => $code]) + 1;
        $detail = ['step' => (int) $member->step, 'caught' => $caught, 'missed' => $missed, 'wrong' => $wrong];
        $playrecord = (object) [
            'charid' => $char->id,
            'courseid' => $courseid,
            'cmid' => 0,
            'game' => $code,
            'playid' => $playid !== '' ? $playid : null,
            'success' => $success ? 1 : 0,
            'tries' => $tries,
            'detail' => json_encode($detail),
            'timecreated' => $now,
        ];
        $playrecord->id = $DB->insert_record(self::T . 'minigame', $playrecord);
        $result = ['success' => $success, 'caught' => $caught, 'missed' => $missed, 'goal' => $game['goal'],
            'tries' => $tries, 'first' => false, 'items' => [], 'coat' => false, 'gems' => 0, 'grew' => false];
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
            if (!empty($reward['gems']) && engine::gems($member, (int) $reward['gems'], 'minigame', 0, 0, 0, $now)) {
                $result['gems'] = (int) $reward['gems'];
            }
            // The first minigame won makes the creature grow in the middle of the mission (unless the lanterns did).
            $result['grew'] = scenes::grow_mid($char, $member, $mission, $now);
        }
        if ($playid !== '') {
            $DB->set_field(self::T . 'minigame', 'detail', json_encode($detail + ['result' => $result]), ['id' => $playrecord->id]);
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * Counts a play of Defensa del nido: each animal once, with the net thrown at it (none if it reached the nest).
     *
     * @param array $game As the student plays it.
     * @param array $events Each one: a (the animal) and net.
     * @return array [caught, missed, wrong (animal => net thrown), success]
     */
    private static function count_nest(array $game, array $events): array {
        $right = array_column($game['animals'], 'net', 'id');
        $nets = array_column($game['nets'], 'id');
        $seen = [];
        $caught = 0;
        $missed = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $animal = (string) ($event['a'] ?? '');
            $net = (string) ($event['net'] ?? '');
            if (!isset($right[$animal]) || isset($seen[$animal]) || ($net !== '' && !in_array($net, $nets, true))) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$animal] = true;
            $answers[] = $net === $right[$animal];
            if ($net === $right[$animal]) {
                $caught++;
            } else {
                $missed++;
                if ($net !== '') {
                    $wrong[$animal] = $net;
                }
            }
        }
        // The healer takes out of the nest the animal that got in: one more caught, one less missed.
        $forgiven = self::forgiven($game, $answers);
        return [$caught, $missed, $wrong, $caught + $forgiven >= $game['goal'] && $missed - $forgiven < $game['shield']];
    }

    /**
     * Counts a play of Who is alive?: each thing touched once (i, its id). A living thing touched is caught; a thing
     * touched that is not alive is a slip; a living thing not touched got away.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [caught, slips, wrong (id => touched or away), success]
     */
    private static function count_whack(array $game, array $events): array {
        $alive = array_column($game['things'], 'alive', 'id');
        $touched = [];
        $answers = [];
        foreach ($events as $event) {
            $id = (string) ($event['i'] ?? '');
            if (!isset($alive[$id]) || isset($touched[$id])) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $touched[$id] = true;
            $answers[] = (bool) $alive[$id];
        }
        $caught = 0;
        $slips = 0;
        $wrong = [];
        foreach ($alive as $id => $living) {
            if ($living && isset($touched[$id])) {
                $caught++;
            } else if ($living) {
                $wrong[$id] = 'away';
            } else if (isset($touched[$id])) {
                $slips++;
                $wrong[$id] = 'touched';
            }
        }
        return [$caught, $slips, $wrong, $caught >= $game['goal'] && $slips - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of the two circles: each thing of each sheet once (s, the sheet; i, the thing; z, where it went).
     * Won when everything is in its place with fewer slips than allowed.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [right, slips, wrong (sheet:thing => where it went), success]
     */
    private static function count_venn(array $game, array $events): array {
        $zones = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['items'] as $i => $item) {
                $zones[$sheet['id'] . ':' . $i] = $item['zone'];
            }
        }
        $seen = [];
        $right = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $key = ($event['s'] ?? '') . ':' . ($event['i'] ?? '');
            $zone = (string) ($event['z'] ?? '');
            if (!isset($zones[$key]) || isset($seen[$key]) || !in_array($zone, ['a', 'b', 'ab'], true)) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$key] = true;
            $answers[] = $zone === $zones[$key];
            if ($zone === $zones[$key]) {
                $right++;
            } else {
                $wrong[$key] = $zone;
            }
        }
        $done = count($seen) === count($zones);
        return [$right, count($wrong), $wrong, $done && count($wrong) - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of Who am I?: for each riddle (r), another clue asked for (c) or a guess (g). The first clue shows
     * from the start; a wrong guess shows the next one, and a wrong guess with no clue left lets the shadow get away.
     * Won when every shadow is found with fewer wrong guesses than allowed.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [found, wrong guesses, wrong (riddle => first wrong guess), success]
     */
    private static function count_whoami(array $game, array $events): array {
        $riddles = array_column($game['riddles'], null, 'id');
        $state = [];
        $found = 0;
        $guesses = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $id = (string) ($event['r'] ?? '');
            $riddle = $riddles[$id] ?? null;
            $st = $state[$id] ?? ['shown' => 1, 'over' => false];
            if (!$riddle || $st['over']) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $clues = count($riddle['clues']);
            if (!empty($event['c'])) {
                if ($st['shown'] >= $clues) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $st['shown']++;
            } else {
                $guess = \core_text::strtolower(trim((string) ($event['g'] ?? '')));
                $answers[] = $guess === \core_text::strtolower($riddle['answer']);
                if ($guess === \core_text::strtolower($riddle['answer'])) {
                    $found++;
                    $st['over'] = true;
                } else {
                    $guesses++;
                    $wrong[$id] = $wrong[$id] ?? (string) ($event['g'] ?? '');
                    if ($st['shown'] >= $clues) {
                        $st['over'] = true;
                    } else {
                        $st['shown']++;
                    }
                }
            }
            $state[$id] = $st;
        }
        return [$found, $guesses, $wrong, $found === count($riddles) && $guesses - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of a hunt for mistakes: each mistake found once (s, the sheet, and m, its number) and each blot of
     * ink (s and x, a word touched that was right).
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [found, blots, missed (sheet:mark => 0, the mistakes not found), success]
     */
    private static function count_errors(array $game, array $events): array {
        $marks = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['parts'] as $part) {
                if ($part['mark'] >= 0) {
                    $marks[$sheet['id'] . ':' . $part['mark']] = true;
                }
            }
        }
        $found = [];
        $blots = 0;
        $answers = [];
        $sheets = array_column($game['sheets'], 'id');
        foreach ($events as $event) {
            $sheet = (string) ($event['s'] ?? '');
            if (!in_array($sheet, $sheets, true)) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['m'])) {
                $key = $sheet . ':' . (int) $event['m'];
                if (!isset($marks[$key]) || isset($found[$key])) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $found[$key] = true;
                $answers[] = true;
            } else {
                $blots++;
                $answers[] = false;
            }
        }
        $missed = array_map(fn() => 0, array_diff_key($marks, $found));
        return [count($found), $blots, $missed, count($found) >= $game['goal'] && $blots - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * The slip the healer gets back: after a streak of right answers, the first slip of the play, once. The client
     * counts it the same way, answer by answer.
     *
     * @param array $game As the student plays it, with its passive.
     * @param bool[] $answers Right or not, in the order they were given.
     * @return int 1 or 0.
     */
    private static function forgiven(array $game, array $answers): int {
        if (($game['passive'] ?? '') !== 'healer') {
            return 0;
        }
        $slipped = false;
        $streak = 0;
        foreach ($answers as $right) {
            if (!$right) {
                $slipped = true;
                $streak = 0;
            } else if (++$streak >= $game['streak'] && $slipped) {
                return 1;
            }
        }
        return 0;
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
     * common mix-ups (an animal and the net thrown at it; in a hunt for mistakes, the mistakes that slipped by most).
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
                    $key = ($game['type'] ?? '') === 'errors' ? (string) $animal : $animal . '|' . $net;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
            arsort($counts);
            $confusions = [];
            foreach (array_slice($counts, 0, self::CONFUSIONS, true) as $key => $count) {
                if (($game['type'] ?? '') === 'errors') {
                    $confusions[] = ['text' => self::mistake($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'venn') {
                    $confusions[] = ['text' => self::misplaced($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'whoami') {
                    $confusions[] = ['text' => self::mistaken($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'whack') {
                    [$id, $how] = explode('|', $key);
                    $label = $game['things'][$id]['label'] ?? $id;
                    $label = is_array($label) ? ($label[$mission->variant] ?? $label['gen'] ?? reset($label)) : $label;
                    $string = $how === 'away' ? 'minigame_letgo' : 'minigame_thoughtalive';
                    $confusions[] = ['text' => get_string($string, 'local_oksigeniatesape', $label), 'count' => $count];
                    continue;
                }
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
     * A mistake of a hunt, for the teacher: what the sheet said and what is right.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key sheet:mark
     * @return string
     */
    private static function mistake(\stdClass $mission, array $game, string $key): string {
        [$id, $mark] = array_pad(explode(':', $key, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') !== $id) {
                continue;
            }
            $text = $sheet['text'] ?? '';
            $text = is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
            foreach (self::parts($text) as $part) {
                if ($part['mark'] === (int) $mark) {
                    return '«' . $part['wrong'] . '» → ' . ($part['right'][0] ?? '');
                }
            }
        }
        return $key;
    }

    /**
     * A shadow of Who am I? taken for another, for the teacher: who it was and what they thought.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key riddle|first wrong guess
     * @return string
     */
    private static function mistaken(\stdClass $mission, array $game, string $key): string {
        [$id, $guess] = array_pad(explode('|', $key, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') === $id) {
                $a = (object) ['answer' => self::riddle(self::sheet_text($mission, $sheet))['answer'], 'guess' => $guess];
                return get_string('minigame_whoami_mix', 'local_oksigeniatesape', $a);
            }
        }
        return $key;
    }

    /**
     * A thing of the two circles put in the wrong place, for the teacher: the thing and where it went.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key sheet:thing|zone
     * @return string
     */
    private static function misplaced(\stdClass $mission, array $game, string $key): string {
        [$where, $zone] = array_pad(explode('|', $key, 2), 2, '');
        [$id, $i] = array_pad(explode(':', $where, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') !== $id) {
                continue;
            }
            $venn = self::venn(self::sheet_text($mission, $sheet));
            $item = $venn['items'][(int) $i] ?? null;
            if ($item) {
                $place = $zone === 'ab' ? get_string('minigame_venn_both', 'local_oksigeniatesape') :
                    ($venn['groups'][$zone === 'a' ? 0 : 1] ?? $zone);
                return '«' . $item['label'] . '» → ' . $place;
            }
        }
        return $where;
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
        // By traits (the first step): what the body of the group is covered with, if that net is played.
        $cover = (string) ($game['covers'][$group] ?? '');
        if ($cover !== '' && in_array($cover, $nets, true)) {
            return $cover;
        }
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
