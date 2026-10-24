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

namespace local_oksigeniatesape\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\equipment;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * A student wears an item they carry, in the hand or in the pack, or takes it off.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class equip extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'item' => new external_value(PARAM_ALPHANUMEXT, 'Code of the item'),
            'on' => new external_value(PARAM_BOOL, 'Wear it (false: take it off)'),
        ]);
    }

    /**
     * Wears or takes off the item.
     *
     * @param int $courseid
     * @param string $item
     * @param bool $on
     * @return array The world of the student.
     */
    public static function execute(int $courseid, string $item, bool $on): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'item' => $item, 'on' => $on]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('noequip', 'local_oksigeniatesape');
        }
        equipment::wear($char, $params['courseid'], $params['item'], $params['on']);
        return view::world($params['courseid'], (int) $USER->id);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return get_world::execute_returns();
    }
}
