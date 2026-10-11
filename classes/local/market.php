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
 * The market of the valley: where gems go. Six things, all of them seen by the others: three paints for the creature,
 * two auras for the hero and the lantern of the team's banner, which the team buys together (the first thing the gems
 * of several students pay for). Nothing is bought with money and nothing changes how the game plays.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class market {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var array What there is: kind (paint, aura or banner), price in gems and whether the team buys it together. */
    public const ITEMS = [
        'paint-gold' => ['kind' => 'paint', 'price' => 10, 'team' => false],
        'paint-night' => ['kind' => 'paint', 'price' => 10, 'team' => false],
        'paint-snow' => ['kind' => 'paint', 'price' => 10, 'team' => false],
        'aura-fireflies' => ['kind' => 'aura', 'price' => 15, 'team' => false],
        'aura-leaves' => ['kind' => 'aura', 'price' => 15, 'team' => false],
        'banner-lantern' => ['kind' => 'banner', 'price' => 20, 'team' => true],
    ];

    /**
     * Buys something for oneself and wears it; or, for the team, puts gems towards it.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param int $amount For the team: gems to put in (as many as are missing at most).
     * @param int|null $now
     */
    public static function buy(int $courseid, int $userid, string $code, int $amount = 0, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $item = self::ITEMS[$code] ?? null;
        $char = characters::get($userid);
        $member = $char ? $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]) : null;
        if (!$item || !$member || $member->status === 'paused' || !regions::enabled($courseid)) {
            throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
        }
        $transaction = $DB->start_delegated_transaction();
        if ($item['team']) {
            $team = teams::of($courseid, $userid);
            if (!$team) {
                throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
            }
            $missing = $item['price'] - self::team_given($courseid, (int) $team->id, $code);
            $amount = min($amount, $missing, (int) $char->gems);
            if ($amount <= 0) {
                throw new \moodle_exception($missing <= 0 ? 'alreadyowned' : 'notenoughgems', 'local_oksigeniatesape');
            }
            engine::gems($member, -$amount, 'market', 0, 0, 0, $now);
            $DB->insert_record(self::T . 'purchase', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'groupid' => $team->id, 'code' => $code, 'amount' => $amount, 'timecreated' => $now]);
        } else {
            if ($DB->record_exists(self::T . 'purchase', ['charid' => $char->id, 'code' => $code, 'groupid' => 0])) {
                throw new \moodle_exception('alreadyowned', 'local_oksigeniatesape');
            }
            engine::gems($member, -$item['price'], 'market', 0, 0, 0, $now);
            $DB->insert_record(self::T . 'purchase', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'groupid' => 0, 'code' => $code, 'amount' => $item['price'], 'timecreated' => $now]);
            self::wear($userid, $code);
        }
        $transaction->allow_commit();
    }

    /**
     * Wears something one owns, or takes off what one wears of a kind.
     *
     * @param int $userid
     * @param string $code What to wear, or a kind (paint, aura) to take it off.
     */
    public static function wear(int $userid, string $code): void {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
        }
        $worn = self::worn($char);
        if (in_array($code, ['paint', 'aura'], true)) {
            unset($worn[$code]);
        } else {
            $item = self::ITEMS[$code] ?? null;
            $owned = $item && !$item['team'] && $DB->record_exists(self::T . 'purchase', ['charid' => $char->id, 'code' => $code,
                'groupid' => 0]);
            if (!$owned) {
                throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
            }
            $worn[$item['kind']] = $code;
        }
        $DB->set_field(self::T . 'char', 'cosmetics', $worn ? json_encode($worn) : '', ['id' => $char->id]);
    }

    /**
     * What a character wears.
     *
     * @param \stdClass $char
     * @return array kind => code.
     */
    public static function worn(\stdClass $char): array {
        $worn = json_decode((string) ($char->cosmetics ?? ''), true);
        return is_array($worn) ? array_intersect_key($worn, ['paint' => 1, 'aura' => 1]) : [];
    }

    /**
     * Gems the members of a team have put towards something.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $code
     * @return int
     */
    public static function team_given(int $courseid, int $groupid, string $code): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT COALESCE(SUM(amount), 0) FROM {' . self::T . 'purchase} WHERE courseid = ? AND groupid = ? AND code = ?',
            [$courseid, $groupid, $code]
        );
    }

    /**
     * What the team has of the market: the codes it has paid in full.
     *
     * @param int $courseid
     * @param int $groupid
     * @return string[]
     */
    public static function team_owns(int $courseid, int $groupid): array {
        $owns = [];
        foreach (self::ITEMS as $code => $item) {
            if ($item['team'] && self::team_given($courseid, $groupid, $code) >= $item['price']) {
                $owns[] = $code;
            }
        }
        return $owns;
    }

    /**
     * The market as a student sees it.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int $userid
     * @return array code, kind, price, team, owned, worn, given (by the team), mine (given by the student).
     */
    public static function for_student(\stdClass $char, int $courseid, int $userid): array {
        global $DB;
        $owned = $DB->get_fieldset_select(self::T . 'purchase', 'code', 'charid = ? AND groupid = 0', [$char->id]);
        $worn = self::worn($char);
        $team = teams::of($courseid, $userid);
        $list = [];
        foreach (self::ITEMS as $code => $item) {
            if ($item['team'] && !$team) {
                continue;
            }
            $given = $item['team'] ? self::team_given($courseid, (int) $team->id, $code) : 0;
            $mine = $item['team'] ? (int) $DB->get_field_sql(
                'SELECT COALESCE(SUM(amount), 0) FROM {' . self::T . 'purchase}
                  WHERE charid = ? AND courseid = ? AND groupid = ? AND code = ?',
                [$char->id, $courseid, $team->id, $code]
            ) : 0;
            $list[] = [
                'code' => $code,
                'kind' => $item['kind'],
                'price' => $item['price'],
                'team' => $item['team'],
                'owned' => $item['team'] ? $given >= $item['price'] : in_array($code, $owned, true),
                'worn' => ($worn[$item['kind']] ?? '') === $code,
                'given' => $given,
                'mine' => $mine,
            ];
        }
        return $list;
    }
}
