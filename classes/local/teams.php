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
 * Teams: the groups of Moodle in the grouping the teacher chose. Each week a team fills a bar of knowledge together;
 * when it is full, everybody in the team gets gems. The teacher can make the teams in one go, with the roles spread.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teams {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int The part of each member in the goal of the week: saber in the course that week. */
    public const GOAL_PART = 40;

    /** @var float Share of the members who have to reach their part: someone missing does not sink the team. */
    public const GOAL_SHARE = 0.75;

    /** @var int Members who have to reach their part at least, in teams of two or more. */
    public const GOAL_MIN = 2;

    /** @var int Gems for each member when the team reaches the goal of the week. */
    public const GOAL_GEMS = 3;

    /** @var int Students per team by default. */
    public const SIZE = 4;

    /** @var string[] Order in which the roles are dealt out, so that every team gets each one. */
    public const ROLE_ORDER = ['protector', 'healer', 'sage', 'scout'];

    /** @var string[] How a team says it is doing today. */
    public const STATES = ['ready', 'working', 'help'];

    /**
     * A team says how it is doing today: ready, working or asking for help. The class screen shows it beside the team,
     * without names nor why.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $state One of STATES.
     * @param int|null $now
     */
    public static function set_state(int $courseid, int $groupid, string $state, ?int $now = null): void {
        global $DB;
        if (!in_array($state, self::STATES, true)) {
            throw new \invalid_parameter_exception('Unknown state of a team');
        }
        $now = $now ?? time();
        $day = (int) (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid))->format('Ymd');
        $row = $DB->get_record(self::T . 'teamstate', ['courseid' => $courseid, 'groupid' => $groupid]);
        $fields = ['state' => $state, 'day' => $day, 'timemodified' => $now];
        if ($row && (int) $row->day !== $day) {
            // What the council chose another day does not count today.
            $fields['first'] = null;
        }
        if ($row) {
            $DB->update_record(self::T . 'teamstate', (object) (['id' => $row->id] + $fields));
        } else {
            $DB->insert_record(self::T . 'teamstate', (object) (['courseid' => $courseid, 'groupid' => $groupid] + $fields));
        }
    }

    /**
     * The council of a team chooses the role that starts the hand in the scenes of today, or none.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $role One of the roles, or empty.
     * @param int|null $now
     */
    public static function set_first(int $courseid, int $groupid, string $role, ?int $now = null): void {
        global $DB;
        if ($role !== '' && !in_array($role, characters::ROLES, true)) {
            throw new \invalid_parameter_exception('Unknown role');
        }
        $now = $now ?? time();
        $day = (int) (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid))->format('Ymd');
        $row = $DB->get_record(self::T . 'teamstate', ['courseid' => $courseid, 'groupid' => $groupid]);
        $fields = ['first' => $role !== '' ? $role : null, 'day' => $day, 'timemodified' => $now];
        if ($row && (int) $row->day !== $day) {
            // How the team said it was doing another day does not count today.
            $fields['state'] = '';
        }
        if ($row) {
            $DB->update_record(self::T . 'teamstate', (object) (['id' => $row->id] + $fields));
        } else {
            $DB->insert_record(self::T . 'teamstate', (object) (['courseid' => $courseid, 'groupid' => $groupid, 'state' => ''] +
                $fields));
        }
    }

    /**
     * The roles that start the hand today, by team, as their councils chose them.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array Group id => role.
     */
    public static function firsts(int $courseid, ?int $now = null): array {
        global $DB;
        $day = (int) (new \DateTimeImmutable('@' . ($now ?? time())))->setTimezone(engine::timezone($courseid))->format('Ymd');
        $where = "courseid = ? AND day = ? AND first IS NOT NULL AND first <> ''";
        return array_map('strval', $DB->get_records_select_menu(self::T . 'teamstate', $where, [$courseid, $day], '', 'groupid, first'));
    }

    /**
     * How the teams of a course say they are doing today.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array Group id => state.
     */
    public static function states(int $courseid, ?int $now = null): array {
        global $DB;
        $day = (int) (new \DateTimeImmutable('@' . ($now ?? time())))->setTimezone(engine::timezone($courseid))->format('Ymd');
        $where = "courseid = ? AND day = ? AND state <> ''";
        $states = $DB->get_records_select_menu(self::T . 'teamstate', $where, [$courseid, $day], '', 'groupid, state');
        return array_map('strval', $states);
    }

    /**
     * The team of a student in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass|null The group.
     */
    public static function of(int $courseid, int $userid): ?\stdClass {
        $region = regions::get($courseid);
        $groups = groups_get_all_groups(games::course($courseid), $userid, (int) ($region->groupingid ?? 0));
        return $groups ? reset($groups) : null;
    }

    /**
     * Those who play in a team: their character in the course, keyed by user id.
     *
     * @param int $courseid
     * @param int $groupid
     * @param bool $paused Also those paused.
     * @return \stdClass[] Members of the course, with userid.
     */
    public static function members(int $courseid, int $groupid, bool $paused = false): array {
        global $DB;
        $userids = array_keys(groups_get_members($groupid, 'u.id'));
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $params[] = $courseid;
        $members = $DB->get_records_sql(
            'SELECT c.userid, m.*
               FROM {' . self::T . 'char} c
               JOIN {' . self::T . "member} m ON m.charid = c.id
              WHERE c.userid $insql AND m.courseid = ? AND m.leftat = 0",
            $params
        );
        return array_filter($members, fn($m) => $paused || $m->status !== 'paused');
    }

    /**
     * How a team is doing this week.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return array saber (of the team), part (of each one), done (members who reached it), needed (to reach the goal),
     *     goal (saber of the needed parts), reached, players.
     */
    public static function week(int $courseid, int $groupid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $members = self::members($courseid, $groupid);
        $bychar = [];
        if ($members) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($m) => $m->charid, $members));
            $bychar = $DB->get_records_sql_menu(
                'SELECT charid, SUM(amount)
                   FROM {' . self::T . "ledger}
                  WHERE charid $insql AND courseid = ? AND resource = 'saber' AND amount > 0 AND undoneby = 0
                        AND timecreated >= ?
               GROUP BY charid",
                array_merge($params, [$courseid, engine::week_start($now, $courseid)])
            );
        }
        $players = count($members);
        // Three of every four (two at least): someone ill or without a device at home does not sink the team.
        $needed = min($players, max(self::GOAL_MIN, (int) floor(self::GOAL_SHARE * $players)));
        $done = count(array_filter($bychar, fn($saber) => (int) $saber >= self::GOAL_PART));
        return [
            'saber' => (int) array_sum($bychar),
            'part' => self::GOAL_PART,
            'done' => $done,
            'needed' => $needed,
            'goal' => $needed * self::GOAL_PART,
            'reached' => $needed > 0 && $done >= $needed,
            'players' => $players,
        ];
    }

    /**
     * After someone earns knowledge: if their team has reached the goal of the week, everybody who plays in it gets
     * gems (once a week each).
     *
     * @param \stdClass $member The one who earned it.
     * @param int|null $now
     * @return int[] User ids of those who got the gems now.
     */
    public static function check_goal(\stdClass $member, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
        $group = $userid ? self::of((int) $member->courseid, $userid) : null;
        if (!$group || !self::week((int) $member->courseid, (int) $group->id, $now)['reached']) {
            return [];
        }
        $given = [];
        foreach (self::members((int) $member->courseid, (int) $group->id) as $mate) {
            $already = $DB->record_exists_select(
                self::T . 'ledger',
                "charid = ? AND courseid = ? AND reason = 'teamgoal' AND refid = ? AND undoneby = 0 AND timecreated >= ?",
                [$mate->charid, $member->courseid, $group->id, engine::week_start($now, (int) $member->courseid)]
            );
            if (!$already) {
                unset($mate->userid);
                engine::gems($mate, self::GOAL_GEMS, 'teamgoal', 0, 0, (int) $group->id, $now);
                $given[] = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $mate->charid]);
            }
        }
        return $given;
    }

    /**
     * The teams of a course with how they are doing this week, for the ranking (if the teacher shows it) and the panel.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array id, name, saber, goal, reached, players; most knowledge first.
     */
    public static function standings(int $courseid, ?int $now = null): array {
        $region = regions::get($courseid);
        $list = [];
        foreach (groups_get_all_groups(games::course($courseid), 0, (int) ($region->groupingid ?? 0)) as $group) {
            $list[] = ['id' => (int) $group->id, 'name' => format_string($group->name)] +
                self::week($courseid, (int) $group->id, $now);
        }
        usort($list, fn($a, $b) => $b['saber'] <=> $a['saber'] ?: strcmp($a['name'], $b['name']));
        return $list;
    }

    /**
     * Makes the teams of a course in one go: a new grouping with about $size students in each team, the roles dealt
     * out so that every team gets each one, and the region set to use it.
     *
     * @param int $courseid
     * @param int $size
     * @return \stdClass[] The groups made.
     */
    public static function make(int $courseid, int $size = self::SIZE): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $size = max(3, min(6, $size));
        $students = view::students($courseid);
        if (!$students) {
            throw new \moodle_exception('nostudents', 'local_oksigeniatesape');
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
        $roles = $DB->get_records_select_menu(self::T . 'char', "userid $insql", $params, '', 'userid, role');
        $byrole = [];
        foreach (array_keys($students) as $userid) {
            $byrole[$roles[$userid] ?? ''][] = (int) $userid;
        }
        $order = [];
        foreach (array_merge(self::ROLE_ORDER, ['']) as $role) {
            $list = $byrole[$role] ?? [];
            shuffle($list);
            $order = array_merge($order, $list);
        }
        $count = max(1, (int) round(count($order) / $size));
        $transaction = $DB->start_delegated_transaction();
        $groupingid = groups_create_grouping((object) [
            'courseid' => games::course($courseid),
            'name' => self::unique_name($courseid, get_string('teams_grouping', 'local_oksigeniatesape'), 'groupings'),
        ]);
        $names = array_map('trim', explode(',', get_string('teams_names', 'local_oksigeniatesape')));
        $groups = [];
        for ($i = 0; $i < $count; $i++) {
            $animal = $names[$i % count($names)];
            $name = get_string('teams_name', 'local_oksigeniatesape', $animal);
            $groupid = groups_create_group((object) ['courseid' => games::course($courseid),
                'name' => self::unique_name($courseid, $name, 'groups')]);
            groups_assign_grouping($groupingid, $groupid);
            $groups[$i] = $groupid;
        }
        // Dealt like cards, going back and forth, so that no team gets only the last ones.
        foreach ($order as $i => $userid) {
            $round = intdiv($i, $count);
            $position = $i % $count;
            $team = $round % 2 ? $count - 1 - $position : $position;
            groups_add_member($groups[$team], $userid);
        }
        regions::save($courseid, ['groupingid' => $groupingid]);
        $transaction->allow_commit();
        return array_values($DB->get_records_list('groups', 'id', $groups, 'id'));
    }

    /**
     * The teams of a game that were all the groups of its course, kept as they are in a grouping of their own: some of
     * the groups are now classes, each with a game of its own.
     *
     * @param int $key The game.
     * @param int[] $classes The groups that are classes.
     * @return int The grouping, or 0 if no team is left.
     */
    public static function keep_as_teams(int $key, array $classes): int {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $courseid = games::course($key);
        $groups = array_diff_key(groups_get_all_groups($courseid, 0, 0, 'g.id'), array_flip($classes));
        if (!$groups) {
            return 0;
        }
        $id = (int) groups_create_grouping((object) ['courseid' => $courseid,
            'name' => self::unique_name($key, get_string('teams_grouping', 'local_oksigeniatesape'), 'groupings')]);
        foreach (array_keys($groups) as $groupid) {
            groups_assign_grouping($id, $groupid);
        }
        regions::save($key, ['groupingid' => $id]);
        return $id;
    }

    /**
     * In a course with more than one game, the grouping with the teams of all its games, for the team submissions of
     * the activities they share (a student is in one game only, so in one team only). Made when needed and kept up to
     * date.
     *
     * @param int $courseid The course.
     * @return int Its id.
     */
    public static function all_teams(int $courseid): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $idnumber = 'tesape-allteams';
        $id = (int) $DB->get_field('groupings', 'id', ['courseid' => $courseid, 'idnumber' => $idnumber]);
        if (!$id) {
            $id = (int) groups_create_grouping((object) ['courseid' => $courseid, 'idnumber' => $idnumber,
                'name' => self::unique_name($courseid, get_string('teams_allgrouping', 'local_oksigeniatesape'), 'groupings')]);
        }
        $want = [];
        foreach (games::keys($courseid) as $key) {
            $region = regions::get($key);
            if ($region && $region->groupingid) {
                $want += groups_get_all_groups($courseid, 0, (int) $region->groupingid, 'g.id');
            }
        }
        $have = groups_get_all_groups($courseid, 0, $id, 'g.id');
        foreach (array_diff_key($want, $have) as $groupid => $group) {
            groups_assign_grouping($id, $groupid);
        }
        foreach (array_diff_key($have, $want) as $groupid => $group) {
            groups_unassign_grouping($id, $groupid);
        }
        return $id;
    }

    /**
     * A name not used yet in the course for a group or a grouping.
     *
     * @param int $courseid
     * @param string $name
     * @param string $table groups or groupings.
     * @return string
     */
    private static function unique_name(int $courseid, string $name, string $table): string {
        global $DB;
        $candidate = $name;
        for ($n = 2; $DB->record_exists($table, ['courseid' => games::course($courseid), 'name' => $candidate]); $n++) {
            $candidate = $name . ' (' . $n . ')';
        }
        return $candidate;
    }
}
