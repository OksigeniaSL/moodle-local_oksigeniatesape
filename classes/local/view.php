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
 * What the game client and the teacher panel see: the world of a student, and the class of a teacher.
 *
 * Who sees what follows the design: classmates see each other's character and role, energy only inside the team,
 * and never why someone fell or was paused; the teacher sees everything of their course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Entries of the log a student sees. */
    public const OWN_LOG = 20;

    /**
     * The world of a student in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function world(int $courseid, int $userid): array {
        global $DB;
        $region = regions::get($courseid);
        $enabled = $region && $region->enabled;
        $char = characters::get($userid);
        $world = [
            'enabled' => $enabled,
            'family' => $region->family ?? 'storyheroes',
            'nicknames' => (int) ($region->nicknames ?? 0),
            'energymax' => engine::ENERGY_MAX,
            'breathmax' => engine::BREATH_MAX,
            'challenges' => [],
            'log' => [],
        ];
        if (!$char) {
            return $world;
        }
        $member = $enabled ? engine::member($char, $courseid) : null;
        $world['character'] = self::character($char, $member, true);
        if (!$member) {
            return $world;
        }
        $team = self::team($courseid, $userid, (int) ($region->groupingid ?? 0), $region);
        if ($team) {
            $world['team'] = $team;
        }
        foreach (classroom::list($courseid) as $challenge) {
            if (!in_array((string) $member->step, explode(',', $challenge->steps), true)) {
                continue;
            }
            $validation = $DB->get_record(self::T . 'validation', ['challengeid' => $challenge->id, 'charid' => $char->id]);
            $world['challenges'][] = [
                'id' => (int) $challenge->id,
                'name' => format_string($challenge->name),
                'description' => format_text((string) $challenge->description, FORMAT_PLAIN),
                'saber' => (int) $challenge->saber,
                'gems' => (int) $challenge->gems,
                'teamwork' => (bool) $challenge->teamwork,
                'status' => $validation->status ?? '',
                'note' => $validation ? (string) $validation->note : '',
            ];
        }
        $entries = $DB->get_records(
            self::T . 'ledger',
            ['charid' => $char->id, 'courseid' => $courseid],
            'timecreated DESC, id DESC',
            '*',
            0,
            self::OWN_LOG
        );
        $world['log'] = array_values(array_map(fn($e) => self::entry($e), $entries));
        return $world;
    }

    /**
     * The class of a course, for its teachers.
     *
     * @param int $courseid
     * @return array
     */
    public static function teacher(int $courseid): array {
        global $DB;
        $region = regions::get($courseid);
        $groupingid = (int) ($region->groupingid ?? 0);
        $students = self::students($courseid);
        $chars = [];
        if ($students) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
            foreach ($DB->get_records_select(self::T . 'char', "userid $insql", $params) as $char) {
                $chars[$char->userid] = $char;
            }
        }
        $members = [];
        if ($chars) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($c) => $c->id, $chars));
            $params[] = $courseid;
            foreach ($DB->get_records_select(self::T . 'member', "charid $insql AND courseid = ?", $params) as $member) {
                $members[$member->charid] = $member;
            }
        }
        $groups = groups_get_all_groups($courseid, 0, $groupingid);
        $teamof = [];
        foreach ($groups as $group) {
            foreach (array_keys(groups_get_members($group->id, 'u.id')) as $memberid) {
                $teamof[$memberid] = $teamof[$memberid] ?? (int) $group->id;
            }
        }
        $list = [];
        foreach ($students as $user) {
            $char = $chars[$user->id] ?? null;
            $member = $char ? ($members[$char->id] ?? null) : null;
            $student = [
                'userid' => (int) $user->id,
                'fullname' => fullname($user),
                'firstname' => $user->firstname,
                'picture' => self::picture($user),
                'teamid' => $teamof[$user->id] ?? 0,
            ];
            if ($char) {
                $student['character'] = self::character($char, $member, true);
            }
            $list[] = $student;
        }
        $challenges = [];
        foreach (classroom::list($courseid) as $challenge) {
            $validations = [];
            foreach (classroom::validations((int) $challenge->id) as $validation) {
                $userid = array_search($validation->charid, array_map(fn($c) => $c->id, $chars));
                if ($userid !== false) {
                    $validations[] = ['userid' => (int) $userid, 'status' => $validation->status,
                        'note' => (string) $validation->note];
                }
            }
            $challenges[] = [
                'id' => (int) $challenge->id,
                'name' => format_string($challenge->name),
                'description' => (string) $challenge->description,
                'saber' => (int) $challenge->saber,
                'gems' => (int) $challenge->gems,
                'steps' => $challenge->steps,
                'teamwork' => (bool) $challenge->teamwork,
                'validations' => $validations,
            ];
        }
        return [
            'region' => self::region($region),
            'students' => $list,
            'teams' => array_values(array_map(fn($g) => ['id' => (int) $g->id, 'name' => format_string($g->name)], $groups)),
            'groupings' => array_values(array_map(
                fn($g) => ['id' => (int) $g->id, 'name' => format_string($g->name)],
                groups_get_all_groupings($courseid)
            )),
            'challenges' => $challenges,
            'energymax' => engine::ENERGY_MAX,
            'breathmax' => engine::BREATH_MAX,
        ];
    }

    /**
     * The log of a course, newest first, for its teachers.
     *
     * @param int $courseid
     * @param int $userid Only this student (0: everybody).
     * @param int $limit
     * @param int $beforeid Only entries older than this one (0: from the newest).
     * @return array
     */
    public static function log(int $courseid, int $userid, int $limit, int $beforeid = 0): array {
        global $DB;
        $where = 'l.courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($userid) {
            $where .= ' AND c.userid = :userid';
            $params['userid'] = $userid;
        }
        if ($beforeid) {
            $where .= ' AND l.id < :beforeid';
            $params['beforeid'] = $beforeid;
        }
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $entries = $DB->get_records_sql(
            "SELECT l.*, c.userid, $names
               FROM {" . self::T . "ledger} l
               JOIN {" . self::T . "char} c ON c.id = l.charid
               JOIN {user} u ON u.id = c.userid
              WHERE $where
           ORDER BY l.id DESC",
            $params,
            0,
            $limit
        );
        $actors = [];
        $actorids = array_filter(array_unique(array_map(fn($e) => (int) $e->actorid, $entries)));
        if ($actorids) {
            $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
            $actors = $DB->get_records_list('user', 'id', $actorids, '', $fields);
        }
        return array_values(array_map(fn($e) => self::entry($e) + [
            'userid' => (int) $e->userid,
            'fullname' => fullname($e),
            'actor' => isset($actors[$e->actorid]) ? fullname($actors[$e->actorid]) : '',
        ], $entries));
    }

    /**
     * The students of a course who can play, by name.
     *
     * @param int $courseid
     * @return \stdClass[] Keyed by user id.
     */
    public static function students(int $courseid): array {
        $fields = 'u.id, u.picture, u.imagealt, u.email, ' . implode(', ', array_map(
            fn($f) => 'u.' . $f,
            \core_user\fields::get_name_fields()
        ));
        $users = get_enrolled_users(
            \context_course::instance($courseid),
            'local/oksigeniatesape:play',
            0,
            $fields,
            'u.lastname, u.firstname',
            0,
            0,
            true
        );
        return $users;
    }

    /**
     * Checks that some users are students of a course who can play.
     *
     * @param int $courseid
     * @param int[] $userids
     */
    public static function require_students(int $courseid, array $userids): void {
        $students = self::students($courseid);
        foreach ($userids as $userid) {
            if (!isset($students[$userid])) {
                throw new \invalid_parameter_exception('Not a student of the course: ' . $userid);
            }
        }
    }

    /**
     * The settings of a region, with the defaults when the game was never set up.
     *
     * @param \stdClass|null $region
     * @return array
     */
    public static function region(?\stdClass $region): array {
        return [
            'enabled' => (bool) ($region->enabled ?? false),
            'family' => $region->family ?? 'storyheroes',
            'groupingid' => (int) ($region->groupingid ?? 0),
            'weeklycap' => (int) ($region->weeklycap ?? 100),
            'dailymanualcap' => (int) ($region->dailymanualcap ?? 20),
            'dailyenergylosscap' => (int) ($region->dailyenergylosscap ?? 3),
            'leaderboard' => (bool) ($region->leaderboard ?? true),
            'nicknames' => (int) ($region->nicknames ?? 0),
            'completionrule' => $region ? regions::rule($region, 'completion') : regions::RULES['completion'],
        ];
    }

    /**
     * A character, with its state in the course if it plays there.
     *
     * @param \stdClass $char
     * @param \stdClass|null $member
     * @param bool $private With what only the student and the teachers see (why they fell or were paused).
     * @return array
     */
    private static function character(\stdClass $char, ?\stdClass $member, bool $private): array {
        $level = progress::level_progress((int) $char->saber);
        $character = [
            'role' => $char->role,
            'nameform' => $char->nameform,
            'lineage' => (string) $char->lineage,
            'nickname' => (string) $char->nickname,
            'appearance' => (string) $char->appearance,
            'saber' => (int) $char->saber,
            'level' => $level['level'],
            'levelinto' => $level['into'],
            'levelneeded' => $level['needed'],
            'stage' => progress::stage_for_level($level['level']),
            'gems' => (int) $char->gems,
            'creaturestage' => (int) $char->creaturestage,
        ];
        if ($member) {
            $character['member'] = [
                'renown' => (int) $member->renown,
                'energy' => (int) $member->energy,
                'breath' => (int) $member->breath,
                'status' => $member->status,
                'statusreason' => $private ? (string) $member->statusreason : '',
                'step' => (int) $member->step,
            ];
        }
        return $character;
    }

    /**
     * The team of a student: its name and its members, as classmates see them.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $groupingid
     * @param \stdClass $region
     * @return array|null
     */
    private static function team(int $courseid, int $userid, int $groupingid, \stdClass $region): ?array {
        global $DB;
        $groups = groups_get_all_groups($courseid, $userid, $groupingid);
        if (!$groups) {
            return null;
        }
        $group = reset($groups);
        $students = self::students($courseid);
        $mates = [];
        foreach (groups_get_members($group->id, 'u.id') as $mate) {
            if (!isset($students[$mate->id])) {
                continue;
            }
            $char = characters::get((int) $mate->id);
            $member = $char ? $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]) : null;
            $entry = [
                'userid' => (int) $mate->id,
                'name' => self::display_name($students[$mate->id], $char, $region),
                'me' => $mate->id == $userid,
            ];
            if ($char) {
                $entry['character'] = self::character($char, $member ?: null, $mate->id == $userid);
            }
            $mates[] = $entry;
        }
        return ['id' => (int) $group->id, 'name' => format_string($group->name), 'members' => $mates];
    }

    /**
     * The name classmates see: the hero name where the course allows it, the first name otherwise.
     *
     * @param \stdClass $user
     * @param \stdClass|null $char
     * @param \stdClass $region
     * @return string
     */
    public static function display_name(\stdClass $user, ?\stdClass $char, \stdClass $region): string {
        if ($region->nicknames && $char && $char->nickname) {
            return $char->nickname;
        }
        return $user->firstname;
    }

    /**
     * The address of a user's picture.
     *
     * @param \stdClass $user
     * @return string
     */
    private static function picture(\stdClass $user): string {
        global $PAGE;
        $picture = new \user_picture($user);
        $picture->size = 100;
        return $picture->get_url($PAGE)->out(false);
    }

    /**
     * A log entry, as the client shows it.
     *
     * @param \stdClass $entry
     * @return array
     */
    private static function entry(\stdClass $entry): array {
        return [
            'id' => (int) $entry->id,
            'resource' => $entry->resource,
            'amount' => (int) $entry->amount,
            'capped' => (int) $entry->capped,
            'reason' => $entry->reason,
            'undone' => (bool) $entry->undoneby,
            'time' => (int) $entry->timecreated,
        ];
    }
}
