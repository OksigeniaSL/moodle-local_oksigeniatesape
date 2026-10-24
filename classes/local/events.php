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
 * - A knot for a team: the Embrollo tangles a sheet of mistakes for one team, which has until the day ends to clean it;
 *   cleaned, it counts for the strength of the expedition.
 * - Messengers: one team takes a hint to another of its choice: today the first hint of each scene is free for it.
 *
 * The last two go to a team (drawn at random; the teacher can change it before anything comes of the card). A mission
 * can give the cards its own names and texts (`cards`), and choose its deck (`deck`) among these and five more, which
 * make more of what is already there:
 *
 * - Minions out walking: a minion untangled on the road gives twice the gems.
 * - The owl flies: a being recognised in an encounter gives twice the gems.
 * - Night of fireflies: the secrets of the places glow more.
 * - A good nose: the creature sniffs once more in each minigame.
 * - Hungry for pages: the page of the day gives twice the breath.
 * - The Embrollo comes back (from the third lantern lit on): it stains a place the class already lit, whose lantern
 *   flickers until the class cleans it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class events {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] The cards there are. */
    public const CARDS = ['calm', 'breeze', 'footprint', 'exchange', 'knot', 'messengers', 'trade', 'minionday', 'owlnight',
        'glowworms', 'sniff', 'pageday', 'comeback'];

    /** @var string[] The deck of a mission that does not choose its own. */
    public const CORE = ['calm', 'breeze', 'footprint', 'exchange', 'knot', 'messengers', 'trade'];

    /** @var string[] The cards that go to a team. */
    public const TARGETED = ['knot', 'messengers'];

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
        $teams = array_map('intval', array_keys(self::teams($courseid)));
        if ($code === '') {
            $yesterday = self::today($courseid, engine::day_start($now, $courseid) - 1);
            $avoid = array_filter([$event->code ?? null, $yesterday->code ?? null]);
            $deck = self::deck($courseid);
            $cards = array_values(array_diff($deck, $avoid)) ?: $deck;
            if (questions::pick($courseid) === null) {
                // Without questions in the mission there is no footprint to follow.
                $cards = array_values(array_diff($cards, ['footprint'])) ?: ['calm'];
            }
            // The cards for a team, only with teams (two, for the messengers and the trade) and a sheet to tangle.
            $out = count($teams) < 2 ? array_merge(self::TARGETED, ['trade']) : (stains::can_tangle($courseid) ? [] : ['knot']);
            // The Embrollo comes back only from the middle of the mission on.
            if (!stains::can_come_back($courseid, $now)) {
                $out[] = 'comeback';
            }
            $cards = array_values(array_diff($cards, $out)) ?: ['calm'];
            $code = $cards[random_int(0, count($cards) - 1)];
        }
        if (
            (in_array($code, self::TARGETED, true) && count($teams) < ($code === 'messengers' ? 2 : 1))
            || ($code === 'trade' && count($teams) < 2)
        ) {
            throw new \moodle_exception('cardnotplayable', 'local_oksigeniatesape');
        }
        // What the card it replaces left, out: the knot of a team.
        self::undo($event);
        $data = [];
        if (in_array($code, self::TARGETED, true)) {
            $data['target'] = $teams[random_int(0, count($teams) - 1)];
        }
        if ($code === 'knot') {
            $stain = stains::for_team($courseid, $data['target'], $now);
            if (!$stain) {
                throw new \moodle_exception('cardnotplayable', 'local_oksigeniatesape');
            }
            $data['stain'] = (int) $stain->id;
        }
        if ($code === 'comeback') {
            $stain = stains::come_back($courseid, $now);
            if (!$stain) {
                throw new \moodle_exception('cardnotplayable', 'local_oksigeniatesape');
            }
            $data['comeback'] = (int) $stain->id;
        }
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
     * The deck of a course: the one its mission chooses, or the usual one.
     *
     * @param int $courseid
     * @return string[]
     */
    public static function deck(int $courseid): array {
        $mission = missions::get($courseid);
        $chosen = array_values(array_intersect(self::CARDS, (array) ($mission->data['deck'] ?? [])));
        return $chosen ?: self::CORE;
    }

    /**
     * The card of today as the students see it, once drawn: its code, name and text (those of the mission, or the usual
     * ones).
     *
     * @param int $courseid
     * @param int|null $now
     * @return array|null code, name, text.
     */
    public static function for_students(int $courseid, ?int $now = null): ?array {
        $event = self::today($courseid, $now);
        if (!$event || $event->status !== 'drawn') {
            return null;
        }
        $mission = missions::get($courseid);
        $card = $mission ? ($mission->data['cards'][$event->code] ?? []) : [];
        $name = $mission ? missions::text($card['name'] ?? '', $mission->skin, $mission->variant) : '';
        $text = $mission ? missions::text($card['text'] ?? '', $mission->skin, $mission->variant) : '';
        $sm = get_string_manager();
        return ['code' => (string) $event->code,
            'name' => $name !== '' ? $name : ($sm->string_exists('event_' . $event->code, 'local_oksigeniatesape') ?
                get_string('event_' . $event->code, 'local_oksigeniatesape') : $event->code),
            'text' => $text !== '' ? $text : ($sm->string_exists('event_' . $event->code . '_text', 'local_oksigeniatesape') ?
                get_string('event_' . $event->code . '_text', 'local_oksigeniatesape') : '')];
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
        self::undo($event);
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
            foreach ($DB->get_records(self::T . 'member', ['courseid' => $courseid, 'leftat' => 0]) as $member) {
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
     * The team a card goes to, chosen by the teacher instead of the one drawn, before anything comes of it.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function aim(int $courseid, int $groupid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::today($courseid, $now);
        if (!$event || $event->status !== 'drawn' || !in_array($event->code, self::TARGETED, true)) {
            throw new \moodle_exception('noevent', 'local_oksigeniatesape');
        }
        if (self::acted($event)) {
            throw new \moodle_exception('eventdone', 'local_oksigeniatesape');
        }
        if (!in_array($groupid, array_keys(self::teams($courseid)))) {
            throw new \invalid_parameter_exception('Not a team of the course');
        }
        $data = json_decode($event->data, true) ?: [];
        $data['target'] = $groupid;
        if (!empty($data['stain'])) {
            stains::move_team($courseid, (int) $data['stain'], $groupid);
        }
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'data' => json_encode($data), 'timemodified' => $now]);
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * The messengers take their hint to another team: today the first hint of each scene is free for it. Once.
     *
     * @param int $courseid
     * @param int $to Group of the team that receives it.
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function deliver(int $courseid, int $to, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::active($courseid, 'messengers', $now);
        if (!$event) {
            throw new \moodle_exception('noevent', 'local_oksigeniatesape');
        }
        $data = json_decode($event->data, true) ?: [];
        if (!empty($data['to'])) {
            return $event;
        }
        if ($to === (int) ($data['target'] ?? 0) || !in_array($to, array_keys(self::teams($courseid)))) {
            throw new \invalid_parameter_exception('Not another team of the course');
        }
        $data['to'] = $to;
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'data' => json_encode($data), 'timemodified' => $now]);
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * Whether the messengers brought a hint to a team today.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return bool
     */
    public static function brought(int $courseid, int $groupid, ?int $now = null): bool {
        $event = $groupid ? self::active($courseid, 'messengers', $now) : null;
        return $event && (int) ((json_decode($event->data, true) ?: [])['to'] ?? 0) === $groupid;
    }

    /**
     * In the trade, a team lends one of the items its members carry to another team until the day ends: the team that
     * receives it carries it in the scenes of the day, and the one that lends it does not. Once per team that lends; a
     * team does not lend to itself.
     *
     * @param int $courseid
     * @param int $from Group of the team that lends.
     * @param int $to Group of the team that receives.
     * @param string $item Code of the item.
     * @param int|null $now
     * @return \stdClass The event.
     */
    public static function lend(int $courseid, int $from, int $to, string $item, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $event = self::active($courseid, 'trade', $now);
        if (!$event) {
            throw new \moodle_exception('noevent', 'local_oksigeniatesape');
        }
        $teams = array_keys(self::teams($courseid));
        if ($from === $to || !in_array($from, $teams) || !in_array($to, $teams)) {
            throw new \invalid_parameter_exception('Not two teams of the course');
        }
        $data = json_decode($event->data, true) ?: [];
        if (isset($data['loans'][$from])) {
            return $event;
        }
        if (!in_array($item, self::team_items($courseid, $from), true)) {
            throw new \invalid_parameter_exception('Not an item of the team');
        }
        $data['loans'][$from] = ['to' => $to, 'item' => $item];
        $DB->update_record(self::T . 'event', (object) ['id' => $event->id, 'data' => json_encode($data), 'timemodified' => $now]);
        return $DB->get_record(self::T . 'event', ['id' => $event->id], '*', MUST_EXIST);
    }

    /**
     * The items a team carries: those of its members, once each.
     *
     * @param int $courseid
     * @param int $groupid
     * @return string[] Codes.
     */
    private static function team_items(int $courseid, int $groupid): array {
        global $DB;
        $chars = array_map(fn($m) => (int) $m->charid, teams::members($courseid, $groupid));
        if (!$chars) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($chars);
        return array_values(array_unique($DB->get_fieldset_sql(
            'SELECT i.code
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.courseid = ? AND v.charid ' . $insql . '
           ORDER BY i.code',
            array_merge([$courseid], $params)
        )));
    }

    /**
     * The items a student carries today with the trade: theirs, without those their team lent, and with those lent to
     * their team.
     *
     * @param int $courseid
     * @param int $groupid Their team (0: none).
     * @param string[] $items What they carry.
     * @param int|null $now
     * @return string[]
     */
    public static function traded(int $courseid, int $groupid, array $items, ?int $now = null): array {
        $event = $groupid ? self::active($courseid, 'trade', $now) : null;
        if (!$event) {
            return $items;
        }
        foreach ((json_decode($event->data, true) ?: [])['loans'] ?? [] as $from => $loan) {
            if ((int) $from === $groupid) {
                $items = array_values(array_diff($items, [$loan['item']]));
            } else if ((int) $loan['to'] === $groupid && !in_array($loan['item'], $items, true)) {
                $items[] = $loan['item'];
            }
        }
        return $items;
    }

    /**
     * The event as the class screen shows it.
     *
     * @param \stdClass|null $event
     * @return array|null code, status, question (with the right option, for the teacher; none if not a footprint), solved,
     *     gifts, target (the team of a card for a team), to (the team the messengers went to), cleaned (the knot of the
     *     team), name and text (those of the mission, if it has them).
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
        $mission = missions::get((int) $event->courseid);
        $card = $mission ? ($mission->data['cards'][$event->code] ?? []) : [];
        // The trade: what each team lent, and what each one that has not lent yet could lend.
        $loans = [];
        $offers = [];
        if ($event->code === 'trade') {
            $names = self::item_names();
            foreach ($data['loans'] ?? [] as $from => $loan) {
                $loans[] = ['from' => (int) $from, 'to' => (int) $loan['to'], 'item' => (string) $loan['item'],
                    'name' => $names[$loan['item']] ?? (string) $loan['item']];
            }
            foreach (array_keys(self::teams((int) $event->courseid)) as $groupid) {
                if (!isset($data['loans'][$groupid])) {
                    $offers[] = ['team' => (int) $groupid, 'items' => array_map(fn($code) => ['code' => $code,
                        'name' => $names[$code] ?? $code], self::team_items((int) $event->courseid, (int) $groupid))];
                }
            }
        }
        return array_filter([
            'id' => (int) $event->id,
            'code' => $event->code,
            'status' => $event->status,
            'question' => $question,
            'solved' => array_key_exists('solved', $data) ? ($data['solved'] ? 'right' : 'wrong') : '',
            'gifts' => $gifts,
            'target' => (int) ($data['target'] ?? 0),
            'to' => (int) ($data['to'] ?? 0),
            'cleaned' => self::cleaned($data),
            'loans' => $loans,
            'offers' => $offers,
            'name' => $mission ? missions::text($card['name'] ?? '', $mission->skin, $mission->variant) : '',
            'text' => $mission ? missions::text($card['text'] ?? '', $mission->skin, $mission->variant) : '',
        ], fn($value) => $value !== null);
    }

    /**
     * The names of the items, by code.
     *
     * @return array
     */
    private static function item_names(): array {
        global $DB;
        return array_map(fn($name) => format_string($name), $DB->get_records_menu(self::T . 'item', null, '', 'code, name'));
    }

    /**
     * Whether something has already come of an event (then it can no longer be changed).
     *
     * @param \stdClass $event
     * @return bool
     */
    private static function acted(\stdClass $event): bool {
        $data = json_decode($event->data ?? '', true) ?: [];
        return isset($data['solved']) || !empty($data['gifts']) || !empty($data['to']) || !empty($data['loans'])
            || self::cleaned($data);
    }

    /**
     * Whether the team cleaned the knot of its card.
     *
     * @param array $data Of the event.
     * @return bool
     */
    private static function cleaned(array $data): bool {
        global $DB;
        $stain = (int) ($data['stain'] ?? $data['comeback'] ?? 0);
        return $stain && (int) $DB->get_field(self::T . 'stain', 'cleanedat', ['id' => $stain]) > 0;
    }

    /**
     * What a card left that has to go with it when it is changed or skipped: the knot of a team, if not cleaned.
     *
     * @param \stdClass|null $event
     */
    private static function undo(?\stdClass $event): void {
        $data = $event ? (json_decode($event->data ?? '', true) ?: []) : [];
        if (!empty($data['stain'])) {
            stains::move_team((int) $event->courseid, (int) $data['stain'], 0);
        }
        if (!empty($data['comeback'])) {
            stains::go_back((int) $event->courseid, (int) $data['comeback']);
        }
    }

    /**
     * The teams of a course: the groups of its grouping.
     *
     * @param int $courseid
     * @return array Group id => group.
     */
    private static function teams(int $courseid): array {
        $region = regions::get($courseid);
        return groups_get_all_groups(games::course($courseid), 0, (int) ($region->groupingid ?? 0));
    }
}
