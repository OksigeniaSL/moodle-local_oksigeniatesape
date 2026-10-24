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
 * The little knots: the fight of the creature of a student with a minion of the Embrollo on the road, in three turns.
 * In each turn the student chooses the net (the idea that undoes what the minion claims) and strikes with a question of
 * its knot: right with the right net, two threads; right with another net, one; wrong, none, and the minion laughs.
 * Then the minion throws a thread and the student moves the creature away from where it falls. Three threads untangle
 * the minion (as before, with its gem); three bonds of the creature caught, or three turns, and it escapes until
 * tomorrow. The creature learns a move by stage: it sniffs a wrong net (stage 2), leaps away from the first thread
 * (stage 3) and strikes double once (stage 4). One fight a day for each minion; the server keeps it and decides.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fights {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Threads of a minion. */
    public const THREADS = 3;

    /** @var int Bonds of the creature. */
    public const BONDS = 3;

    /** @var int Turns before the minion escapes. */
    public const TURNS = 3;

    /** @var int Nets to choose from. */
    public const NETS = 3;

    /** @var int Places where the creature can be. */
    public const HOLES = 3;

    /** @var int Seconds to move the creature away, and a margin for the network. */
    public const DODGE = 4;

    /** @var int Margin of the network. */
    public const MARGIN = 4;

    /** @var array Stage of the creature from which it knows each move. */
    public const MOVES = ['sniff' => 2, 'leap' => 3, 'double' => 4];

    /**
     * A student starts the fight with the minion on their road, or goes on with the one of today.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param string $code The minion.
     * @param int|null $now
     * @return \stdClass The fight.
     */
    public static function start(\stdClass $char, \stdClass $member, string $code, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $courseid = (int) $member->courseid;
        $mission = missions::get($courseid);
        $knot = $mission && $member->status !== 'paused' ? minions::standing($char, $mission, $code) : null;
        if (!$knot) {
            throw new \moodle_exception('nominion', 'local_oksigeniatesape');
        }
        $day = engine::day_start($now, $courseid);
        $fight = $DB->get_record(self::T . 'fight', ['charid' => $char->id, 'minion' => $code, 'day' => $day]);
        if ($fight) {
            if ($fight->outcome !== '') {
                throw new \moodle_exception('miniontried', 'local_oksigeniatesape');
            }
            return $fight;
        }
        $try = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'miniontry', 'code' => $code . '-' . $day];
        if ($DB->record_exists(self::T . 'found', $try)) {
            throw new \moodle_exception('miniontried', 'local_oksigeniatesape');
        }
        $DB->insert_record(self::T . 'found', $try + ['courseid' => $courseid, 'timecreated' => $now]);
        // The questions of its knot, one a turn, and the nets: its weakness and those of two other knots.
        $seed = crc32('tesape-fight-' . $char->id . '-' . $code . '-' . $day);
        $ids = array_values((array) ($knot['questions'] ?? []));
        $questions = [];
        for ($i = 0; $i < self::TURNS && $ids; $i++) {
            $questions[] = (string) $ids[($seed + $i) % count($ids)];
        }
        $others = array_values(array_filter(
            knots::usable($mission),
            fn($k) => $k['code'] !== $code && trim((string) missions::text($k['weakness'] ?? '', $mission->skin)) !== ''
        ));
        usort($others, fn($a, $b) => crc32($seed . $a['code']) <=> crc32($seed . $b['code']));
        $nets = array_merge([$code], array_column(array_slice($others, 0, self::NETS - 1), 'code'));
        usort($nets, fn($a, $b) => crc32($seed . ':' . $a) <=> crc32($seed . ':' . $b));
        $state = ['threads' => self::THREADS, 'bonds' => self::BONDS, 'turn' => 0, 'phase' => 'net',
            'stage' => (int) $char->creaturestage, 'questions' => $questions, 'nets' => $nets, 'net' => -1, 'struck' => [],
            'sniffed' => false, 'leapt' => false, 'doubled' => false, 'tangled' => false, 'aim' => -1, 'dodgeat' => 0,
            'seed' => $seed, 'last' => [], 'log' => [], 'gems' => 0];
        $fight = (object) ['charid' => $char->id, 'courseid' => $courseid, 'missionid' => $mission->id, 'minion' => $code,
            'day' => $day, 'state' => json_encode($state), 'outcome' => '', 'timecreated' => $now, 'timemodified' => $now];
        $fight->id = $DB->insert_record(self::T . 'fight', $fight);
        return $fight;
    }

    /**
     * A step of the student in the fight: sniff, choose the net, strike or move the creature.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $fightid
     * @param string $action sniff, net, strike or dodge.
     * @param int $value The net, the option or the place, as shown.
     * @param int|null $now
     * @return \stdClass The fight.
     */
    public static function act(
        \stdClass $char,
        \stdClass $member,
        int $fightid,
        string $action,
        int $value,
        ?int $now = null
    ): \stdClass {
        global $DB;
        $now = $now ?? time();
        $fight = $DB->get_record(self::T . 'fight', ['id' => $fightid, 'charid' => $char->id, 'courseid' => $member->courseid]);
        if (!$fight || $fight->outcome !== '') {
            throw new \moodle_exception('nominion', 'local_oksigeniatesape');
        }
        $s = json_decode((string) $fight->state, true);
        $wrong = fn() => new \moodle_exception('fightnotnow', 'local_oksigeniatesape');
        if ($action === 'sniff') {
            if ($s['phase'] !== 'net' || $s['sniffed'] || $s['stage'] < self::MOVES['sniff']) {
                throw $wrong();
            }
            // The creature sniffs: a wrong net is struck out.
            foreach ($s['nets'] as $i => $net) {
                if ($net !== $fight->minion && !in_array($i, $s['struck'], true)) {
                    $s['struck'][] = $i;
                    break;
                }
            }
            $s['sniffed'] = true;
        } else if ($action === 'net') {
            if ($s['phase'] !== 'net' || !isset($s['nets'][$value]) || in_array($value, $s['struck'], true)) {
                throw $wrong();
            }
            $s['net'] = $value;
            $s['phase'] = 'strike';
        } else if ($action === 'strike') {
            $q = $s['phase'] === 'strike' ? self::question($fight, $s) : null;
            if (!$q || !isset($q['options'][$value])) {
                throw $wrong();
            }
            $right = $value === (int) $q['correct'];
            $goodnet = $s['nets'][$s['net']] === $fight->minion;
            $cut = $right ? ($goodnet && !$s['tangled'] ? 2 : 1) : 0;
            if ($cut === 2 && !$s['doubled'] && $s['stage'] >= self::MOVES['double']) {
                // The double strike, once a fight.
                $cut = 3;
                $s['doubled'] = true;
            }
            $s['threads'] = max(0, $s['threads'] - $cut);
            $s['tangled'] = false;
            $s['last'] = ['kind' => $right ? ($cut > 1 ? 'hit' : 'weak') : 'miss', 'cut' => $cut, 'goodnet' => $goodnet,
                'correct' => (int) $q['correct'], 'chosen' => $value, 'why' => (string) $q['why']];
            $s['log'][] = ['net' => $s['nets'][$s['net']], 'right' => $right, 'cut' => $cut];
            if ($s['threads'] <= 0) {
                return self::finish($fight, $s, $char, $member, 'won', $now);
            }
            $s['phase'] = 'dodge';
            $s['aim'] = random_int(0, self::HOLES - 1);
            $s['dodgeat'] = $now;
        } else if ($action === 'dodge') {
            if ($s['phase'] !== 'dodge' || $value < 0 || $value >= self::HOLES) {
                throw $wrong();
            }
            $late = !adapt::has($member, 'notime') && $now - (int) $s['dodgeat'] > self::DODGE + self::MARGIN;
            $caught = $value === (int) $s['aim'] || $late;
            $kind = $caught ? 'caught' : 'dodged';
            if ($caught && !$s['leapt'] && $s['stage'] >= self::MOVES['leap']) {
                // The creature leaps away from the first thread.
                $s['leapt'] = true;
                $caught = false;
                $kind = 'leapt';
            }
            if ($caught) {
                $s['bonds']--;
                $s['tangled'] = true;
            }
            $s['last'] = ['kind' => $kind, 'aim' => (int) $s['aim'], 'chosen' => $value];
            $s['log'][count($s['log']) - 1]['dodge'] = $kind;
            $s['turn']++;
            if ($s['bonds'] <= 0 || $s['turn'] >= self::TURNS) {
                return self::finish($fight, $s, $char, $member, 'escaped', $now);
            }
            $s['phase'] = 'net';
            $s['net'] = -1;
            $s['struck'] = [];
            $s['aim'] = -1;
        } else {
            throw new \invalid_parameter_exception('Unknown action');
        }
        return self::save($fight, $s, '', $now);
    }

    /**
     * The fight ends: untangled, with its gems, or escaped until tomorrow.
     *
     * @param \stdClass $fight
     * @param array $s
     * @param \stdClass $char
     * @param \stdClass $member
     * @param string $outcome won or escaped.
     * @param int $now
     * @return \stdClass
     */
    private static function finish(\stdClass $fight, array $s, \stdClass $char, \stdClass $member, string $outcome, int $now): \stdClass {
        $s['phase'] = 'end';
        if ($outcome === 'won') {
            $mission = missions::get((int) $fight->courseid);
            $knot = $mission ? knots::get($mission, (string) $fight->minion) : null;
            if ($knot) {
                $s['gems'] = minions::untangle($char, $member, $mission, ['code' => $fight->minion], $now);
            }
        }
        return self::save($fight, $s, $outcome, $now);
    }

    /**
     * The fight as the tablet of the student shows it.
     *
     * @param \stdClass $fight
     * @return array
     */
    public static function view(\stdClass $fight): array {
        $s = json_decode((string) $fight->state, true);
        $mission = missions::get((int) $fight->courseid);
        $knot = $mission ? knots::get($mission, (string) $fight->minion) : null;
        $nets = [];
        foreach ($s['nets'] as $i => $code) {
            $k = $mission ? knots::get($mission, (string) $code) : null;
            $nets[] = ['text' => $k ? (string) $k['weakness'] : '', 'struck' => in_array($i, $s['struck'], true)];
        }
        $q = $s['phase'] === 'strike' ? self::question($fight, $s) : null;
        $moves = array_keys(array_filter(self::MOVES, fn($stage) => $s['stage'] >= $stage));
        $last = $s['last'] + ['kind' => '', 'cut' => 0, 'goodnet' => false, 'correct' => -1, 'chosen' => -1, 'why' => '',
            'aim' => -1];
        return [
            'id' => (int) $fight->id,
            'minion' => (string) $fight->minion,
            'name' => $knot ? (string) $knot['name'] : '',
            'claim' => $knot ? (string) $knot['claim'] : '',
            'laugh' => $knot ? (string) $knot['laugh'] : '',
            'picture' => 'minion.' . $fight->minion,
            'phase' => (string) $s['phase'],
            'threads' => (int) $s['threads'],
            'maxthreads' => self::THREADS,
            'bonds' => (int) $s['bonds'],
            'maxbonds' => self::BONDS,
            'turn' => (int) $s['turn'],
            'turns' => self::TURNS,
            'moves' => $moves,
            'cansniff' => $s['phase'] === 'net' && !$s['sniffed'] && in_array('sniff', $moves, true),
            'tangled' => (bool) $s['tangled'],
            'nets' => $nets,
            'net' => $s['net'] >= 0 ? $nets[$s['net']]['text'] : '',
            'question' => $q ? (string) $q['text'] : '',
            'options' => $q ? array_values($q['options']) : [],
            'aim' => $s['phase'] === 'dodge' ? (int) $s['aim'] : -1,
            'holes' => self::HOLES,
            'dodge' => self::DODGE,
            'last' => ['kind' => (string) $last['kind'], 'cut' => (int) $last['cut'], 'goodnet' => (bool) $last['goodnet'],
                'correct' => (int) $last['correct'], 'chosen' => (int) $last['chosen'], 'why' => (string) $last['why'],
                'aim' => (int) $last['aim']],
            'outcome' => (string) $fight->outcome,
            'gems' => (int) $s['gems'],
            'weakness' => $fight->outcome === 'won' && $knot ? (string) $knot['weakness'] : '',
        ];
    }

    /**
     * The question of the turn at hand, with its options in the order of this fight and turn.
     *
     * @param \stdClass $fight
     * @param array $s
     * @return array|null As questions::get().
     */
    private static function question(\stdClass $fight, array $s): ?array {
        $id = $s['questions'][$s['turn']] ?? null;
        return $id ? questions::get((int) $fight->courseid, (string) $id, (int) $s['seed'] + (int) $s['turn']) : null;
    }

    /**
     * Keeps a fight.
     *
     * @param \stdClass $fight
     * @param array $s
     * @param string $outcome
     * @param int $now
     * @return \stdClass
     */
    private static function save(\stdClass $fight, array $s, string $outcome, int $now): \stdClass {
        global $DB;
        $fight->state = json_encode($s);
        $fight->outcome = $outcome;
        $fight->timemodified = $now;
        $DB->update_record(self::T . 'fight', (object) ['id' => $fight->id, 'state' => $fight->state, 'outcome' => $outcome,
            'timemodified' => $now]);
        return $fight;
    }
}
