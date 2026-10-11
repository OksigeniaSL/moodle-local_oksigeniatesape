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
 * The event of the day: a card the teacher draws on the class screen when the class starts, and may change or skip.
 *
 * - Calm: nobody in the course loses energy today.
 * - Breeze: activities completed today give double saber (within the weekly cap).
 * - Footprint: a quick question for the whole class; if the class gets it right, saber for everybody.
 * - Exchange: each team gives breath to another team of its choice.
 *
 * The battle of the boss joins the deck when the battles arrive.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] The cards of the deck. */
    public const CARDS = ['calm', 'breeze', 'footprint', 'exchange'];

    /** @var int Saber for each player when the class solves the footprint. */
    public const FOOTPRINT_SABER = 5;

    /** @var int Breath for each member of the team that receives a gift. */
    public const EXCHANGE_BREATH = 1;

    /**
     * The event of today in a course, drawn or skipped; null if nobody has drawn one yet.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function today(int $courseid, ?int $now = null): ?\stdClass {
        global $DB;
        $day = engine::day_start($now ?? time(), $courseid);
        return $DB->get_record(self::T . 'event', ['courseid' => $courseid, 'day' => $day]) ?: null;
    }

    /**
     * Whether a card is in play today in a course (for the passive ones: calm and breeze).
     *
     * @param int $courseid
     * @param string $code
     * @param int|null $now
     * @return \stdClass|null The event, if so.
     */
    public static function active(int $courseid, string $code, ?int $now = null): ?\stdClass {
        $event = self::today($courseid, $now);
        return $event && $event->status === 'drawn' && $event->code === $code ? $event : null;
    }

    /**
     * Draws the card of the day, or another one if there is one already and nothing has come of it yet. It never repeats
     * the card of yesterday nor the one it replaces.
     *
     * @param int $courseid
     * @param string $code A given card instead of a random one.
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function draw(int $courseid, string $code = '', ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if ($code !== '' && !in_array($code, self::CARDS, true)) {
            throw new \invalid_parameter_exception('Unknown card');
        }
        $event = self::today($courseid, $now);
        if ($event && self::acted($event)) {
            throw new \moodle_exception('eventdone', 'local_oksigeniatesape');
        }
        if ($code === '') {
            $yesterday = self::today($courseid, engine::day_start($now, $courseid) - 1);
            $avoid = array_filter([$event->code ?? null, $yesterday->code ?? null]);
            $cards = array_values(array_diff(self::CARDS, $avoid)) ?: self::CARDS;
            if (questions::pick($courseid) === null) {
                // Without questions in the mission there is no footprint to follow.
                $cards = array_values(array_diff($cards, ['footprint'])) ?: ['calm'];
            }
            $code = $cards[random_int(0, count($cards) - 1)];
        }
        $data = [];
        if ($code === 'footprint') {
            $previous = json_decode($event->data ?? '', true) ?: [];
            $data['question'] = questions::pick($courseid, array_filter([$previous['question'] ?? null]));
            if ($data['question'] === null) {
                throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
            }
        }
        $record = (object) [
            'courseid' => $courseid,
            'day' => engine::day_start($now, $courseid),
            'code' => $code,
            'status' => 'drawn',
            'data' => json_encode($data),
            'timemodified' => $now,
        ];
        if ($event) {
            $record->id = $event->id;
            $DB->update_record(self::T . 'event', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::T . 'event', $record);
        }
        return $DB->get_record(self::T . 'event', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * No event today.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass The event, skipped.
     */
    public static function skip(int $courseid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::today($courseid, $now);
        if ($event && self::acted($event)) {
            throw new \moodle_exception('eventdone', 'local_oksigeniatesape');
        }
        if (!$event) {
            $event = self::draw($courseid, 'calm', $now);
        }
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'status' => 'skipped', 'timemodified' => $now]);
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * The class answered the question of the footprint: if right, saber for everybody who plays in the course. Once.
     *
     * @param int $courseid
     * @param bool $right
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function solve(int $courseid, bool $right, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::active($courseid, 'footprint', $now);
        if (!$event) {
            throw new \moodle_exception('noevent', 'local_oksigeniatesape');
        }
        $data = json_decode($event->data, true) ?: [];
        if (isset($data['solved'])) {
            return $event;
        }
        $transaction = $DB->start_delegated_transaction();
        $data['solved'] = $right;
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'data' => json_encode($data), 'timemodified' => $now]);
        if ($right) {
            foreach ($DB->get_records(self::T . 'member', ['courseid' => $courseid]) as $member) {
                engine::saber($member, self::FOOTPRINT_SABER, 'event', $actorid, 0, (int) $event->id, $now);
            }
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * In the exchange, a team gives breath to another: each member of the other team gets some. Once per team that
     * gives; a team does not give to itself.
     *
     * @param int $courseid
     * @param int $from Group of the team that gives.
     * @param int $to Group of the team that receives.
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function gift(int $courseid, int $from, int $to, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::active($courseid, 'exchange', $now);
        if (!$event) {
            throw new \moodle_exception('noevent', 'local_oksigeniatesape');
        }
        $teams = array_keys(self::teams($courseid));
        if ($from === $to || !in_array($from, $teams) || !in_array($to, $teams)) {
            throw new \invalid_parameter_exception('Not two teams of the course');
        }
        $data = json_decode($event->data, true) ?: [];
        if (isset($data['gifts'][$from])) {
            return $event;
        }
        $transaction = $DB->start_delegated_transaction();
        $data['gifts'][$from] = $to;
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'data' => json_encode($data), 'timemodified' => $now]);
        foreach (teams::members($courseid, $to) as $member) {
            engine::breath($member, self::EXCHANGE_BREATH, 'event', $actorid, (int) $event->id, $now);
        }
        $transaction->allow_commit();
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * The event as the class screen shows it.
     *
     * @param \stdClass|null $event
     * @return array|null code, status, question (with the right option, for the teacher; none if not a footprint), solved,
     *     gifts.
     */
    public static function export(?\stdClass $event): ?array {
        if (!$event) {
            return null;
        }
        $data = json_decode($event->data ?? '', true) ?: [];
        $question = null;
        if ($event->code === 'footprint' && !empty($data['question'])) {
            $question = questions::get((int) $event->courseid, (string) $data['question'], (int) $event->id);
        }
        $gifts = [];
        foreach ($data['gifts'] ?? [] as $from => $to) {
            $gifts[] = ['from' => (int) $from, 'to' => (int) $to];
        }
        return array_filter([
            'id' => (int) $event->id,
            'code' => $event->code,
            'status' => $event->status,
            'question' => $question,
            'solved' => array_key_exists('solved', $data) ? ($data['solved'] ? 'right' : 'wrong') : '',
            'gifts' => $gifts,
        ], fn($value) => $value !== null);
    }

    /**
     * Whether something has already come of an event (then it can no longer be changed).
     *
     * @param \stdClass $event
     * @return bool
     */
    private static function acted(\stdClass $event): bool {
        $data = json_decode($event->data ?? '', true) ?: [];
        return isset($data['solved']) || !empty($data['gifts']);
    }

    /**
     * The teams of a course: the groups of its grouping.
     *
     * @param int $courseid
     * @return array Group id => group.
     */
    private static function teams(int $courseid): array {
        $region = regions::get($courseid);
        return groups_get_all_groups($courseid, 0, (int) ($region->groupingid ?? 0));
    }
}
