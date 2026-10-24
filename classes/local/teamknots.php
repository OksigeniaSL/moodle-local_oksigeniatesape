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
 * The knot of a team, without the class screen: the tablets of a team (a corner of the classroom, a reinforcement
 * session) face some knots of the Embrollo on their own, with the battle of the class at the scale of the team. It is a
 * team session (huddles) without a scene. Turn by turn, one of them answers the question of a knot and the others help
 * with the verb of their role: the sage strikes out a wrong option, the scout shows its weakness, the protector shields
 * the candle and the healer cheers for a second try. Every right answer cuts a thread; a wrong one without a shield blows
 * a candle. Won (60 % of the threads cut, and a candle still lit), a gem for each one, once a day.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teamknots {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Candles of the team. */
    public const CANDLES = 2;

    /** @var int Fewest turns: with fewer players, someone answers twice. */
    public const FEWEST = 3;

    /** @var int Most turns. */
    public const MOST = 5;

    /** @var float Share of the threads to cut to win. */
    public const TO_WIN = 0.6;

    /** @var int Gems for each one when the team wins, once a day. */
    public const GEMS = 1;

    /**
     * Whether a team session is a knot of the team (not a scene).
     *
     * @param \stdClass $huddle
     * @return bool
     */
    public static function is_knot(\stdClass $huddle): bool {
        return (int) $huddle->sceneid === 0;
    }

    /**
     * Whether a knot of the team is over.
     *
     * @param \stdClass $huddle
     * @return bool
     */
    public static function over(\stdClass $huddle): bool {
        return (self::state($huddle)['knot']['phase'] ?? '') === 'end';
    }

    /**
     * A student opens a knot for their team, or comes into the one already open.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function open(int $courseid, int $userid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        $team = $char ? teams::of($courseid, $userid) : null;
        if (!$team) {
            throw new \moodle_exception('powernoteam', 'local_oksigeniatesape');
        }
        if (engine::member($char, $courseid, $now)->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        if (!knots::plan($courseid, 1)) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $open = huddles::current($courseid, (int) $team->id, $now);
        if ($open && !self::is_knot($open)) {
            throw new \moodle_exception('teamknotbusy', 'local_oksigeniatesape');
        }
        if ($open && self::over($open)) {
            $open = null;
        }
        if (!$open) {
            // One session a team: the old ones go.
            $old = $DB->get_fieldset_select(self::T . 'huddle', 'id', 'courseid = ? AND groupid = ?', [$courseid, $team->id]);
            if ($old) {
                [$in, $params] = $DB->get_in_or_equal($old);
                $DB->delete_records_select(self::T . 'huddlein', "huddleid $in", $params);
                $DB->delete_records_select(self::T . 'huddle', "id $in", $params);
            }
            $open = (object) ['courseid' => $courseid, 'groupid' => $team->id, 'sceneid' => 0, 'charid' => $char->id,
                'state' => json_encode(['started' => false, 'seq' => 0, 'node' => null, 'pending' => null, 'players' => [],
                'knot' => ['phase' => 'lobby']]), 'status' => 'open', 'timecreated' => $now, 'timemodified' => $now];
            $open->id = $DB->insert_record(self::T . 'huddle', $open);
        }
        huddles::join($courseid, $userid, $now);
        return $DB->get_record(self::T . 'huddle', ['id' => $open->id], '*', MUST_EXIST);
    }

    /**
     * Someone in starts the knot with those who are in: one turn each (three at least, five at most), with the knots of
     * the places the class has reached, those the class mixes up last.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $now
     */
    public static function start(\stdClass $huddle, \stdClass $char, int $now): void {
        $state = self::state($huddle);
        $present = array_column(huddles::present($huddle, $now), 'userid');
        if (($state['knot']['phase'] ?? '') !== 'lobby' || !in_array((int) $char->userid, $present, true)) {
            throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
        }
        if (count($present) < 2) {
            throw new \moodle_exception('teamknotfew', 'local_oksigeniatesape');
        }
        $courseid = (int) $huddle->courseid;
        $rounds = max(self::FEWEST, min(self::MOST, count($present)));
        $known = array_column(expedition::known($courseid), 'code');
        $plan = array_values(array_filter(
            knots::plan($courseid, $rounds, false, $known),
            fn($p) => questions::get($courseid, (string) $p[1], (int) $huddle->id) !== null
        ));
        if (!$plan) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $state['started'] = true;
        $state['players'] = $present;
        $state['knot'] = ['phase' => 'round', 'round' => 0, 'plan' => $plan, 'cut' => 0, 'candles' => self::CANDLES,
            'help' => [], 'struck' => [], 'cheered' => false, 'last' => '', 'log' => [], 'outcome' => '', 'rewarded' => []];
        self::save($huddle, $state, $now);
    }

    /**
     * Who does not answer this turn helps with the verb of their role, once a turn each verb.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param string $verb
     * @param int $round The turn they saw.
     * @param int $now
     */
    public static function help(\stdClass $huddle, \stdClass $char, string $verb, int $round, int $now): void {
        $state = self::state($huddle);
        $k = $state['knot'];
        $userid = (int) $char->userid;
        if (
            ($k['phase'] ?? '') !== 'round' || (int) $k['round'] !== $round || self::hand($state) === $userid ||
            !in_array($userid, $state['players'], true) || (live::TOKENS[$char->role] ?? '') !== $verb ||
            isset($k['help'][$verb])
        ) {
            throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
        }
        $k['help'][$verb] = $userid;
        if ($verb === 'glance') {
            // The sage strikes out a wrong option.
            $q = self::question($huddle, $k);
            foreach (array_keys($q['options']) as $i) {
                if ($i !== (int) $q['correct'] && !in_array($i, $k['struck'], true)) {
                    $k['struck'][] = $i;
                    break;
                }
            }
        }
        $state['knot'] = $k;
        self::save($huddle, $state, $now);
    }

    /**
     * Who has the turn answers: a thread cut, a second try if the healer cheered, a candle shielded or blown; then the
     * next turn, or the end.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $option Position of the option chosen.
     * @param int $round The turn they saw.
     * @param int $now
     */
    public static function answer(\stdClass $huddle, \stdClass $char, int $option, int $round, int $now): void {
        $state = self::state($huddle);
        $k = $state['knot'];
        if (
            ($k['phase'] ?? '') !== 'round' || (int) $k['round'] !== $round || self::hand($state) !== (int) $char->userid ||
            in_array($option, $k['struck'], true)
        ) {
            throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
        }
        $q = self::question($huddle, $k);
        if (!isset($q['options'][$option])) {
            throw new \invalid_parameter_exception('Not an option');
        }
        $code = (string) $k['plan'][$round][0];
        if ($option === (int) $q['correct']) {
            $k['cut']++;
            $k['last'] = 'right';
        } else if (isset($k['help']['cheer']) && !$k['cheered']) {
            // The healer cheered: one more try, without the option chosen.
            $k['cheered'] = true;
            $k['struck'][] = $option;
            $k['last'] = 'again';
            $state['knot'] = $k;
            self::save($huddle, $state, $now);
            return;
        } else if (isset($k['help']['shield'])) {
            $k['last'] = 'shielded';
        } else {
            $k['candles']--;
            $k['last'] = 'blown';
        }
        $k['log'][] = ['knot' => $code, 'result' => $k['last']];
        $rounds = count($k['plan']);
        if ($k['candles'] <= 0 || $round + 1 >= $rounds) {
            $won = $k['candles'] > 0 && $k['cut'] >= (int) ceil($rounds * self::TO_WIN);
            $k['phase'] = 'end';
            $k['outcome'] = $won ? 'won' : 'lost';
            if ($won) {
                $k['rewarded'] = self::reward($huddle, $state['players'], $now);
            }
        } else {
            $k['round'] = $round + 1;
            $k['help'] = [];
            $k['struck'] = [];
            $k['cheered'] = false;
        }
        $state['knot'] = $k;
        self::save($huddle, $state, $now);
    }

    /**
     * The team won: a gem for each one who played, if they had none from a knot of the team today.
     *
     * @param \stdClass $huddle
     * @param int[] $players
     * @param int $now
     * @return int[] Those who got it.
     */
    private static function reward(\stdClass $huddle, array $players, int $now): array {
        global $DB;
        $courseid = (int) $huddle->courseid;
        $mission = missions::get($courseid);
        $today = usergetmidnight($now);
        $given = [];
        foreach ($players as $userid) {
            $char = characters::get((int) $userid);
            if (!$char) {
                continue;
            }
            $where = "charid = ? AND courseid = ? AND kind = 'teamknot' AND timecreated >= ?";
            if ($DB->record_exists_select(self::T . 'found', $where, [$char->id, $courseid, $today])) {
                continue;
            }
            $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'missionid' => $mission ? (int) $mission->id : 0, 'kind' => 'teamknot', 'code' => (string) $huddle->id,
                'timecreated' => $now]);
            if (engine::gems(engine::member($char, $courseid, $now), self::GEMS, 'teamknot', 0, 0, (int) $huddle->id, $now)) {
                $given[] = (int) $userid;
            }
        }
        return $given;
    }

    /**
     * The knot as a tablet of the team sees it.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $now
     * @return array phase, round, rounds, cut, towin, candles, maxcandles, hand, handname, myturn, myverb, canhelp,
     *     helped (verb, name), name, claim, weakness, question, options (text, struck), last, outcome, gem, log.
     */
    public static function view(\stdClass $huddle, \stdClass $char, int $now): array {
        $state = self::state($huddle);
        $k = $state['knot'];
        $phase = (string) ($k['phase'] ?? 'lobby');
        $userid = (int) $char->userid;
        $verb = (string) (live::TOKENS[$char->role] ?? '');
        $view = ['phase' => $phase, 'round' => (int) ($k['round'] ?? 0), 'rounds' => count($k['plan'] ?? []),
            'cut' => (int) ($k['cut'] ?? 0), 'towin' => (int) ceil(count($k['plan'] ?? []) * self::TO_WIN),
            'candles' => (int) ($k['candles'] ?? self::CANDLES), 'maxcandles' => self::CANDLES, 'hand' => 0, 'handname' => '',
            'myturn' => false, 'myverb' => $verb, 'canhelp' => false, 'helped' => [], 'name' => '', 'claim' => '',
            'weakness' => '', 'question' => '', 'options' => [], 'last' => (string) ($k['last'] ?? ''),
            'outcome' => (string) ($k['outcome'] ?? ''), 'gem' => in_array($userid, $k['rewarded'] ?? [], true),
            'player' => in_array($userid, $state['players'], true), 'log' => []];
        $mission = missions::get((int) $huddle->courseid);
        if ($mission) {
            foreach ($k['log'] ?? [] as $entry) {
                $knot = knots::get($mission, (string) $entry['knot']);
                $view['log'][] = ['name' => $knot ? $knot['name'] : '', 'result' => (string) $entry['result']];
            }
        }
        if ($phase !== 'round' || !$mission) {
            return $view;
        }
        $hand = self::hand($state);
        $knot = knots::get($mission, (string) $k['plan'][$view['round']][0]);
        $q = self::question($huddle, $k);
        foreach ($k['help'] as $v => $by) {
            $view['helped'][] = ['verb' => (string) $v, 'name' => self::name((int) $by, (int) $huddle->courseid)];
        }
        $view = array_merge($view, [
            'hand' => $hand,
            'handname' => self::name($hand, (int) $huddle->courseid),
            'myturn' => $hand === $userid,
            'canhelp' => $view['player'] && $hand !== $userid && $verb !== '' && !isset($k['help'][$verb]),
            'name' => $knot ? $knot['name'] : '',
            'claim' => $knot ? $knot['claim'] : '',
            'weakness' => $knot && isset($k['help']['trail']) ? $knot['weakness'] : '',
            'question' => (string) $q['text'],
            'options' => array_map(
                fn($i, $o) => ['text' => (string) $o, 'struck' => in_array($i, $k['struck'], true)],
                array_keys($q['options']),
                $q['options']
            ),
        ]);
        return $view;
    }

    /**
     * Who answers the turn at hand: the players in their order, round and round.
     *
     * @param array $state
     * @return int
     */
    private static function hand(array $state): int {
        $players = array_values(array_map('intval', $state['players'] ?? []));
        return $players ? $players[(int) ($state['knot']['round'] ?? 0) % count($players)] : 0;
    }

    /**
     * The question of the turn at hand, with its options in the order of this session and turn.
     *
     * @param \stdClass $huddle
     * @param array $k The knot.
     * @return array
     */
    private static function question(\stdClass $huddle, array $k): array {
        $round = (int) $k['round'];
        $q = questions::get((int) $huddle->courseid, (string) $k['plan'][$round][1], (int) $huddle->id * 10 + $round);
        if (!$q) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        return $q;
    }

    /**
     * The name of a student, as classmates see it.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    private static function name(int $userid, int $courseid): string {
        $user = \core_user::get_user($userid);
        $region = regions::get($courseid);
        return $user && $region ? view::display_name($user, characters::get($userid), $region) : '';
    }

    /**
     * The state of a session.
     *
     * @param \stdClass $huddle
     * @return array
     */
    private static function state(\stdClass $huddle): array {
        $state = (json_decode((string) $huddle->state, true) ?: []) + ['started' => false, 'players' => [], 'knot' => []];
        $state['players'] = array_values(array_map('intval', $state['players']));
        $state['knot'] += ['phase' => 'lobby', 'help' => [], 'struck' => [], 'cheered' => false, 'log' => [], 'rewarded' => []];
        return $state;
    }

    /**
     * Keeps the state of a session: whatever any of them does keeps it alive.
     *
     * @param \stdClass $huddle
     * @param array $state
     * @param int $now
     */
    private static function save(\stdClass $huddle, array $state, int $now): void {
        global $DB;
        $huddle->state = json_encode($state);
        $huddle->timemodified = $now;
        $DB->update_record(self::T . 'huddle', (object) ['id' => $huddle->id, 'state' => $huddle->state, 'timemodified' => $now]);
    }
}
