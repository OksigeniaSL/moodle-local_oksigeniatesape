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
 * Defensa del nido on the class screen, for one team, with each tablet as a different control: the animals come down to
 * the nest on the class screen, one at a time, and the nets of the game are shared out among the tablets of the team.
 * Only who has the right net can catch the animal, so the team has to talk: whose net is it? A wrong net, or no net in
 * time, and the animal gets into the nest, which loses one of its eggs of warmth. Won with an egg left at the end: a gem
 * for each one, once a day. A live session of its own kind, `nest`, with the fields of a battle left empty.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class nestboards {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string The kind of the live session. */
    public const KIND = 'nest';

    /** @var string The game of the mission it plays. */
    public const GAME = 'nestdefence';

    /** @var int Animals that come down. */
    public const ANIMALS = 8;

    /** @var int Eggs of the nest. */
    public const EGGS = 3;

    /** @var int Seconds for each animal. */
    public const SECS = 15;

    /** @var int Gems for each one when the nest is saved, once a day. */
    public const GEMS = 1;

    /**
     * The class screen opens the nest for a team (the waiting room), and closes the session that was open, if any.
     *
     * @param int $courseid
     * @param int $groupid The team.
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function start(int $courseid, int $groupid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $game = $mission ? minigames::config($mission, self::GAME, 1) : null;
        $group = groups_get_group($groupid);
        if (!$game || !$group || (int) $group->courseid !== games::course($courseid)) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $DB->set_field_select(self::T . 'live', 'timeend', $now, 'courseid = ? AND timeend = 0', [$courseid]);
        $state = ['mode' => 'phones', 'secs' => 0, 'questions' => [], 'rounds' => [], 'open' => false, 'closesat' => 0,
            'hp' => 0, 'maxhp' => 0, 'outcome' => '', 'rewarded' => [], 'final' => false, 'rehearsal' => false,
            'nest' => ['phase' => 'lobby', 'team' => $groupid, 'animals' => [], 'hands' => [], 'index' => 0, 'caught' => 0,
            'missed' => 0, 'eggs' => self::EGGS, 'turnat' => 0, 'last' => null, 'outcome' => '', 'gems' => []]];
        $id = $DB->insert_record(self::T . 'live', (object) ['courseid' => $courseid, 'kind' => self::KIND,
            'state' => json_encode($state), 'round' => 0, 'timecreated' => $now, 'timemodified' => $now, 'timeend' => 0]);
        return $DB->get_record(self::T . 'live', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The class screen starts it with the tablets of the team that came in: the nets are shared out among them. Inside
     * the lock of the live sessions of the course.
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
        $n = $state['nest'];
        if ($n['phase'] !== 'lobby') {
            return $live;
        }
        $players = array_map('intval', self::players($live, (int) $n['team']));
        if (!$players) {
            throw new \moodle_exception('untanglenobody', 'local_oksigeniatesape');
        }
        $game = minigames::config(missions::get((int) $live->courseid), self::GAME, 1);
        $animals = array_column($game['animals'], 'id');
        usort($animals, fn($a, $b) => crc32($live->id . $a) <=> crc32($live->id . $b));
        $nets = array_column($game['nets'], 'id');
        // Every net in a hand; with more tablets than nets, some nets in two hands.
        $hands = array_fill_keys(array_map('strval', $players), []);
        for ($i = 0; $i < max(count($nets), count($players)); $i++) {
            $hands[(string) $players[$i % count($players)]][] = $nets[$i % count($nets)];
        }
        $n['animals'] = array_slice($animals, 0, self::ANIMALS);
        $n['hands'] = array_map(fn($h) => array_values(array_unique($h)), $hands);
        $n['phase'] = 'play';
        $n['turnat'] = $now;
        $state['nest'] = $n;
        return self::save($live, $state, $now);
    }

    /**
     * A tablet throws one of its nets at the animal on the class screen.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param string $net
     * @param int $index The animal they saw.
     * @param int|null $now
     * @return \stdClass
     */
    public static function throw(\stdClass $live, \stdClass $member, string $net, int $index, ?int $now = null): \stdClass {
        $now = $now ?? time();
        return live::locked((int) $live->courseid, function () use ($live, $member, $net, $index, $now) {
            global $DB;
            $live = $DB->get_record(self::T . 'live', ['id' => $live->id], '*', MUST_EXIST);
            $state = self::state($live);
            $n = $state['nest'];
            $hand = $n['hands'][(string) $member->charid] ?? [];
            if ($n['phase'] !== 'play' || $index !== (int) $n['index'] || !in_array($net, $hand, true)) {
                throw new \moodle_exception('nestnotnow', 'local_oksigeniatesape');
            }
            return self::resolve($live, $state, $net, (int) $member->charid, $now);
        });
    }

    /**
     * The animal of the turn gets into the nest if nobody threw a net in time. Inside the lock.
     *
     * @param \stdClass $live
     * @param int $now
     * @return \stdClass
     */
    public static function tick(\stdClass $live, int $now): \stdClass {
        $state = self::state($live);
        $n = $state['nest'];
        if ($n['phase'] === 'play' && $now >= (int) $n['turnat'] + self::SECS) {
            return self::resolve($live, $state, '', 0, $now);
        }
        return $live;
    }

    /**
     * The class screen ends it now. Inside the lock.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass
     */
    public static function finish(\stdClass $live, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $live = $DB->get_record(self::T . 'live', ['id' => $live->id], '*', MUST_EXIST);
        $state = self::state($live);
        return self::end($live, $state, $now);
    }

    /**
     * What the class screen shows: the team, the animal, who holds each net, the eggs and how the last one went.
     *
     * @param \stdClass $live
     * @param int $now
     * @return array
     */
    public static function board(\stdClass $live, int $now): array {
        global $DB;
        $n = self::state($live)['nest'];
        $game = self::game($live);
        $animals = array_column($game['animals'] ?? [], null, 'id');
        $nets = array_column($game['nets'] ?? [], 'label', 'id');
        $group = groups_get_group((int) $n['team']);
        $holders = [];
        foreach ($n['hands'] as $charid => $hand) {
            foreach ($hand as $net) {
                $holders[$net][] = self::name($live, (int) $charid);
            }
        }
        $current = $n['phase'] === 'play' ? ($animals[$n['animals'][$n['index']] ?? ''] ?? null) : null;
        $joined = array_map(fn($c) => self::name($live, (int) $c), self::players($live, (int) $n['team']));
        return ['phase' => (string) $n['phase'], 'team' => $group ? format_string($group->name) : '',
            'animal' => $current ? ['id' => (string) $current['id'], 'label' => (string) $current['label']] : null,
            'index' => (int) $n['index'], 'total' => count($n['animals']), 'left' => $current ? max(0, (int) $n['turnat'] +
            self::SECS - $now) : 0, 'secs' => self::SECS, 'eggs' => (int) $n['eggs'], 'maxeggs' => self::EGGS,
            'caught' => (int) $n['caught'], 'missed' => (int) $n['missed'], 'joined' => $joined,
            'nets' => array_values(array_map(fn($id, $label) => ['id' => $id, 'label' => $label,
                'who' => implode(', ', $holders[$id] ?? [])], array_keys($nets), $nets)),
            'last' => self::last($n, $animals, $nets, $live), 'outcome' => (string) $n['outcome']];
    }

    /**
     * What the tablet of a student of the team shows: their nets, the animal and how the last one went; null for the
     * others.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $now
     * @return array|null
     */
    public static function device(\stdClass $live, \stdClass $member, int $now): ?array {
        $n = self::state($live)['nest'];
        global $DB;
        $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
        if ((int) (teams::of((int) $live->courseid, $userid)->id ?? 0) !== (int) $n['team']) {
            return null;
        }
        $board = self::board($live, $now);
        $nets = array_column($board['nets'], 'label', 'id');
        $hand = $n['hands'][(string) $member->charid] ?? [];
        return ['phase' => $board['phase'], 'team' => $board['team'], 'animal' => $board['animal'], 'index' => $board['index'],
            'total' => $board['total'], 'eggs' => $board['eggs'], 'maxeggs' => self::EGGS, 'left' => $board['left'],
            'nets' => array_map(fn($id) => ['id' => $id, 'label' => $nets[$id] ?? $id], $hand), 'last' => $board['last'],
            'outcome' => $board['outcome'], 'gem' => in_array((int) $member->charid, $n['gems'], true)];
    }

    /**
     * The net thrown (none if the time ran out): caught, or into the nest; then the next animal or the end.
     *
     * @param \stdClass $live
     * @param array $state
     * @param string $net
     * @param int $by
     * @param int $now
     * @return \stdClass
     */
    private static function resolve(\stdClass $live, array $state, string $net, int $by, int $now): \stdClass {
        $n = $state['nest'];
        $game = self::game($live);
        $animals = array_column($game['animals'], null, 'id');
        $animal = $animals[$n['animals'][$n['index']]] ?? null;
        $right = $animal && $net === (string) $animal['net'];
        if ($right) {
            $n['caught']++;
        } else {
            $n['missed']++;
            $n['eggs'] = max(0, $n['eggs'] - 1);
        }
        $n['last'] = ['animal' => $animal ? (string) $animal['id'] : '', 'net' => $net, 'by' => $by, 'right' => $right];
        $n['index']++;
        $n['turnat'] = $now;
        $state['nest'] = $n;
        if ($n['eggs'] <= 0 || $n['index'] >= count($n['animals'])) {
            return self::end($live, $state, $now);
        }
        return self::save($live, $state, $now);
    }

    /**
     * Ends it: saved with an egg left, and a gem for each one who played, once a day.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return \stdClass
     */
    private static function end(\stdClass $live, array $state, int $now): \stdClass {
        global $DB;
        $n = $state['nest'];
        if ($n['phase'] === 'end') {
            return $live;
        }
        $saved = $n['phase'] === 'play' && $n['eggs'] > 0;
        $n['phase'] = 'end';
        $n['outcome'] = $saved ? 'won' : ($n['hands'] ? 'lost' : 'none');
        if ($saved) {
            $today = engine::day_start($now, (int) $live->courseid);
            foreach (array_keys($n['hands']) as $charid) {
                $where = "charid = ? AND courseid = ? AND kind = 'nestboard' AND timecreated >= ?";
                if ($DB->record_exists_select(self::T . 'found', $where, [$charid, $live->courseid, $today])) {
                    continue;
                }
                $char = $DB->get_record(self::T . 'char', ['id' => $charid]);
                $member = $char ? engine::member($char, (int) $live->courseid, $now) : null;
                $DB->insert_record(self::T . 'found', (object) ['charid' => $charid, 'courseid' => $live->courseid,
                    'missionid' => (int) (missions::get((int) $live->courseid)->id ?? 0), 'kind' => 'nestboard',
                    'code' => (string) $live->id, 'timecreated' => $now]);
                if ($member && engine::gems($member, self::GEMS, 'nestboard', 0, 0, (int) $live->id, $now)) {
                    $n['gems'][] = (int) $charid;
                }
            }
        }
        $state['nest'] = $n;
        $state['outcome'] = $saved ? 'won' : 'lost';
        $live = self::save($live, $state, $now);
        $DB->set_field(self::T . 'live', 'timeend', $now, ['id' => $live->id]);
        $live->timeend = $now;
        return $live;
    }

    /**
     * How the last animal went, in words for the screens.
     *
     * @param array $n
     * @param array $animals
     * @param array $nets
     * @param \stdClass $live
     * @return array|null animal, net, by, right, hint (the net it needed and why).
     */
    private static function last(array $n, array $animals, array $nets, \stdClass $live): ?array {
        $last = $n['last'];
        if (!$last) {
            return null;
        }
        $animal = $animals[$last['animal']] ?? null;
        $game = self::game($live);
        $hints = array_column($game['nets'] ?? [], 'hint', 'id');
        return ['animal' => $animal ? (string) $animal['label'] : '', 'net' => (string) ($nets[$last['net']] ?? ''),
            'by' => $last['by'] ? self::name($live, (int) $last['by']) : '', 'right' => (bool) $last['right'],
            'needed' => $animal ? (string) ($nets[$animal['net']] ?? '') : '',
            'hint' => $animal ? (string) ($animal['why'] ?: ($hints[$animal['net']] ?? '')) : ''];
    }

    /**
     * The characters of a team who came in, in the order they came.
     *
     * @param \stdClass $live
     * @param int $team
     * @return int[]
     */
    private static function players(\stdClass $live, int $team): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_sql(
            'SELECT charid FROM {' . self::T . 'livein} WHERE liveid = ? AND round = 0 AND team = ? ORDER BY id',
            [$live->id, $team]
        ));
    }

    /**
     * The game of the mission, as for the first step.
     *
     * @param \stdClass $live
     * @return array
     */
    private static function game(\stdClass $live): array {
        $mission = missions::get((int) $live->courseid);
        return ($mission ? minigames::config($mission, self::GAME, 1) : null) ?? [];
    }

    /**
     * The name of a character's student, as classmates see it.
     *
     * @param \stdClass $live
     * @param int $charid
     * @return string
     */
    private static function name(\stdClass $live, int $charid): string {
        global $DB;
        $char = $DB->get_record(self::T . 'char', ['id' => $charid]);
        $user = $char ? \core_user::get_user((int) $char->userid) : null;
        $region = regions::get((int) $live->courseid);
        return $user && $region ? view::display_name($user, $char, $region) : '';
    }

    /**
     * The state of a session.
     *
     * @param \stdClass $live
     * @return array
     */
    private static function state(\stdClass $live): array {
        $state = json_decode((string) $live->state, true) ?: [];
        $state['nest'] = ($state['nest'] ?? []) + ['phase' => 'lobby', 'team' => 0, 'animals' => [], 'hands' => [],
            'index' => 0, 'caught' => 0, 'missed' => 0, 'eggs' => self::EGGS, 'turnat' => 0, 'last' => null, 'outcome' => '',
            'gems' => []];
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
