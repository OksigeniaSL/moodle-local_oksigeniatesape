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
 * Team sessions: the tablets of a team joined to a scene. The scene runs on the tablet of who opened it (the guide), as a
 * team play on one device does; the others are the hand: they see each decision to choose, and who decides, and the one
 * whose turn it is chooses on their own tablet. What they choose waits here until the guide takes it and plays it.
 * Only decisions to choose travel; the rest stays on the guide.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class huddles {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Seconds without a sign of the guide before the session is gone. */
    public const ALIVE = 120;

    /** @var int Seconds without a sign of a tablet before it counts as gone (and the hand passes on). */
    public const PRESENT = 20;

    /**
     * The guide opens a session of their team for a scene, or finds the one already open.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $sceneid
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function open(int $courseid, int $userid, int $sceneid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        [$char, $team] = self::student($courseid, $userid, $now);
        $scene = scenes::playable($courseid, $sceneid);
        if (($scene->data['mode'] ?? 'both') === 'solo') {
            throw new \moodle_exception('huddlesolo', 'local_oksigeniatesape');
        }
        $open = self::current($courseid, (int) $team->id, $now);
        if ($open && (int) $open->charid !== (int) $char->id) {
            // Someone of the team opened one already: this tablet joins it.
            self::touch($open, $char, $now, true);
            return $open;
        }
        // One session a team: the old ones go.
        $old = $DB->get_fieldset_select(self::T . 'huddle', 'id', 'courseid = ? AND groupid = ?', [$courseid, $team->id]);
        if ($old) {
            [$in, $params] = $DB->get_in_or_equal($old);
            $DB->delete_records_select(self::T . 'huddlein', "huddleid $in", $params);
            $DB->delete_records_select(self::T . 'huddle', "id $in", $params);
        }
        $huddle = (object) ['courseid' => $courseid, 'groupid' => $team->id, 'sceneid' => $scene->id, 'charid' => $char->id,
            'state' => json_encode(['started' => false, 'seq' => 0, 'node' => null, 'pending' => null, 'players' => []]),
            'status' => 'open', 'timecreated' => $now, 'timemodified' => $now];
        $huddle->id = $DB->insert_record(self::T . 'huddle', $huddle);
        self::touch($huddle, $char, $now, true);
        return $huddle;
    }

    /**
     * The session open now in a team: its guide gave a sign in the last two minutes.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int $now
     * @return \stdClass|null
     */
    public static function current(int $courseid, int $groupid, int $now): ?\stdClass {
        global $DB;
        $where = 'courseid = ? AND groupid = ? AND status = ? AND timemodified >= ?';
        $open = $DB->get_records_select(
            self::T . 'huddle',
            $where,
            [$courseid, $groupid, 'open', $now - self::ALIVE],
            'id DESC',
            '*',
            0,
            1
        );
        return $open ? reset($open) : null;
    }

    /**
     * A student joins the session open in their team.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return array|null The session as their tablet sees it.
     */
    public static function join(int $courseid, int $userid, ?int $now = null): ?array {
        $now = $now ?? time();
        [$char, $team] = self::student($courseid, $userid, $now);
        $huddle = self::current($courseid, (int) $team->id, $now);
        if (!$huddle) {
            return null;
        }
        self::touch($huddle, $char, $now, true);
        return self::view($huddle, $char, $now);
    }

    /**
     * The session of the team of a student, if one is open; for who joined it, asking is their sign that they are there.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return array|null The session as their tablet sees it.
     */
    public static function mine(int $courseid, int $userid, ?int $now = null): ?array {
        $now = $now ?? time();
        $char = characters::get($userid);
        $team = $char && regions::enabled($courseid) ? teams::of($courseid, $userid) : null;
        $huddle = $team ? self::current($courseid, (int) $team->id, $now) : null;
        if (!$huddle) {
            return null;
        }
        self::touch($huddle, $char, $now);
        return self::view($huddle, $char, $now);
    }

    /**
     * A session by its id, for a student of its team.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $huddleid
     * @param int $now
     * @return array [session, character]
     */
    public static function get(int $courseid, int $userid, int $huddleid, int $now): array {
        global $DB;
        [$char, $team] = self::student($courseid, $userid, $now);
        $huddle = $DB->get_record(self::T . 'huddle', ['id' => $huddleid, 'courseid' => $courseid, 'groupid' => $team->id]);
        if (!$huddle || $huddle->status !== 'open' || (int) $huddle->timemodified < $now - self::ALIVE) {
            throw new \moodle_exception('huddlegone', 'local_oksigeniatesape');
        }
        self::touch($huddle, $char, $now, true);
        return [$huddle, $char];
    }

    /**
     * The guide starts the scene with those who are in: the play counts for them.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $now
     * @return int[] The users who play, the guide first.
     */
    public static function start(\stdClass $huddle, \stdClass $char, int $now): array {
        self::guide($huddle, $char);
        $state = self::state($huddle);
        $state['started'] = true;
        $state['players'] = array_column(self::present($huddle, $now), 'userid');
        self::save($huddle, $state, $now);
        return $state['players'];
    }

    /**
     * The guide publishes the decision at hand: what it asks, who decides, and the options if it is one to choose.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param array $node node, kind, prompt, options (id, label), hand (a user).
     * @param int $now
     * @return int The number of the decision.
     */
    public static function publish(\stdClass $huddle, \stdClass $char, array $node, int $now): int {
        self::guide($huddle, $char);
        $state = self::state($huddle);
        $options = [];
        foreach ((array) ($node['options'] ?? []) as $option) {
            $options[] = ['id' => clean_param((string) ($option['id'] ?? ''), PARAM_ALPHANUMEXT),
                'label' => clean_param((string) ($option['label'] ?? ''), PARAM_TEXT)];
        }
        $state['seq']++;
        $state['node'] = ['node' => clean_param((string) ($node['node'] ?? ''), PARAM_ALPHANUMEXT),
            'kind' => clean_param((string) ($node['kind'] ?? ''), PARAM_ALPHA),
            'prompt' => clean_param((string) ($node['prompt'] ?? ''), PARAM_TEXT),
            'options' => $options, 'hand' => (int) ($node['hand'] ?? 0)];
        $state['pending'] = null;
        self::save($huddle, $state, $now);
        return $state['seq'];
    }

    /**
     * Who has the hand chooses on their tablet: it waits for the guide to play it.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param string $option
     * @param int $seq The number of the decision they saw.
     * @param int $now
     */
    public static function decide(\stdClass $huddle, \stdClass $char, string $option, int $seq, int $now): void {
        $state = self::state($huddle);
        $node = $state['node'] ?? null;
        if (
            !$node || $node['kind'] !== 'choose' || (int) $node['hand'] !== (int) $char->userid || $seq !== (int) $state['seq']
            || !in_array($option, array_column($node['options'], 'id'), true) || !empty($state['pending'])
        ) {
            throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
        }
        $state['pending'] = ['by' => (int) $char->userid, 'option' => $option, 'seq' => $seq];
        // The decision of a member does not count as a sign of the guide.
        self::save($huddle, $state, (int) $huddle->timemodified);
    }

    /**
     * Who plays in the session of a guide, started now in their team: each one's own tablet says they are there, also
     * for who follows the expedition from far.
     *
     * @param int $courseid
     * @param \stdClass $char The guide.
     * @param int $now
     * @return int[] Users.
     */
    public static function players(int $courseid, \stdClass $char, int $now): array {
        $team = teams::of($courseid, (int) $char->userid);
        $huddle = $team ? self::current($courseid, (int) $team->id, $now) : null;
        if (!$huddle || (int) $huddle->charid !== (int) $char->id) {
            return [];
        }
        $state = self::state($huddle);
        return empty($state['started']) ? [] : array_map('intval', $state['players']);
    }

    /**
     * A tablet leaves the session: it stops counting as there.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     */
    public static function leave(\stdClass $huddle, \stdClass $char): void {
        global $DB;
        $DB->delete_records(self::T . 'huddlein', ['huddleid' => $huddle->id, 'charid' => $char->id]);
    }

    /**
     * The guide closes the session, or a teacher from the class screen.
     *
     * @param \stdClass $huddle
     * @param int $now
     */
    public static function close(\stdClass $huddle, int $now): void {
        global $DB;
        $DB->update_record(self::T . 'huddle', (object) ['id' => $huddle->id, 'status' => 'closed', 'timemodified' => $now]);
    }

    /**
     * The teams of a course with a session open now, for the class screen.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array groupid => session id.
     */
    public static function open_teams(int $courseid, ?int $now = null): array {
        global $DB;
        $where = 'courseid = ? AND status = ? AND timemodified >= ?';
        return array_map('intval', $DB->get_records_select_menu(
            self::T . 'huddle',
            $where,
            [$courseid, 'open', ($now ?? time()) - self::ALIVE],
            'id',
            'groupid, id'
        ));
    }

    /**
     * The session as a tablet of the team sees it.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $now
     * @return array
     */
    public static function view(\stdClass $huddle, \stdClass $char, int $now): array {
        global $DB;
        $state = self::state($huddle);
        $present = self::present($huddle, $now);
        $names = array_column($present, 'name', 'userid');
        $guide = (int) $huddle->charid === (int) $char->id;
        $node = $state['node'] ?? null;
        $hand = $node ? (int) $node['hand'] : 0;
        $scene = $DB->get_record(self::T . 'scene', ['id' => $huddle->sceneid], 'id, code, definition');
        $title = $scene ? (string) ((json_decode((string) $scene->definition, true) ?: [])['title'] ?? $scene->code) : '';
        $mission = missions::get((int) $huddle->courseid);
        $leader = array_values(array_filter($present, fn($p) => $p['guide']));
        $view = [
            'id' => (int) $huddle->id,
            'sceneid' => (int) $huddle->sceneid,
            'scene' => $mission ? missions::text($title, $mission->skin) : $title,
            'guide' => $guide,
            'joined' => (bool) $DB->record_exists(self::T . 'huddlein', ['huddleid' => $huddle->id, 'charid' => $char->id]),
            'guidename' => $leader ? $leader[0]['name'] : self::name((int) $huddle->charid, (int) $huddle->courseid),
            'started' => !empty($state['started']),
            'members' => array_values(array_map(fn($p) => ['userid' => $p['userid'], 'name' => $p['name'],
                'me' => $p['userid'] === (int) $char->userid], $present)),
            'players' => array_map('intval', $state['players'] ?? []),
            'seq' => (int) $state['seq'],
            'kind' => $node ? (string) $node['kind'] : '',
            'node' => $node ? (string) $node['node'] : '',
            'prompt' => $node ? (string) $node['prompt'] : '',
            'options' => $node ? $node['options'] : [],
            'hand' => $hand,
            'handname' => $names[$hand] ?? ($hand ? self::user_name($hand, (int) $huddle->courseid) : ''),
            'myturn' => $hand === (int) $char->userid,
            'waiting' => !empty($state['pending']),
        ];
        // The choice waiting is for the guide to play.
        if ($guide && !empty($state['pending'])) {
            $view['pending'] = ['by' => (int) $state['pending']['by'], 'option' => (string) $state['pending']['option'],
                'seq' => (int) $state['pending']['seq']];
        }
        return $view;
    }

    /**
     * Who is in the session now: those who gave a sign in the last seconds, the guide first.
     *
     * @param \stdClass $huddle
     * @param int $now
     * @return array Of userid, name and guide.
     */
    public static function present(\stdClass $huddle, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT h.charid, c.userid
               FROM {' . self::T . 'huddlein} h
               JOIN {' . self::T . 'char} c ON c.id = h.charid
              WHERE h.huddleid = ? AND h.lastseen >= ?
           ORDER BY h.timecreated, h.id',
            [$huddle->id, $now - self::PRESENT]
        );
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['userid' => (int) $row->userid, 'name' => self::user_name((int) $row->userid, (int) $huddle->courseid),
                'guide' => (int) $row->charid === (int) $huddle->charid];
        }
        usort($list, fn($a, $b) => (int) $b['guide'] <=> (int) $a['guide']);
        return $list;
    }

    /**
     * A tablet gives a sign: it is in the session (it joins the first time); the guide keeps it alive.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     * @param int $now
     * @param bool $join It comes in now (otherwise only who is in gives signs).
     */
    private static function touch(\stdClass $huddle, \stdClass $char, int $now, bool $join = false): void {
        global $DB;
        $in = $DB->get_record(self::T . 'huddlein', ['huddleid' => $huddle->id, 'charid' => $char->id]);
        if ($in) {
            $DB->set_field(self::T . 'huddlein', 'lastseen', $now, ['id' => $in->id]);
        } else if ($join) {
            $DB->insert_record(self::T . 'huddlein', (object) ['huddleid' => $huddle->id, 'courseid' => $huddle->courseid,
                'charid' => $char->id, 'lastseen' => $now, 'timecreated' => $now]);
        }
        if ((int) $huddle->charid === (int) $char->id) {
            $DB->set_field(self::T . 'huddle', 'timemodified', $now, ['id' => $huddle->id]);
            $huddle->timemodified = $now;
        }
    }

    /**
     * A student of a course with a character and a team, playing.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $now
     * @return array [character, team]
     */
    private static function student(int $courseid, int $userid, int $now): array {
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        if (engine::member($char, $courseid, $now)->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $team = teams::of($courseid, $userid);
        if (!$team) {
            throw new \moodle_exception('powernoteam', 'local_oksigeniatesape');
        }
        return [$char, $team];
    }

    /**
     * Only the guide does this.
     *
     * @param \stdClass $huddle
     * @param \stdClass $char
     */
    private static function guide(\stdClass $huddle, \stdClass $char): void {
        if ((int) $huddle->charid !== (int) $char->id) {
            throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
        }
    }

    /**
     * The state of a session.
     *
     * @param \stdClass $huddle
     * @return array
     */
    private static function state(\stdClass $huddle): array {
        return (json_decode((string) $huddle->state, true) ?: []) + ['started' => false, 'seq' => 0, 'node' => null,
            'pending' => null, 'players' => []];
    }

    /**
     * Keeps the state of a session.
     *
     * @param \stdClass $huddle
     * @param array $state
     * @param int $now
     */
    private static function save(\stdClass $huddle, array $state, int $now): void {
        global $DB;
        $huddle->state = json_encode($state);
        $huddle->timemodified = $now;
        $DB->update_record(self::T . 'huddle', (object) ['id' => $huddle->id, 'state' => $huddle->state,
            'timemodified' => $now]);
    }

    /**
     * The name of a character's student, as classmates see it.
     *
     * @param int $charid
     * @param int $courseid
     * @return string
     */
    private static function name(int $charid, int $courseid): string {
        global $DB;
        $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $charid]);
        return $userid ? self::user_name($userid, $courseid) : '';
    }

    /**
     * The name of a student, as classmates see it.
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    private static function user_name(int $userid, int $courseid): string {
        $user = \core_user::get_user($userid);
        $region = regions::get($courseid);
        return $user && $region ? view::display_name($user, characters::get($userid), $region) : '';
    }
}
