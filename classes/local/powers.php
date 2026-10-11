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
 * Powers: real privileges of the classroom that cost breath. A student asks to use one; the teacher approves it with
 * a tap (or it is approved by itself if the teacher wants). Some act in the game when approved: the shield and the
 * wall keep energy from being lost today, the breather gives energy, revive brings a worn-out teammate back, cheer
 * gives breath to the team. The rest are privileges the teacher grants in class.
 *
 * Breath is spent when asking; if the teacher says no, it comes back.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class powers {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * @var array The powers each course starts with: code => [role, cost in breath, level, target]. Target: mate (a
     *     teammate), fallen (a worn-out teammate), team (the whole team) or none.
     */
    public const CATALOG = [
        'shield' => ['protector', 2, 1, 'mate'],
        'extratime' => ['protector', 2, 3, 'none'],
        'wall' => ['protector', 4, 5, 'team'],
        'breather' => ['healer', 2, 1, 'mate'],
        'revive' => ['healer', 3, 3, 'fallen'],
        'cheer' => ['healer', 4, 5, 'team'],
        'hint' => ['sage', 2, 1, 'none'],
        'notes' => ['sage', 3, 3, 'none'],
        'glance' => ['sage', 3, 4, 'none'],
        'lookout' => ['scout', 2, 1, 'none'],
        'explore' => ['scout', 3, 3, 'none'],
        'trail' => ['scout', 4, 5, 'team'],
        'seat' => ['all', 3, 2, 'none'],
        'music' => ['all', 2, 4, 'none'],
        'lateday' => ['all', 5, 6, 'none'],
    ];

    /** @var string[] Who a power is for: the roles, and all. */
    public const ROLES = ['protector', 'healer', 'sage', 'scout', 'all'];

    /** @var int Energy the breather gives. */
    public const BREATHER_ENERGY = 3;

    /** @var int Breath cheer gives to each teammate. */
    public const CHEER_BREATH = 2;

    /** @var int Knowledge for everybody in the team when one of them is rescued. */
    public const RESCUE_SABER = 5;

    /** @var string[] What a request can be. */
    public const STATUSES = ['pending', 'approved', 'rejected', 'used'];

    /**
     * The powers of a course: those of the catalog are added the first time (and when new ones come with the plugin).
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass[] Keyed by id, in the order of the catalog and then the teacher's own.
     */
    public static function catalog(int $courseid, ?int $now = null): array {
        global $DB;
        $powers = $DB->get_records(self::T . 'power', ['courseid' => $courseid], 'id');
        $codes = array_map(fn($p) => $p->code, $powers);
        $now = $now ?? time();
        foreach (self::CATALOG as $code => [$role, $cost, $level]) {
            if (!in_array($code, $codes, true)) {
                $power = (object) [
                    'courseid' => $courseid,
                    'role' => $role,
                    'code' => $code,
                    'name' => '',
                    'description' => null,
                    'cost' => $cost,
                    'minlevel' => $level,
                    'enabled' => 1,
                    'autoapprove' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $power->id = $DB->insert_record(self::T . 'power', $power);
                $powers[$power->id] = $power;
            }
        }
        $order = array_flip(array_keys(self::CATALOG));
        uasort($powers, fn($a, $b) => ($order[$a->code] ?? 999) <=> ($order[$b->code] ?? 999) ?: $a->id <=> $b->id);
        return $powers;
    }

    /**
     * The name of a power: the teacher's, or the one of the language pack.
     *
     * @param \stdClass $power
     * @return string
     */
    public static function name(\stdClass $power): string {
        if (trim((string) $power->name) !== '' || !isset(self::CATALOG[$power->code])) {
            return format_string((string) $power->name);
        }
        return get_string('power_' . $power->code, 'local_oksigeniatesape');
    }

    /**
     * What a power does: the teacher's words, or those of the language pack.
     *
     * @param \stdClass $power
     * @return string
     */
    public static function description(\stdClass $power): string {
        if (trim((string) $power->description) !== '' || !isset(self::CATALOG[$power->code])) {
            return (string) $power->description;
        }
        return get_string('power_' . $power->code . '_desc', 'local_oksigeniatesape');
    }

    /**
     * Who a power is used on.
     *
     * @param \stdClass $power
     * @return string mate, fallen, team or none.
     */
    public static function target(\stdClass $power): string {
        return self::CATALOG[$power->code][3] ?? 'none';
    }

    /**
     * Changes a power of the course, or makes a new one of the teacher's (a privilege: it does nothing in the game but
     * be approved).
     *
     * @param int $courseid
     * @param array $data id (0 for a new one), and any of role, name, description, cost, minlevel, enabled,
     *     autoapprove.
     * @param int|null $now
     * @return \stdClass
     */
    public static function save(int $courseid, array $data, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!empty($data['id'])) {
            $power = $DB->get_record(self::T . 'power', ['id' => $data['id'], 'courseid' => $courseid], '*', MUST_EXIST);
        } else {
            $power = (object) ['courseid' => $courseid, 'code' => 'own', 'role' => 'all', 'name' => '', 'cost' => 2,
                'minlevel' => 1, 'enabled' => 1, 'autoapprove' => 0, 'timecreated' => $now];
            if (trim(clean_param((string) ($data['name'] ?? ''), PARAM_TEXT)) === '') {
                throw new \invalid_parameter_exception('A new power needs a name');
            }
        }
        if (array_key_exists('role', $data) && $power->code === 'own') {
            if (!in_array($data['role'], self::ROLES, true)) {
                throw new \invalid_parameter_exception('Unknown role: ' . $data['role']);
            }
            $power->role = $data['role'];
        }
        if (array_key_exists('name', $data)) {
            $power->name = \core_text::substr(trim(clean_param((string) $data['name'], PARAM_TEXT)), 0, 255);
        }
        if (array_key_exists('description', $data)) {
            $description = trim(clean_param((string) $data['description'], PARAM_TEXT));
            $power->description = $description === '' ? null : $description;
        }
        if (array_key_exists('cost', $data)) {
            $power->cost = max(0, min(engine::BREATH_MAX, (int) $data['cost']));
        }
        if (array_key_exists('minlevel', $data)) {
            $power->minlevel = max(1, min(progress::MAX_LEVEL, (int) $data['minlevel']));
        }
        foreach (['enabled', 'autoapprove'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $power->$flag = $data[$flag] ? 1 : 0;
            }
        }
        $power->timemodified = $now;
        if (empty($power->id)) {
            $power->id = $DB->insert_record(self::T . 'power', $power);
        } else {
            $DB->update_record(self::T . 'power', $power);
        }
        return $DB->get_record(self::T . 'power', ['id' => $power->id], '*', MUST_EXIST);
    }

    /**
     * A student asks to use a power. The breath is spent now; if the power approves itself, it acts now too.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $powerid
     * @param int $targetuserid The teammate, for powers used on one (0 otherwise).
     * @param int|null $now
     * @return \stdClass The request.
     */
    public static function request(int $courseid, int $userid, int $powerid, int $targetuserid = 0, ?int $now = null): \stdClass {
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
        if ($member->status === 'fallen') {
            throw new \moodle_exception('powerwornout', 'local_oksigeniatesape');
        }
        $power = $DB->get_record(self::T . 'power', ['id' => $powerid, 'courseid' => $courseid, 'enabled' => 1]);
        if (!$power || ($power->role !== 'all' && $power->role !== $char->role)) {
            throw new \moodle_exception('powernotyours', 'local_oksigeniatesape');
        }
        if ((int) $char->level < (int) $power->minlevel) {
            throw new \moodle_exception('powerlocked', 'local_oksigeniatesape', '', $power->minlevel);
        }
        if ($DB->record_exists(self::T . 'powerreq', ['powerid' => $power->id, 'memberid' => $member->id, 'status' => 'pending'])) {
            throw new \moodle_exception('powerpending', 'local_oksigeniatesape');
        }
        $target = self::check_target($courseid, $userid, self::target($power), $targetuserid);
        if ((int) $member->breath < (int) $power->cost) {
            throw new \moodle_exception('notenoughbreath', 'local_oksigeniatesape');
        }
        $transaction = $DB->start_delegated_transaction();
        $request = (object) [
            'powerid' => $power->id,
            'memberid' => $member->id,
            'targetmemberid' => $target ? $target->id : 0,
            'status' => 'pending',
            'decidedby' => 0,
            'timecreated' => $now,
            'timedecided' => 0,
        ];
        $request->id = $DB->insert_record(self::T . 'powerreq', $request);
        if ($power->cost > 0) {
            engine::breath($member, -(int) $power->cost, 'power', $userid, (int) $request->id, $now);
        }
        $transaction->allow_commit();
        if ($power->autoapprove) {
            return self::decide((int) $request->id, true, 0, $now);
        }
        return $request;
    }

    /**
     * The teacher says yes or no to a request. Yes makes it act; no gives the breath back.
     *
     * @param int $requestid
     * @param bool $approve
     * @param int $actorid The teacher (0: approved by itself).
     * @param int|null $now
     * @return \stdClass The request.
     */
    public static function decide(int $requestid, bool $approve, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $request = $DB->get_record(self::T . 'powerreq', ['id' => $requestid], '*', MUST_EXIST);
        if ($request->status !== 'pending') {
            throw new \moodle_exception('powerdecided', 'local_oksigeniatesape');
        }
        $power = $DB->get_record(self::T . 'power', ['id' => $request->powerid], '*', MUST_EXIST);
        $member = $DB->get_record(self::T . 'member', ['id' => $request->memberid], '*', MUST_EXIST);
        $request->status = $approve ? 'approved' : 'rejected';
        $request->decidedby = $actorid;
        $request->timedecided = $now;
        $DB->update_record(self::T . 'powerreq', $request);
        if ($approve) {
            self::act($power, $request, $member, $now);
        } else {
            $spent = $DB->get_field(self::T . 'ledger', 'id', ['charid' => $member->charid, 'courseid' => $member->courseid,
                'resource' => 'breath', 'reason' => 'power', 'refid' => $request->id, 'undoneby' => 0]);
            if ($spent) {
                engine::undo((int) $spent, $actorid, $now);
            }
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'powerreq', ['id' => $request->id], '*', MUST_EXIST);
    }

    /**
     * What an approved power does in the game.
     *
     * @param \stdClass $power
     * @param \stdClass $request
     * @param \stdClass $member Who used it.
     * @param int $now
     */
    private static function act(\stdClass $power, \stdClass $request, \stdClass $member, int $now): void {
        global $DB;
        $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
        $target = $request->targetmemberid ? $DB->get_record(self::T . 'member', ['id' => $request->targetmemberid]) : null;
        switch ($power->code) {
            case 'breather':
                if ($target) {
                    engine::energy($target, self::BREATHER_ENERGY, 'power', $userid, (int) $request->id, $now);
                }
                break;
            case 'revive':
                if ($target && $target->status === 'fallen') {
                    engine::recover($target, 'rescue', $userid, $now);
                    $group = teams::of((int) $member->courseid, $userid);
                    foreach ($group ? teams::members((int) $member->courseid, (int) $group->id) : [] as $mate) {
                        unset($mate->userid);
                        engine::saber($mate, self::RESCUE_SABER, 'rescue', $userid, 0, (int) $request->id, $now);
                    }
                }
                break;
            case 'cheer':
                $group = teams::of((int) $member->courseid, $userid);
                foreach ($group ? teams::members((int) $member->courseid, (int) $group->id) : [] as $mate) {
                    if ($mate->id != $member->id) {
                        unset($mate->userid);
                        engine::breath($mate, self::CHEER_BREATH, 'power', $userid, (int) $request->id, $now);
                    }
                }
                break;
        }
        // The shield and the wall stay approved for the rest of the day, and the glance until the next question of a
        // battle; the rest are privileges granted in class.
    }

    /**
     * Before a student loses energy for a commitment: the wall of their team stops it, or the shield of a teammate
     * takes it on (at half).
     *
     * @param \stdClass $member Who is going to lose it.
     * @param int $now
     * @return array|null kind (wall or shield), request and, for the shield, the protector (member).
     */
    public static function guard(\stdClass $member, int $now): ?array {
        global $DB;
        $today = engine::day_start($now);
        $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
        $group = $userid ? teams::of((int) $member->courseid, $userid) : null;
        if ($group) {
            $mates = teams::members((int) $member->courseid, (int) $group->id, true);
            if ($mates) {
                [$insql, $params] = $DB->get_in_or_equal(array_map(fn($m) => $m->id, $mates));
                $wall = $DB->get_records_sql(
                    'SELECT r.*
                       FROM {' . self::T . 'powerreq} r
                       JOIN {' . self::T . "power} p ON p.id = r.powerid
                      WHERE p.code = 'wall' AND r.status = 'approved' AND r.timedecided >= ? AND r.memberid $insql",
                    array_merge([$today], $params),
                    0,
                    1
                );
                if ($wall) {
                    return ['kind' => 'wall', 'request' => reset($wall)];
                }
            }
        }
        $shield = $DB->get_records_sql(
            'SELECT r.*
               FROM {' . self::T . 'powerreq} r
               JOIN {' . self::T . "power} p ON p.id = r.powerid
              WHERE p.code = 'shield' AND r.status = 'approved' AND r.timedecided >= ? AND r.targetmemberid = ?
           ORDER BY r.timedecided",
            [$today, $member->id],
            0,
            1
        );
        if ($shield) {
            $request = reset($shield);
            $protector = $DB->get_record(self::T . 'member', ['id' => $request->memberid]);
            if ($protector && $protector->status === 'active') {
                return ['kind' => 'shield', 'request' => $request, 'protector' => $protector];
            }
        }
        return null;
    }

    /**
     * A glance approved today and not used yet: the next question of a battle, before anybody else.
     *
     * @param \stdClass $member
     * @param int $now
     * @return \stdClass|null The request.
     */
    public static function glance(\stdClass $member, int $now): ?\stdClass {
        global $DB;
        $requests = $DB->get_records_sql(
            'SELECT r.*
               FROM {' . self::T . 'powerreq} r
               JOIN {' . self::T . "power} p ON p.id = r.powerid
              WHERE p.code = 'glance' AND r.status = 'approved' AND r.timedecided >= ? AND r.memberid = ?
           ORDER BY r.timedecided",
            [engine::day_start($now), $member->id],
            0,
            1
        );
        return reset($requests) ?: null;
    }

    /**
     * Checks the teammate a power is used on.
     *
     * @param int $courseid
     * @param int $userid Who uses it.
     * @param string $target mate, fallen, team or none.
     * @param int $targetuserid
     * @return \stdClass|null The teammate in the course, for mate and fallen.
     */
    private static function check_target(int $courseid, int $userid, string $target, int $targetuserid): ?\stdClass {
        if ($target === 'none') {
            return null;
        }
        $group = teams::of($courseid, $userid);
        if (!$group) {
            throw new \moodle_exception('powernoteam', 'local_oksigeniatesape');
        }
        if ($target === 'team') {
            return null;
        }
        $mates = teams::members($courseid, (int) $group->id);
        $mate = $mates[$targetuserid] ?? null;
        if (!$mate || $targetuserid == $userid) {
            throw new \moodle_exception('powertarget', 'local_oksigeniatesape');
        }
        if (($target === 'fallen') !== ($mate->status === 'fallen')) {
            throw new \moodle_exception('powertarget', 'local_oksigeniatesape');
        }
        unset($mate->userid);
        return $mate;
    }

    /**
     * The powers a student sees: those of their role and the common ones, with whether they can use them now, and
     * their latest requests.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @return array powers, requests.
     */
    public static function for_student(\stdClass $char, \stdClass $member): array {
        global $DB;
        $catalog = self::catalog((int) $member->courseid);
        $pending = $DB->get_records_menu(
            self::T . 'powerreq',
            ['memberid' => $member->id, 'status' => 'pending'],
            '',
            'powerid, id'
        );
        $powers = [];
        foreach ($catalog as $power) {
            if (!$power->enabled || ($power->role !== 'all' && $power->role !== $char->role)) {
                continue;
            }
            $powers[] = [
                'id' => (int) $power->id,
                'code' => $power->code,
                'role' => $power->role,
                'name' => self::name($power),
                'description' => self::description($power),
                'cost' => (int) $power->cost,
                'minlevel' => (int) $power->minlevel,
                'target' => self::target($power),
                'locked' => (int) $char->level < (int) $power->minlevel,
                'pending' => isset($pending[$power->id]),
            ];
        }
        $requests = [];
        $records = $DB->get_records(self::T . 'powerreq', ['memberid' => $member->id], 'timecreated DESC, id DESC', '*', 0, 8);
        foreach ($records as $request) {
            $power = $catalog[$request->powerid] ?? null;
            $requests[] = [
                'id' => (int) $request->id,
                'name' => $power ? self::name($power) : '',
                'status' => $request->status,
                'target' => self::target_name($request, $member->courseid),
                'time' => (int) $request->timecreated,
            ];
        }
        return ['powers' => $powers, 'requests' => $requests];
    }

    /**
     * The requests of a course for its teachers: those waiting, and the latest decided.
     *
     * @param int $courseid
     * @param int $limit Decided requests to show.
     * @return array
     */
    public static function requests(int $courseid, int $limit = 20): array {
        global $DB;
        $catalog = self::catalog($courseid);
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = 'SELECT r.*, c.userid, ' . $names . '
                  FROM {' . self::T . 'powerreq} r
                  JOIN {' . self::T . 'member} m ON m.id = r.memberid
                  JOIN {' . self::T . 'char} c ON c.id = m.charid
                  JOIN {user} u ON u.id = c.userid
                 WHERE m.courseid = ? AND ';
        $pending = $DB->get_records_sql($sql . "r.status = 'pending' ORDER BY r.timecreated, r.id", [$courseid]);
        $decided = $DB->get_records_sql(
            $sql . "r.status <> 'pending' ORDER BY r.timedecided DESC, r.id DESC",
            [$courseid],
            0,
            $limit
        );
        $out = function ($request) use ($catalog, $courseid) {
            $power = $catalog[$request->powerid] ?? null;
            return [
                'id' => (int) $request->id,
                'userid' => (int) $request->userid,
                'fullname' => fullname($request),
                'power' => $power ? self::name($power) : '',
                'code' => $power ? $power->code : '',
                'cost' => $power ? (int) $power->cost : 0,
                'target' => self::target_name($request, $courseid),
                'status' => $request->status,
                'time' => (int) $request->timecreated,
            ];
        };
        return ['pending' => array_values(array_map($out, $pending)), 'decided' => array_values(array_map($out, $decided))];
    }

    /**
     * How many requests of a course are waiting for a teacher.
     *
     * @param int $courseid
     * @return int
     */
    public static function pending_count(int $courseid): int {
        global $DB;
        return $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {' . self::T . 'powerreq} r
               JOIN {' . self::T . "member} m ON m.id = r.memberid
              WHERE m.courseid = ? AND r.status = 'pending'",
            [$courseid]
        );
    }

    /**
     * The course of a request.
     *
     * @param int $requestid
     * @return int
     */
    public static function course_of(int $requestid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT m.courseid
               FROM {' . self::T . 'powerreq} r
               JOIN {' . self::T . 'member} m ON m.id = r.memberid
              WHERE r.id = ?',
            [$requestid],
            MUST_EXIST
        );
    }

    /**
     * The powers of a course as its teachers see them.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_teacher(int $courseid): array {
        $list = [];
        foreach (self::catalog($courseid) as $power) {
            $list[] = [
                'id' => (int) $power->id,
                'code' => $power->code,
                'role' => $power->role,
                'name' => self::name($power),
                'description' => self::description($power),
                'cost' => (int) $power->cost,
                'minlevel' => (int) $power->minlevel,
                'target' => self::target($power),
                'enabled' => (bool) $power->enabled,
                'autoapprove' => (bool) $power->autoapprove,
                'own' => $power->code === 'own',
            ];
        }
        return $list;
    }

    /**
     * The first name of the teammate a request was for.
     *
     * @param \stdClass $request
     * @param int $courseid
     * @return string
     */
    private static function target_name(\stdClass $request, int $courseid): string {
        global $DB;
        if (!$request->targetmemberid) {
            return '';
        }
        $user = $DB->get_record_sql(
            'SELECT u.id, u.firstname, c.nickname
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'char} c ON c.id = m.charid
               JOIN {user} u ON u.id = c.userid
              WHERE m.id = ?',
            [$request->targetmemberid]
        );
        if (!$user) {
            return '';
        }
        $region = regions::get($courseid);
        return $region && $region->nicknames && $user->nickname ? $user->nickname : $user->firstname;
    }
}
