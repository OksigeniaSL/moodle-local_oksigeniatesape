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
 * What the hero wears: two places, the hand and the pack, for the items of the mission they have won that can be worn
 * (the mission says where each goes and what it does so). Worn, an item does something more than opening the options
 * that ask for it, which it does from the backpack anyway: the lantern in the hand lights where the being of the walked
 * encounter hides; the magnifier in the hand gives a look more in each minigame; the net in the pack catches an animal
 * that slips in the Defence of the nest. Nothing is bought: everything is won.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class equipment {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] The places: the hand and the pack. */
    public const SLOTS = ['hand', 'pack'];

    /**
     * What a hero wears in a course.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return string[] slot => code of the item.
     */
    public static function worn(\stdClass $char, int $courseid): array {
        global $DB;
        return $DB->get_records_sql_menu(
            "SELECT v.slot, i.code
               FROM {" . self::T . "inventory} v
               JOIN {" . self::T . "item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ? AND v.slot IS NOT NULL AND v.slot <> ''",
            [$char->id, $courseid]
        );
    }

    /**
     * Where an item of the mission of a course is worn, if it can be.
     *
     * @param int $courseid
     * @param string $code
     * @return string hand, pack or empty.
     */
    public static function fits(int $courseid, string $code): string {
        $mission = missions::get($courseid);
        $slot = (string) ($mission->data['items'][$code]['slot'] ?? '');
        return in_array($slot, self::SLOTS, true) ? $slot : '';
    }

    /**
     * Wears an item the hero carries, in its place (what was there goes back to the backpack); or takes it off.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param string $code
     * @param bool $on
     */
    public static function wear(\stdClass $char, int $courseid, string $code, bool $on): void {
        global $DB;
        $slot = self::fits($courseid, $code);
        $row = $DB->get_record_sql(
            "SELECT v.*
               FROM {" . self::T . "inventory} v
               JOIN {" . self::T . "item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ? AND i.code = ?",
            [$char->id, $courseid, $code]
        );
        if (!$slot || !$row) {
            throw new \moodle_exception('noequip', 'local_oksigeniatesape');
        }
        $transaction = $DB->start_delegated_transaction();
        if ($on) {
            $DB->set_field(self::T . 'inventory', 'slot', null, ['charid' => $char->id, 'courseid' => $courseid,
                'slot' => $slot]);
        }
        $DB->set_field(self::T . 'inventory', 'slot', $on ? $slot : null, ['id' => $row->id]);
        $transaction->allow_commit();
    }
}
