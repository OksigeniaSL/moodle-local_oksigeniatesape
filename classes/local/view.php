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

    /** @var string[] Roles that see which teammates are low on energy, because they can help them. */
    public const CARERS = ['healer', 'protector'];

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
            'items' => [],
            'log' => [],
            'activities' => [],
            'powers' => [],
            'powerrequests' => [],
            'standings' => [],
            'ideas' => [],
            'hascoat' => false,
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
            $team['week'] = teams::week($courseid, $team['id']);
            $world['team'] = $team;
        }
        if ($region->leaderboard) {
            $world['standings'] = array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'saber' => $t['saber'],
                'goal' => $t['goal'], 'reached' => $t['reached']], teams::standings($courseid));
        }
        $powers = powers::for_student($char, $member);
        $world['powers'] = $powers['powers'];
        $world['powerrequests'] = $powers['requests'];
        $world['activities'] = activities::for_student($courseid, $userid, $member);
        $world['ideas'] = ideas::mine($courseid, (int) $char->id);
        $world['hascoat'] = minigames::has_coat($char, $courseid);
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
        $world['items'] = array_values(array_map(fn($i) => ['code' => $i->code, 'name' => format_string($i->name),
            'description' => (string) $i->description], $DB->get_records_sql(
                'SELECT i.id, i.code, i.name, i.description
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ?
           ORDER BY v.timecreated',
                [$char->id, $courseid]
            )));
        $mission = self::mission($courseid, $char, $member);
        if ($mission) {
            $world['mission'] = $mission;
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
     * The mission of a course as a student sees it: the map with its chapters and lanterns, the scenes of their step
     * and what they have done, and the pages of the book they have found.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param \stdClass $member
     * @return array|null
     */
    public static function mission(int $courseid, \stdClass $char, \stdClass $member): ?array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $data = $mission->data;
        $skin = $mission->skin;
        $found = [];
        foreach ($DB->get_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id]) as $f) {
            $found[$f->kind][$f->code] = true;
        }
        $attempts = $DB->get_records_sql_menu(
            'SELECT sceneid, COUNT(1) FROM {' . self::T . 'sceneattempt} WHERE charid = ? AND courseid = ? GROUP BY sceneid',
            [$char->id, $courseid]
        );
        // The endings reached in each scene, to know which can still be made better.
        $reached = [];
        $rows = $DB->get_recordset_sql(
            'SELECT DISTINCT sceneid, ending FROM {' . self::T . 'sceneattempt} WHERE charid = ? AND courseid = ?',
            [$char->id, $courseid]
        );
        foreach ($rows as $row) {
            $reached[$row->sceneid][] = $row->ending;
        }
        $rows->close();
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder');
        $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code');
        $challenges = $DB->get_records_select_menu(
            self::T . 'challenge',
            'courseid = ? AND cmid = 0 AND chapterid > 0',
            [$courseid],
            '',
            'chapterid, id'
        );
        $info = [];
        foreach ($data['chapters'] ?? [] as $chapter) {
            $info[$chapter['code']] = $chapter;
        }
        $list = [];
        foreach ($chapters as $chapter) {
            $meta = $info[$chapter->code] ?? [];
            $mine = scenes::for_step(
                array_values(array_filter($scenes, fn($s) => $s->chapterid == $chapter->id)),
                (int) $member->step
            );
            $games = [];
            foreach ($meta['games'] ?? [] as $game) {
                if (isset($data['games'][$game])) {
                    $games[] = ['code' => $game, 'title' => missions::text($data['games'][$game]['title'] ?? $game, $skin),
                        'done' => !empty($found['game'][$game])];
                }
            }
            $list[] = [
                'id' => (int) $chapter->id,
                'code' => $chapter->code,
                'games' => $games,
                'name' => missions::text($meta['name'] ?? $chapter->code, $skin),
                'intro' => missions::text($meta['intro'] ?? '', $skin, $mission->variant),
                'x' => (int) ($meta['x'] ?? 0),
                'y' => (int) ($meta['y'] ?? 0),
                'lit' => (bool) $chapter->litat,
                'mine' => !empty($found['lantern'][$chapter->code]),
                'done' => !empty($found['chapter'][$chapter->code]),
                'challengeid' => (int) ($challenges[$chapter->id] ?? 0),
                'scenes' => array_map(function ($scene) use ($found, $attempts, $reached) {
                    $def = json_decode($scene->definition, true);
                    $done = !empty($found['scene'][$scene->code]);
                    // Done with a partial ending only: playing it again can make it better.
                    $best = array_filter($reached[$scene->id] ?? [], fn($e) => ($def['nodes'][$e]['type'] ?? '') === 'ending'
                        && ($def['nodes'][$e]['outcome'] ?? 'success') === 'success');
                    return [
                        'id' => (int) $scene->id,
                        'code' => $scene->code,
                        'title' => (string) ($def['title'] ?? $scene->code),
                        'minutes' => (int) ($def['minutes'] ?? 0),
                        'done' => $done,
                        'better' => $done && !$best,
                        'attempts' => (int) ($attempts[$scene->id] ?? 0),
                    ];
                }, $mine),
            ];
        }
        $pages = [];
        foreach ($data['pages'] ?? [] as $code => $page) {
            if (!empty($found['page'][$code])) {
                $pages[] = ['code' => $code, 'title' => (string) $page['title'], 'text' => (string) $page['text']];
            }
        }
        return [
            'id' => (int) $mission->id,
            'code' => $mission->code,
            'title' => missions::text($data['title'] ?? $mission->code, $skin),
            'subtitle' => missions::text($data['subtitle'] ?? '', $skin),
            'prologue' => missions::text($data['prologue'] ?? '', $skin, $mission->variant),
            'book' => missions::text($data['book'] ?? '', $skin),
            'skin' => $skin,
            'variant' => $mission->variant,
            'pagecount' => count($data['pages'] ?? []),
            'chapters' => $list,
            'pages' => $pages,
        ];
    }

    /**
     * The class screen of a course: the map of the mission with the lanterns of the class, and the event of the day.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array mission (if any), event (if drawn), opener (if recent), cards.
     */
    public static function board(int $courseid, ?int $now = null): array {
        global $DB;
        $board = ['cards' => events::CARDS];
        $mission = missions::get($courseid);
        if ($mission) {
            $info = [];
            foreach ($mission->data['chapters'] ?? [] as $chapter) {
                $info[$chapter['code']] = $chapter;
            }
            $chapters = [];
            $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code');
            foreach ($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder') as $chapter) {
                $meta = $info[$chapter->code] ?? [];
                // The scenes the class can play together: those of the core step.
                $mine = scenes::for_step(array_values(array_filter($scenes, fn($sc) => $sc->chapterid == $chapter->id)), 2);
                $chapters[] = [
                    'id' => (int) $chapter->id,
                    'code' => $chapter->code,
                    'name' => missions::text($meta['name'] ?? $chapter->code, $mission->skin),
                    'x' => (int) ($meta['x'] ?? 0),
                    'y' => (int) ($meta['y'] ?? 0),
                    'lit' => (bool) $chapter->litat,
                    'scenes' => array_map(fn($sc) => ['id' => (int) $sc->id,
                        'title' => (string) (json_decode($sc->definition, true)['title'] ?? $sc->code)], $mine),
                ];
            }
            [$items, $roles] = scenes::class_context($courseid);
            $board['mission'] = [
                'items' => $items,
                'roles' => $roles,
                'code' => $mission->code,
                'title' => missions::text($mission->data['title'] ?? $mission->code, $mission->skin),
                'skin' => $mission->skin,
                'chapters' => $chapters,
                // The final battle, if the mission saves something for the end, and the text of its end.
                'final' => !empty($mission->data['battle']['final']),
                'ending' => missions::text($mission->data['ending'] ?? '', $mission->skin, $mission->variant),
            ];
        }
        $event = events::export(events::today($courseid, $now));
        if ($event) {
            $board['event'] = $event;
        }
        $opener = nextclass::opener($courseid, $now ?? time());
        if ($opener !== '') {
            $board['opener'] = $opener;
        }
        return $board;
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
            'standings' => teams::standings($courseid),
            'activities' => activities::for_teacher($courseid, array_keys($students)),
            'powerspending' => powers::pending_count($courseid),
            'ideasunread' => ideas::unread($courseid),
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
            'weeklycap' => (int) ($region->weeklycap ?? 0),
            'dailymanualcap' => (int) ($region->dailymanualcap ?? 20),
            'dailyenergylosscap' => (int) ($region->dailyenergylosscap ?? 3),
            'leaderboard' => (bool) ($region->leaderboard ?? false),
            'nicknames' => (int) ($region->nicknames ?? 0),
            'timezone' => (string) ($region->timezone ?? '99'),
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
            'creature' => (string) $char->creature,
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
        // Who is low on energy, only for the healer and the protector, who can help; the rest see the team's total.
        $mine = characters::get($userid);
        $carers = $mine && in_array($mine->role, self::CARERS, true);
        $energy = 0;
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
                $energy += $member ? (int) $member->energy : 0;
                if ($member && !$carers && $mate->id != $userid) {
                    $entry['character']['member']['energy'] = -1;
                    if ($member->status === 'fallen') {
                        $entry['character']['member']['status'] = 'active';
                    }
                }
            }
            $mates[] = $entry;
        }
        $playing = count(array_filter($mates, fn($m) => isset($m['character']['member'])));
        return ['id' => (int) $group->id, 'name' => format_string($group->name), 'members' => $mates,
            'energy' => $energy, 'energymax' => $playing * engine::ENERGY_MAX, 'carer' => $carers];
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
     * The address of a user's picture, empty if the user has none: the teacher sees the portrait of the character
     * instead of the empty picture of Moodle.
     *
     * @param \stdClass $user
     * @return string
     */
    private static function picture(\stdClass $user): string {
        global $CFG, $PAGE;
        if (empty($user->picture) && empty($CFG->enablegravatar)) {
            return '';
        }
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
