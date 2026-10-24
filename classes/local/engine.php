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
 * The state of the game: what each character has in each course, and the log of every change.
 *
 * Everything that adds or takes away goes through here and leaves an entry in the log, so that the student and the
 * teacher can see it and the teacher can undo it. Undoing never deletes: it adds the opposite entry and marks the
 * original.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class engine {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Most energy a character has in a course. */
    public const ENERGY_MAX = 10;

    /** @var int Most breath a character has in a course. */
    public const BREATH_MAX = 5;

    /** @var int Energy a worn-out character comes back with. */
    public const RECOVERED_ENERGY = 5;

    /** @var int Breath that comes back each day, up to BREATH_MAX: saving it is worth something. */
    public const BREATH_DAILY = 2;

    /** @var int Saber a character can earn in a week in the whole site, outside the mission, if the site sets none. */
    public const SITE_WEEKLY_CAP = 150;

    /** @var string[] Reasons of saber that come from the mission itself: measured by design, they go under no cap. */
    public const MISSION_REASONS = ['scene', 'battle'];

    /**
     * The character in a course: the existing one, or a new one with full energy and breath (joining the region).
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass
     */
    public static function member(\stdClass $char, int $courseid, ?int $now = null): \stdClass {
        global $DB;
        $member = $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]);
        if ($member) {
            // A pause that has ended ends now, before the hour of the task.
            if ($member->status === 'paused' && $member->pausedto > 0 && $member->pausedto <= ($now ?? time())) {
                $member = self::resume($member, $now);
            }
            return $member;
        }
        $now = $now ?? time();
        $member = (object) [
            'charid' => $char->id,
            'courseid' => $courseid,
            'energy' => self::ENERGY_MAX,
            'breath' => self::BREATH_MAX,
            'lastregen' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        try {
            $member->id = $DB->insert_record(self::T . 'member', $member);
        } catch (\dml_write_exception $e) {
            // The world and the poll of a battle, coming in at the same moment the first time: the other one made it.
            $made = $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]);
            if (!$made) {
                throw $e;
            }
            return $made;
        }
        return $DB->get_record(self::T . 'member', ['id' => $member->id], '*', MUST_EXIST);
    }

    /**
     * Gives saber. What the mission gives (its scenes, its battle, the activities and classroom challenges of its
     * chapters) goes under no cap: it is measured by design. Anything else goes under the weekly cap of the character in
     * the whole site, the weekly cap of the course if it has one and, if given by hand, the daily cap. Saber is never
     * taken away (only undone).
     *
     * @param \stdClass $member
     * @param int $amount More than 0.
     * @param string $reason Code of the reason (completion, classroom, scene, manual…).
     * @param int $actorid Who gives it (0: the game).
     * @param int $cmid Activity behind it, if any.
     * @param int $refid Other reference, if any.
     * @param int|null $now
     * @param bool $mission It comes from a chapter of the mission (scenes and battles always do).
     * @return \stdClass|null The log entry, or null if the student is paused.
     */
    public static function saber(
        \stdClass $member,
        int $amount,
        string $reason,
        int $actorid,
        int $cmid = 0,
        int $refid = 0,
        ?int $now = null,
        bool $mission = false
    ): ?\stdClass {
        global $DB;
        if ($amount <= 0) {
            throw new \coding_exception('Saber is only given; use undo to take it back');
        }
        if ($member->status === 'paused') {
            return null;
        }
        $now = $now ?? time();
        if ($reason === 'completion' && events::active((int) $member->courseid, 'breeze', $now)) {
            // Breeze, the event of the day: what is handed in today counts double.
            $amount *= 2;
        }
        $region = regions::get((int) $member->courseid);
        $granted = $amount;
        $mission = $mission || in_array($reason, self::MISSION_REASONS, true);
        if (!$mission) {
            $week = self::week_start($now, (int) $member->courseid);
            $granted = progress::apply_cap($granted, self::earned($member, $week, false), self::site_weekly_cap())['granted'];
            if ($region && $region->weeklycap > 0) {
                $granted = progress::apply_cap($granted, self::earned($member, $week, true), (int) $region->weeklycap)['granted'];
            }
            if ($region && $reason === 'manual') {
                $today = self::day_start($now, (int) $member->courseid);
                $manual = self::sum($member, 'saber', $today, "l.amount > 0 AND l.reason = 'manual'");
                $granted = progress::apply_cap($granted, $manual, (int) $region->dailymanualcap)['granted'];
            }
        }
        $transaction = $DB->start_delegated_transaction();
        $entry = self::record($member, 'saber', $granted, $reason, $actorid, $cmid, $refid, $amount - $granted, $now, $mission);
        self::add_saber((int) $member->charid, $member, $granted, $now);
        if ($granted > 0) {
            teams::check_goal($member, $now);
        }
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Runs something that reads and spends what a character has, one at a time for that character: a double tap, or
     * two tabs, cannot spend the same breath or gems twice.
     *
     * @param int $charid
     * @param callable $action
     * @return mixed What the action returns.
     */
    public static function one_at_a_time(int $charid, callable $action) {
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniatesape')->get_lock('char-' . $charid, 10);
        if (!$lock) {
            throw new \moodle_exception('charbusy', 'local_oksigeniatesape');
        }
        try {
            return $action();
        } finally {
            $lock->release();
        }
    }

    /**
     * Gives or spends gems (they belong to the character, not to the course).
     *
     * @param \stdClass $member
     * @param int $amount Positive to give, negative to spend.
     * @param string $reason
     * @param int $actorid
     * @param int $cmid
     * @param int $refid
     * @param int|null $now
     * @return \stdClass|null The log entry, or null if the student is paused.
     */
    public static function gems(
        \stdClass $member,
        int $amount,
        string $reason,
        int $actorid,
        int $cmid = 0,
        int $refid = 0,
        ?int $now = null
    ): ?\stdClass {
        global $DB;
        if ($amount == 0) {
            throw new \coding_exception('No gems to give or spend');
        }
        if ($amount > 0 && $member->status === 'paused') {
            return null;
        }
        $transaction = $DB->start_delegated_transaction();
        $char = $DB->get_record(self::T . 'char', ['id' => $member->charid], '*', MUST_EXIST);
        if ($char->gems + $amount < 0) {
            throw new \moodle_exception('notenoughgems', 'local_oksigeniatesape');
        }
        $now = $now ?? time();
        $entry = self::record($member, 'gems', $amount, $reason, $actorid, $cmid, $refid, 0, $now);
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'gems' => $char->gems + $amount, 'timemodified' => $now]);
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Gives or takes energy. Losses stop at the daily cap of the course; at 0 the character falls in that course.
     *
     * @param \stdClass $member
     * @param int $amount
     * @param string $reason
     * @param int $actorid
     * @param int $refid
     * @param int|null $now
     * @return \stdClass|null The log entry, or null if the student is paused or nothing changes.
     */
    public static function energy(
        \stdClass $member,
        int $amount,
        string $reason,
        int $actorid,
        int $refid = 0,
        ?int $now = null
    ): ?\stdClass {
        global $DB;
        if ($amount == 0 || $member->status === 'paused') {
            return null;
        }
        $now = $now ?? time();
        if ($amount < 0 && $reason === 'commitment' && $member->energy > 0) {
            $calm = events::active((int) $member->courseid, 'calm', $now);
            if ($calm) {
                // Calm, the event of the day: nobody loses energy; the loss is noted as left out.
                return self::record($member, 'energy', 0, 'calm', $actorid, 0, (int) $calm->id, -$amount, $now);
            }
            $guard = powers::guard($member, $now);
            if ($guard) {
                return self::guarded($member, -$amount, $guard, $actorid, $refid, $now);
            }
        }
        $capped = 0;
        if ($amount < 0) {
            $region = regions::get((int) $member->courseid);
            $loss = min(-$amount, (int) $member->energy);
            if ($region && $region->dailyenergylosscap > 0) {
                $today = self::day_start($now, (int) $member->courseid);
                $lost = -self::sum($member, 'energy', $today, "l.amount < 0 AND l.reason <> 'undo'");
                $loss = min($loss, max(0, $region->dailyenergylosscap - $lost));
            }
            $capped = -$amount - $loss;
            $change = -$loss;
        } else {
            $change = min(self::ENERGY_MAX, $member->energy + $amount) - $member->energy;
        }
        if ($change == 0 && $capped == 0) {
            return null;
        }
        $transaction = $DB->start_delegated_transaction();
        $entry = self::record($member, 'energy', $change, $reason, $actorid, 0, $refid, $capped, $now);
        $member->energy += $change;
        if ($member->energy <= 0 && $member->status === 'active') {
            $member->status = 'fallen';
        }
        $member->timemodified = $now;
        $DB->update_record(self::T . 'member', $member);
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * A loss of energy stopped by a power: the wall of the team stops it all; a shield marks itself used and the
     * protector loses half of it instead (within their own daily cap).
     *
     * @param \stdClass $member Who was going to lose it.
     * @param int $loss More than 0.
     * @param array $guard Of powers::guard().
     * @param int $actorid
     * @param int $refid What caused the loss (a commitment).
     * @param int $now
     * @return \stdClass The entry of the one protected, with nothing lost and the loss as left out.
     */
    private static function guarded(\stdClass $member, int $loss, array $guard, int $actorid, int $refid, int $now): \stdClass {
        global $DB;
        $request = $guard['request'];
        $transaction = $DB->start_delegated_transaction();
        // Both entries point to what caused the loss (a commitment), so that undoing it undoes them too.
        $entry = self::record($member, 'energy', 0, $guard['kind'], $actorid, 0, $refid, $loss, $now);
        if ($guard['kind'] === 'shield') {
            $DB->set_field('local_oksigeniatesape_powerreq', 'status', 'used', ['id' => $request->id]);
            self::energy($guard['protector'], -(int) ceil($loss / 2), 'shield', $actorid, $refid, $now);
        }
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Gives or spends breath (powers cost breath).
     *
     * @param \stdClass $member
     * @param int $amount
     * @param string $reason
     * @param int $actorid
     * @param int $refid
     * @param int|null $now
     * @return \stdClass|null The log entry, or null if nothing changes.
     */
    public static function breath(
        \stdClass $member,
        int $amount,
        string $reason,
        int $actorid,
        int $refid = 0,
        ?int $now = null
    ): ?\stdClass {
        global $DB;
        if ($amount < 0 && $member->breath + $amount < 0) {
            throw new \moodle_exception('notenoughbreath', 'local_oksigeniatesape');
        }
        $change = $amount < 0 ? $amount : min(self::BREATH_MAX, $member->breath + $amount) - $member->breath;
        if ($change == 0 || ($change > 0 && $member->status === 'paused')) {
            return null;
        }
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $entry = self::record($member, 'breath', $change, $reason, $actorid, 0, $refid, 0, $now);
        $member->breath += $change;
        $member->timemodified = $now;
        $DB->update_record(self::T . 'member', $member);
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Gives an item for the expedition in a course (once: there are no duplicates).
     *
     * @param \stdClass $member
     * @param int $itemid
     * @param string $source minigame, challenge or battle.
     * @param int $actorid
     * @param int $cmid
     * @param int|null $now
     * @return \stdClass|null The log entry, or null if they already have it or are paused.
     */
    public static function item(
        \stdClass $member,
        int $itemid,
        string $source,
        int $actorid,
        int $cmid = 0,
        ?int $now = null
    ): ?\stdClass {
        global $DB;
        $key = ['charid' => $member->charid, 'itemid' => $itemid, 'courseid' => $member->courseid];
        if ($member->status === 'paused' || $DB->record_exists(self::T . 'inventory', $key)) {
            return null;
        }
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $DB->insert_record(self::T . 'inventory', (object) ($key + ['source' => $source, 'timecreated' => $now]));
        $entry = self::record($member, 'item', 1, $source, $actorid, $cmid, $itemid, 0, $now);
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Undoes a log entry: adds the opposite one and marks the original.
     *
     * @param int $entryid
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass The new entry.
     */
    public static function undo(int $entryid, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $entry = $DB->get_record(self::T . 'ledger', ['id' => $entryid], '*', MUST_EXIST);
        if ($entry->undoneby || $entry->reason === 'undo') {
            throw new \moodle_exception('cannotundo', 'local_oksigeniatesape');
        }
        $char = $DB->get_record(self::T . 'char', ['id' => $entry->charid], '*', MUST_EXIST);
        $member = $DB->get_record(self::T . 'member', ['charid' => $entry->charid, 'courseid' => $entry->courseid]);
        $change = -(int) $entry->amount;
        switch ($entry->resource) {
            case 'saber':
                self::add_saber((int) $entry->charid, $member, $change, $now);
                break;
            case 'gems':
                // Gems already spent cannot be taken back.
                $change = max($change, -(int) $char->gems);
                $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'gems' => $char->gems + $change,
                    'timemodified' => $now]);
                break;
            case 'energy':
            case 'breath':
                $max = $entry->resource === 'energy' ? self::ENERGY_MAX : self::BREATH_MAX;
                $field = $entry->resource;
                $change = max(0, min($max, $member->$field + $change)) - $member->$field;
                $member->$field += $change;
                if ($field === 'energy' && $member->status !== 'paused') {
                    // Worn out with no energy, playing with some, whichever way the undo goes.
                    $member->status = $member->energy > 0 ? 'active' : 'fallen';
                }
                $member->timemodified = $now;
                $DB->update_record(self::T . 'member', $member);
                break;
            case 'item':
                $DB->delete_records(self::T . 'inventory', ['charid' => $entry->charid, 'itemid' => $entry->refid,
                    'courseid' => $entry->courseid]);
                break;
        }
        $undo = (object) [
            'charid' => $entry->charid,
            'courseid' => $entry->courseid,
            'resource' => $entry->resource,
            'amount' => $change,
            'reason' => 'undo',
            'cmid' => $entry->cmid,
            'refid' => $entry->id,
            'actorid' => $actorid,
            'timecreated' => $now,
        ];
        $undo->id = $DB->insert_record(self::T . 'ledger', $undo);
        $DB->update_record(self::T . 'ledger', (object) ['id' => $entry->id, 'undoneby' => $undo->id, 'timeundone' => $now]);
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'ledger', ['id' => $undo->id], '*', MUST_EXIST);
    }

    /**
     * Pauses a student in a course: they go on learning in Moodle but win nothing and do not play there.
     *
     * @param \stdClass $member
     * @param string $reason Seen only by the student and the teachers.
     * @param int $actorid The teacher.
     * @param int|null $now
     * @return \stdClass
     */
    public static function pause(\stdClass $member, string $reason, int $actorid, ?int $now = null, int $until = 0): \stdClass {
        global $DB;
        $now = $now ?? time();
        $member->status = 'paused';
        $member->statusreason = $reason;
        $member->statusby = $actorid;
        // Never for good: a week at most.
        $member->pausedto = min($until ?: PHP_INT_MAX, $now + regions::PAUSE_DAYS * DAYSECS);
        $member->timemodified = $now;
        $DB->update_record(self::T . 'member', $member);
        return $member;
    }

    /**
     * Brings back to the game the students whose pause has ended.
     *
     * @param int|null $now
     * @return int How many.
     */
    public static function wake(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $ended = $DB->get_records_select(self::T . 'member', "status = 'paused' AND pausedto > 0 AND pausedto <= ?", [$now]);
        foreach ($ended as $member) {
            self::resume($member, $now);
        }
        return count($ended);
    }

    /**
     * Brings a paused student back to the game.
     *
     * @param \stdClass $member
     * @param int|null $now
     * @return \stdClass
     */
    public static function resume(\stdClass $member, ?int $now = null): \stdClass {
        global $DB;
        if ($member->status !== 'paused') {
            return $member;
        }
        $member->status = $member->energy > 0 ? 'active' : 'fallen';
        $member->statusreason = null;
        $member->statusby = 0;
        $member->pausedto = 0;
        $member->timemodified = $now ?? time();
        $DB->update_record(self::T . 'member', $member);
        return $member;
    }

    /**
     * Brings a worn-out character back, after their recovery challenge or a rescue by their team.
     *
     * @param \stdClass $member
     * @param string $reason recovery or rescue.
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass|null The log entry, or null if they were not worn out.
     */
    public static function recover(\stdClass $member, string $reason, int $actorid, ?int $now = null): ?\stdClass {
        global $DB;
        if ($member->status !== 'fallen') {
            return null;
        }
        $transaction = $DB->start_delegated_transaction();
        $member->status = 'active';
        $DB->set_field(self::T . 'member', 'status', 'active', ['id' => $member->id]);
        $entry = self::energy($member, self::RECOVERED_ENERGY - (int) $member->energy, $reason, $actorid, 0, $now);
        $transaction->allow_commit();
        return $entry;
    }

    /**
     * Once a day for the characters who are playing: energy full again, and some breath back (up to the most).
     *
     * @param int|null $now
     * @return int How many were filled.
     */
    public static function regenerate(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        // The day starts at a different time in each time zone: the courses go by zone.
        $zones = [];
        foreach ($DB->get_records_menu(self::T . 'region', ['enabled' => 1], '', 'courseid, timezone') as $courseid => $zone) {
            $zones[(string) $zone][] = (int) $courseid;
        }
        $count = 0;
        foreach ($zones as $zone => $courseids) {
            $today = (new \DateTimeImmutable('@' . $now))->setTimezone(regions::zone($zone))->setTime(0, 0)->getTimestamp();
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $where = "status = 'active' AND lastregen < :today AND courseid $insql";
            $params['today'] = $today;
            $found = $DB->count_records_select(self::T . 'member', $where, $params);
            if ($found) {
                $DB->execute(
                    'UPDATE {' . self::T . "member}
                        SET energy = :energy,
                            breath = CASE WHEN breath + :daily > :breath THEN :breath2 ELSE breath + :daily2 END,
                            lastregen = :now, timemodified = :now2
                      WHERE $where",
                    $params + ['energy' => self::ENERGY_MAX, 'breath' => self::BREATH_MAX, 'breath2' => self::BREATH_MAX,
                        'daily' => self::BREATH_DAILY, 'daily2' => self::BREATH_DAILY, 'now' => $now, 'now2' => $now]
                );
            }
            $count += $found;
        }
        return $count;
    }

    /**
     * Start of the school week: Monday at midnight, in the time zone of the course.
     *
     * @param int $now
     * @param int $courseid 0: in the time zone of the site.
     * @return int
     */
    public static function week_start(int $now, int $courseid = 0): int {
        return progress::week_start($now, self::timezone($courseid));
    }

    /**
     * Start of the day, in the time zone of the course.
     *
     * @param int $now
     * @param int $courseid 0: in the time zone of the site.
     * @return int
     */
    public static function day_start(int $now, int $courseid = 0): int {
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone(self::timezone($courseid));
        return $date->setTime(0, 0)->getTimestamp();
    }

    /**
     * The time zone of the days and weeks of a course: the one of its region, or the one of the site.
     *
     * @param int $courseid 0: the site.
     * @return \DateTimeZone
     */
    public static function timezone(int $courseid = 0): \DateTimeZone {
        $zone = $courseid ? (string) (regions::get($courseid)->timezone ?? '99') : '99';
        return regions::zone($zone);
    }

    /**
     * The weekly cap of a character in the whole site: a setting of the plugin.
     *
     * @return int 0: no cap.
     */
    public static function site_weekly_cap(): int {
        $cap = get_config('local_oksigeniatesape', 'weeklycap');
        return $cap === false || $cap === '' ? self::SITE_WEEKLY_CAP : max(0, (int) $cap);
    }

    /**
     * Saber a character has earned since a time outside the mission (what goes under the weekly caps).
     *
     * @param \stdClass $member
     * @param int $since
     * @param bool $incourse Only in the course of the member; else in the whole site.
     * @return int
     */
    private static function earned(\stdClass $member, int $since, bool $incourse): int {
        global $DB;
        $params = ['charid' => $member->charid, 'since' => $since, 'courseid' => $member->courseid];
        $course = $incourse ? 'AND l.courseid = :courseid' : '';
        return (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(l.amount), 0)
               FROM {' . self::T . "ledger} l
              WHERE l.charid = :charid AND l.resource = 'saber' AND l.amount > 0 AND l.undoneby = 0 AND l.mission = 0
                    AND l.timecreated >= :since $course",
            $params
        );
    }

    /**
     * Sum of the entries of a resource in a course since a time, not counting those undone.
     *
     * @param \stdClass $member
     * @param string $resource
     * @param int $since
     * @param string $extra More conditions on l.
     * @return int
     */
    private static function sum(\stdClass $member, string $resource, int $since, string $extra): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(l.amount), 0)
               FROM {' . self::T . "ledger} l
              WHERE l.charid = ? AND l.courseid = ? AND l.resource = ? AND l.timecreated >= ? AND l.undoneby = 0
                    AND $extra",
            [$member->charid, $member->courseid, $resource, $since]
        );
    }

    /**
     * Adds saber (or takes it, when undoing) to the character and to its renown in the course.
     *
     * @param int $charid
     * @param \stdClass|false $member The character in the course, if it is still there.
     * @param int $amount
     * @param int $now
     */
    private static function add_saber(int $charid, $member, int $amount, int $now): void {
        global $DB;
        if (!$amount) {
            return;
        }
        if ($member) {
            // Like the saber, renown does not go below zero when something is undone.
            $renown = max(0, (int) $DB->get_field(self::T . 'member', 'renown', ['id' => $member->id]) + $amount);
            $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'renown' => $renown, 'timemodified' => $now]);
            $member->renown = $renown;
        }
        $char = $DB->get_record(self::T . 'char', ['id' => $charid], 'id, saber', MUST_EXIST);
        $saber = max(0, $char->saber + $amount);
        $DB->update_record(self::T . 'char', (object) [
            'id' => $char->id,
            'saber' => $saber,
            'level' => progress::level_for_saber($saber),
            'timemodified' => $now,
        ]);
    }

    /**
     * Writes a log entry.
     *
     * @param \stdClass $member
     * @param string $resource
     * @param int $amount
     * @param string $reason
     * @param int $actorid
     * @param int $cmid
     * @param int $refid
     * @param int $capped
     * @param int $now
     * @param bool $mission It comes from the mission (no weekly cap).
     * @return \stdClass
     */
    private static function record(
        \stdClass $member,
        string $resource,
        int $amount,
        string $reason,
        int $actorid,
        int $cmid,
        int $refid,
        int $capped,
        int $now,
        bool $mission = false
    ): \stdClass {
        global $DB;
        $entry = (object) [
            'mission' => $mission ? 1 : 0,
            'charid' => $member->charid,
            'courseid' => $member->courseid,
            'resource' => $resource,
            'amount' => $amount,
            'reason' => $reason,
            'cmid' => $cmid,
            'refid' => $refid,
            'actorid' => $actorid,
            'capped' => $capped,
            'timecreated' => $now,
        ];
        $entry->id = $DB->insert_record(self::T . 'ledger', $entry);
        return $entry;
    }
}
