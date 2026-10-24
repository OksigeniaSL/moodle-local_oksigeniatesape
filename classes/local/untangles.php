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
 * The great untangling: the whole class against a huge knot of the Embrollo, on the class screen with the phones. The
 * knot has a strand for each team; the phones of each team get micro-challenges of the burst of the mission, one after
 * the other, and each right answer loosens the strand of the team (a wrong one tightens it a little). A team whose
 * strand is loose helps the team with the tightest one. Five minutes. Each strand loosened weakens the Embrollo for the
 * final battle (expedition::strength). It is a live session of its own kind, `untangle`, with the fields of a battle
 * left empty, so that what reads the sessions of a course goes on working.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class untangles {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string The kind of the live session. */
    public const KIND = 'untangle';

    /** @var int Seconds of the untangling. */
    public const SECS = 300;

    /** @var int Knots of the strand of each team. */
    public const STRAND = 6;

    /**
     * The class screen opens the great untangling (the waiting room), and closes the session that was open, if any.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function start(int $courseid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        if (!$mission || !self::items($mission, 1)) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $DB->set_field_select(self::T . 'live', 'timeend', $now, 'courseid = ? AND timeend = 0', [$courseid]);
        // What a battle has, empty: what reads the sessions of a course goes on working.
        $state = ['mode' => 'phones', 'secs' => 0, 'questions' => [], 'rounds' => [], 'open' => false, 'closesat' => 0,
            'hp' => 0, 'maxhp' => 0, 'outcome' => '', 'rewarded' => [], 'final' => false, 'rehearsal' => false,
            'untangle' => ['phase' => 'lobby', 'startat' => 0, 'endsat' => 0, 'strands' => [], 'served' => [], 'right' => 0,
            'loose' => 0]];
        $id = $DB->insert_record(self::T . 'live', (object) ['courseid' => $courseid, 'kind' => self::KIND,
            'state' => json_encode($state), 'round' => 0, 'timecreated' => $now, 'timemodified' => $now, 'timeend' => 0]);
        return $DB->get_record(self::T . 'live', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The class screen starts it with the teams that have someone in: five minutes from now. Inside the lock of the
     * live sessions of the course (live::locked), as every action of the class screen.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass
     */
    public static function go(\stdClass $live, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $live = $DB->get_record(self::T . 'live', ['id' => $live->id], '*', MUST_EXIST);
        $state = self::state($live);
        $u = $state['untangle'];
        if ($u['phase'] !== 'lobby') {
            return $live;
        }
        $teams = array_unique(array_map('intval', $DB->get_fieldset_select(
            self::T . 'livein',
            'team',
            'liveid = ? AND round = 0 AND team > 0',
            [$live->id]
        )));
        if (!$teams) {
            throw new \moodle_exception('untanglenobody', 'local_oksigeniatesape');
        }
        sort($teams);
        $u['strands'] = array_fill_keys(array_map('strval', $teams), self::STRAND);
        $u['phase'] = 'play';
        $u['startat'] = $now;
        $u['endsat'] = $now + self::SECS;
        $state['untangle'] = $u;
        return self::save($live, $state, $now);
    }

    /**
     * A student answers their micro-challenge: right, the strand of their team (or, loose, of the team they help) loosens;
     * wrong, it tightens a little. Each one goes on with the next.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $number The number of the micro-challenge they saw.
     * @param int $answer The option chosen, as shown.
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function answer(\stdClass $live, \stdClass $member, int $number, int $answer, ?int $now = null): \stdClass {
        $now = $now ?? time();
        return live::locked((int) $live->courseid, function () use ($live, $member, $number, $answer, $now) {
            global $DB;
            $live = $DB->get_record(self::T . 'live', ['id' => $live->id], '*', MUST_EXIST);
            $state = self::state($live);
            $u = $state['untangle'];
            $charid = (string) $member->charid;
            $team = self::team($live, $member);
            if ($u['phase'] !== 'play' || $now >= (int) $u['endsat'] || !isset($u['strands'][(string) $team])) {
                throw new \moodle_exception('untanglenot', 'local_oksigeniatesape');
            }
            $served = (int) ($u['served'][$charid] ?? 0);
            $item = self::item($live, (int) $member->charid, $served);
            if ($number !== $served || !$item || !isset($item['options'][$answer])) {
                throw new \moodle_exception('untanglenot', 'local_oksigeniatesape');
            }
            $right = $answer === (int) $item['right'];
            $target = self::target($u, $team);
            $strand = (int) $u['strands'][(string) $target];
            $u['strands'][(string) $target] = $right ? max(0, $strand - 1) : min(self::STRAND, $strand + 1);
            $u['served'][$charid] = $served + 1;
            $u['right'] += $right ? 1 : 0;
            $u['last'][$charid] = ['right' => $right, 'correct' => (int) $item['right'], 'target' => $target];
            $u['loose'] = count(array_filter($u['strands'], fn($s) => (int) $s === 0));
            $state['untangle'] = $u;
            if ($u['loose'] === count($u['strands'])) {
                return self::finish_state($live, $state, $now);
            }
            return self::save($live, $state, $now);
        });
    }

    /**
     * The class screen ends it, or the time is up: how many strands are loose stays for the final battle. Inside the
     * lock of the live sessions of the course.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass
     */
    public static function finish(\stdClass $live, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $live = $DB->get_record(self::T . 'live', ['id' => $live->id], '*', MUST_EXIST);
        return self::finish_state($live, self::state($live), $now);
    }

    /**
     * Ends the session if its time is up; the screens ask often, so this is how it ends by itself. Inside the lock.
     *
     * @param \stdClass $live
     * @param int $now
     * @return \stdClass
     */
    public static function tick(\stdClass $live, int $now): \stdClass {
        $u = self::state($live)['untangle'] ?? null;
        return $u && $u['phase'] === 'play' && $now >= (int) $u['endsat'] ? self::finish($live, $now) : $live;
    }

    /**
     * The last great untangling of a course that ended: how many strands it loosened, and of how many.
     *
     * @param int $courseid
     * @return array [loose, strands]
     */
    public static function last(int $courseid): array {
        global $DB;
        $rows = $DB->get_records_select(
            self::T . 'live',
            "courseid = ? AND kind = ? AND timeend > 0",
            [$courseid, self::KIND],
            'timeend DESC',
            'id, state',
            0,
            5
        );
        foreach ($rows as $row) {
            $u = self::state($row)['untangle'] ?? [];
            if (($u['phase'] ?? '') === 'end' && !empty($u['strands'])) {
                return [(int) $u['loose'], count($u['strands'])];
            }
        }
        return [0, 0];
    }

    /**
     * What the class screen shows.
     *
     * @param \stdClass $live
     * @param int $now
     * @return array phase, left, strands (team, name, knots, loose), strand, joined, loose, right, outcome.
     */
    public static function board(\stdClass $live, int $now): array {
        global $DB;
        $u = self::state($live)['untangle'];
        $names = [];
        foreach (groups_get_all_groups(games::course((int) $live->courseid)) as $group) {
            $names[(int) $group->id] = format_string($group->name);
        }
        $strands = [];
        foreach ($u['strands'] as $team => $knots) {
            $strands[] = ['team' => (int) $team, 'name' => $names[(int) $team] ?? '', 'knots' => (int) $knots, 'loose' => !$knots];
        }
        return ['phase' => (string) $u['phase'], 'left' => $u['phase'] === 'play' ? max(0, (int) $u['endsat'] - $now) : 0,
            'secs' => self::SECS, 'strand' => self::STRAND, 'strands' => $strands, 'loose' => (int) $u['loose'],
            'right' => (int) $u['right'], 'outcome' => (string) ($u['outcome'] ?? ''),
            'joined' => $DB->count_records(self::T . 'livein', ['liveid' => $live->id, 'round' => 0])];
    }

    /**
     * What the phone of a student shows: their team and its strand, the team they help, their micro-challenge and how
     * the last one went.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $now
     * @return array
     */
    public static function device(\stdClass $live, \stdClass $member, int $now): array {
        $u = self::state($live)['untangle'];
        $board = self::board($live, $now);
        $team = self::team($live, $member);
        $charid = (string) $member->charid;
        $view = ['phase' => $board['phase'], 'left' => $board['left'], 'strand' => self::STRAND, 'myteam' => $team,
            'mine' => (int) ($u['strands'][(string) $team] ?? -1), 'helping' => '', 'number' => -1, 'item' => null,
            'last' => $u['last'][$charid] ?? null, 'loose' => $board['loose'], 'strands' => count($board['strands']),
            'outcome' => $board['outcome']];
        if ($board['phase'] === 'play' && isset($u['strands'][(string) $team])) {
            $target = self::target($u, $team);
            if ($target !== $team) {
                $view['helping'] = (string) (array_column($board['strands'], 'name', 'team')[$target] ?? '');
            }
            $view['number'] = (int) ($u['served'][$charid] ?? 0);
            $item = self::item($live, (int) $member->charid, $view['number']);
            if ($item) {
                $view['item'] = ['word' => (string) $item['word'], 'prompt' => (string) $item['prompt'],
                    'label' => (string) ($item['label'] ?? ''), 'picture' => (string) ($item['picture'] ?? ''),
                    'options' => array_values($item['options'])];
            }
        }
        return $view;
    }

    /**
     * The team a strand of which a student works: their own, or, loose, the tightest one.
     *
     * @param array $u
     * @param int $team
     * @return int
     */
    private static function target(array $u, int $team): int {
        if ((int) ($u['strands'][(string) $team] ?? 0) > 0) {
            return $team;
        }
        $tight = $u['strands'];
        arsort($tight);
        return (int) array_key_first($tight);
    }

    /**
     * The team of a student in the session.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @return int
     */
    private static function team(\stdClass $live, \stdClass $member): int {
        global $DB;
        return (int) $DB->get_field(self::T . 'livein', 'team', ['liveid' => $live->id, 'round' => 0,
            'charid' => $member->charid]);
    }

    /**
     * The micro-challenge number n of a student: from bursts drawn for them in this session, one after the other.
     *
     * @param \stdClass $live
     * @param int $charid
     * @param int $n
     * @return array|null
     */
    private static function item(\stdClass $live, int $charid, int $n): ?array {
        $mission = missions::get((int) $live->courseid);
        if (!$mission) {
            return null;
        }
        $items = self::items($mission, crc32('tesape-untangle-' . $live->id . '-' . $charid . '-' . intdiv($n, 6)));
        return $items ? $items[$n % count($items)] : null;
    }

    /**
     * The micro-challenges of a burst of the mission with options (not the three things at once).
     *
     * @param \stdClass $mission
     * @param int $seed
     * @return array
     */
    private static function items(\stdClass $mission, int $seed): array {
        return array_values(array_filter(
            minigames::class_burst($mission, $seed),
            fn($i) => ($i['kind'] ?? '') !== 'boss' && count($i['options'] ?? []) >= 2
        ));
    }

    /**
     * Ends it: the session is over, and what it loosened stays.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return \stdClass
     */
    private static function finish_state(\stdClass $live, array $state, int $now): \stdClass {
        global $DB;
        $u = $state['untangle'];
        if ($u['phase'] === 'end') {
            return $live;
        }
        $u['phase'] = 'end';
        $u['outcome'] = $u['strands'] && $u['loose'] === count($u['strands']) ? 'won' : ($u['strands'] ? 'time' : 'none');
        $state['untangle'] = $u;
        $state['outcome'] = $u['outcome'] === 'won' ? 'won' : 'lost';
        $live = self::save($live, $state, $now);
        $DB->set_field(self::T . 'live', 'timeend', $now, ['id' => $live->id]);
        $live->timeend = $now;
        return $live;
    }

    /**
     * The state of a session.
     *
     * @param \stdClass $live
     * @return array
     */
    private static function state(\stdClass $live): array {
        $state = json_decode((string) $live->state, true) ?: [];
        $state['untangle'] = ($state['untangle'] ?? []) + ['phase' => 'lobby', 'startat' => 0, 'endsat' => 0, 'strands' => [],
            'served' => [], 'right' => 0, 'loose' => 0, 'last' => []];
        return $state;
    }

    /**
     * Keeps the state of a session.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return \stdClass
     */
    private static function save(\stdClass $live, array $state, int $now): \stdClass {
        global $DB;
        $live->state = json_encode($state);
        $live->timemodified = $now;
        $DB->update_record(self::T . 'live', (object) ['id' => $live->id, 'state' => $live->state, 'timemodified' => $now]);
        return $live;
    }
}
