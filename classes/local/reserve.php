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
 * The reserve of breath of a team: a common resource, apart from the breath of each one. It fills when the team keeps
 * all its commitments of a place and when its task of a place is validated; it is spent on what only the team can do,
 * proposed by a member and confirmed by another from their tablet: a breather of energy for the team, or getting up a
 * worn-out member who asks for it (without the healer). Proposals are open the day they are made.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class reserve {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Most breath in the reserve. */
    public const MAX = 10;

    /** @var int Breath for the commitments of a place kept by the whole team. */
    public const PACTS = 2;

    /** @var int Breath for the task of a place validated for the team. */
    public const TASK = 1;

    /** @var int[] What each spending costs. */
    public const COSTS = ['refresh' => 3, 'rise' => 4];

    /** @var int Energy for each member, in a breather of the team. */
    public const REFRESH_ENERGY = 1;

    /**
     * The row of a team, made the first time it is needed.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return \stdClass
     */
    private static function team(int $courseid, int $groupid, ?int $now = null): \stdClass {
        global $DB;
        $row = $DB->get_record(self::T . 'team', ['groupid' => $groupid]);
        if (!$row) {
            $now = $now ?? time();
            $id = $DB->insert_record(self::T . 'team', (object) ['groupid' => $groupid, 'courseid' => $courseid, 'books' => 0,
                'breath' => 0, 'timecreated' => $now, 'timemodified' => $now]);
            $row = $DB->get_record(self::T . 'team', ['id' => $id], '*', MUST_EXIST);
        }
        return $row;
    }

    /**
     * The row of a team, made if it is not there yet.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return \stdClass
     */
    public static function row(int $courseid, int $groupid, ?int $now = null): \stdClass {
        return self::team($courseid, $groupid, $now);
    }

    /**
     * Breath in the reserve of a team.
     *
     * @param int $groupid
     * @return int
     */
    public static function amount(int $groupid): int {
        global $DB;
        return (int) $DB->get_field(self::T . 'team', 'breath', ['groupid' => $groupid]);
    }

    /**
     * Takes breath from the reserve of a team, if it has that much (a cover in a battle).
     *
     * @param int $groupid
     * @param int $amount
     * @param int|null $now
     * @return bool Whether it had it.
     */
    public static function take(int $groupid, int $amount, ?int $now = null): bool {
        global $DB;
        $row = $DB->get_record(self::T . 'team', ['groupid' => $groupid]);
        if (!$row || (int) $row->breath < $amount) {
            return false;
        }
        $where = 'id = ? AND breath = ?';
        $DB->set_field_select(self::T . 'team', 'breath', (int) $row->breath - $amount, $where, [$row->id, $row->breath]);
        $DB->set_field(self::T . 'team', 'timemodified', $now ?? time(), ['id' => $row->id]);
        return (int) $DB->get_field(self::T . 'team', 'breath', ['id' => $row->id]) === (int) $row->breath - $amount;
    }

    /**
     * Fills the reserve for something the team did, once (up to the most it holds).
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $key What filled it (pacts-<place>, task-<challenge>).
     * @param int $amount
     * @param int|null $now
     */
    public static function credit(int $courseid, int $groupid, string $key, int $amount, ?int $now = null): void {
        global $DB;
        $row = self::team($courseid, $groupid, $now);
        $filled = json_decode((string) $row->filled, true) ?: [];
        if (isset($filled[$key])) {
            return;
        }
        // What went in, as it went in: taken back, the same comes out.
        $given = max(0, min($amount, banners::reserve_max($courseid, $groupid) - (int) $row->breath));
        $filled[$key] = $given;
        $DB->update_record(self::T . 'team', (object) ['id' => $row->id, 'breath' => (int) $row->breath + $given,
            'filled' => json_encode($filled), 'timemodified' => $now ?? time()]);
    }

    /**
     * Takes back what something filled, if it did (a commitment that was kept and is not any more).
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $key
     * @param int|null $now
     */
    public static function uncredit(int $courseid, int $groupid, string $key, ?int $now = null): void {
        global $DB;
        $row = $DB->get_record(self::T . 'team', ['groupid' => $groupid]);
        $filled = $row ? (json_decode((string) $row->filled, true) ?: []) : [];
        if (!isset($filled[$key])) {
            return;
        }
        $back = (int) $filled[$key];
        unset($filled[$key]);
        $DB->update_record(self::T . 'team', (object) ['id' => $row->id, 'breath' => max(0, (int) $row->breath - $back),
            'filled' => json_encode((object) $filled), 'timemodified' => $now ?? time()]);
    }

    /**
     * The day, as yyyymmdd in the time zone of the course.
     *
     * @param int $courseid
     * @param int $now
     * @return int
     */
    private static function day(int $courseid, int $now): int {
        return (int) (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid))->format('Ymd');
    }

    /**
     * A member of a team proposes a spending; another member has to confirm it. To get up, only who is worn out asks.
     *
     * @param int $courseid
     * @param \stdClass $char Who proposes it.
     * @param string $kind refresh or rise.
     * @param int|null $now
     * @return \stdClass The proposal.
     */
    public static function propose(int $courseid, \stdClass $char, string $kind, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!isset(self::COSTS[$kind])) {
            throw new \invalid_parameter_exception('Unknown spending');
        }
        $team = teams::of($courseid, (int) $char->userid);
        if (!$team) {
            throw new \moodle_exception('noteam', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $courseid, $now);
        if ($kind === 'rise' && $member->status !== 'fallen') {
            throw new \invalid_parameter_exception('Only who is worn out asks to get up');
        }
        if (self::amount((int) $team->id) < self::COSTS[$kind]) {
            throw new \moodle_exception('reserveshort', 'local_oksigeniatesape');
        }
        $day = self::day($courseid, $now);
        $open = $DB->get_record(self::T . 'teamspend', ['groupid' => $team->id, 'kind' => $kind, 'status' => 'open',
            'day' => $day]);
        if ($open) {
            return $open;
        }
        $id = $DB->insert_record(self::T . 'teamspend', (object) ['courseid' => $courseid, 'groupid' => $team->id,
            'kind' => $kind, 'charid' => $char->id, 'confirmedby' => 0, 'status' => 'open', 'day' => $day,
            'timecreated' => $now, 'timemodified' => $now]);
        return $DB->get_record(self::T . 'teamspend', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Another member of the team confirms a proposal of today: the reserve pays and the team gets it.
     *
     * @param int $courseid
     * @param \stdClass $char Who confirms it.
     * @param int $spendid
     * @param int|null $now
     * @return \stdClass The proposal, done.
     */
    public static function confirm(int $courseid, \stdClass $char, int $spendid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $spend = $DB->get_record(self::T . 'teamspend', ['id' => $spendid, 'courseid' => $courseid]);
        $team = teams::of($courseid, (int) $char->userid);
        if (!$spend || !$team || (int) $spend->groupid !== (int) $team->id || $spend->status !== 'open') {
            throw new \moodle_exception('nospend', 'local_oksigeniatesape');
        }
        if ((int) $spend->charid === (int) $char->id) {
            throw new \invalid_parameter_exception('Another member confirms it');
        }
        if ((int) $spend->day !== self::day($courseid, $now)) {
            throw new \moodle_exception('nospend', 'local_oksigeniatesape');
        }
        $row = self::team($courseid, (int) $team->id, $now);
        $cost = self::COSTS[$spend->kind];
        if ((int) $row->breath < $cost) {
            throw new \moodle_exception('reserveshort', 'local_oksigeniatesape');
        }
        $asker = $DB->get_record(self::T . 'member', ['charid' => $spend->charid, 'courseid' => $courseid]);
        if ($spend->kind === 'rise' && (!$asker || $asker->status !== 'fallen')) {
            throw new \moodle_exception('nospend', 'local_oksigeniatesape');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record(self::T . 'team', (object) ['id' => $row->id, 'breath' => (int) $row->breath - $cost,
            'timemodified' => $now]);
        $DB->update_record(self::T . 'teamspend', (object) ['id' => $spend->id, 'status' => 'done',
            'confirmedby' => $char->id, 'timemodified' => $now]);
        if ($spend->kind === 'refresh') {
            foreach (teams::members($courseid, (int) $team->id) as $mate) {
                unset($mate->userid);
                if ((int) $mate->energy < engine::ENERGY_MAX) {
                    engine::energy($mate, self::REFRESH_ENERGY, 'reserve', 0, (int) $spend->id, $now);
                }
            }
        } else {
            engine::recover($asker, 'rescue', 0, $now);
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'teamspend', ['id' => $spend->id], '*', MUST_EXIST);
    }

    /**
     * Who proposed it takes it back, while nobody has confirmed it.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param int $spendid
     */
    public static function cancel(int $courseid, \stdClass $char, int $spendid): void {
        global $DB;
        $DB->set_field_select(
            self::T . 'teamspend',
            'status',
            'cancelled',
            "id = ? AND courseid = ? AND charid = ? AND status = 'open'",
            [$spendid, $courseid, $char->id]
        );
    }

    /**
     * The reserve as a member of the team sees it: what it has and what it costs, and the proposals of today.
     *
     * @param int $courseid
     * @param int $groupid
     * @param \stdClass $char Who looks.
     * @param array $names Names of the members of the team, by user id.
     * @param int|null $now
     * @return array breath, max, costs (kind => cost), open (id, kind, mine, by).
     */
    public static function view(int $courseid, int $groupid, \stdClass $char, array $names, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $open = [];
        $where = "groupid = ? AND status = 'open' AND day = ?";
        foreach ($DB->get_records_select(self::T . 'teamspend', $where, [$groupid, self::day($courseid, $now)], 'id') as $spend) {
            $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $spend->charid]);
            $open[] = ['id' => (int) $spend->id, 'kind' => (string) $spend->kind, 'mine' => (int) $spend->charid === (int) $char->id,
                'by' => (string) ($names[$userid] ?? '')];
        }
        $costs = [];
        foreach (self::COSTS as $kind => $cost) {
            $costs[] = ['kind' => $kind, 'cost' => $cost];
        }
        return ['breath' => self::amount($groupid), 'max' => banners::reserve_max($courseid, $groupid), 'costs' => $costs,
            'open' => $open];
    }
}
