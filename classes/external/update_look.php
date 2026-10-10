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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\classroom;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * Changes how the current student's character looks.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class update_look extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'appearance' => new external_value(PARAM_RAW, 'Look (JSON object)'),
            'lineage' => new external_value(PARAM_ALPHA, 'Lineage, empty for none', VALUE_DEFAULT, ''),
            'nameform' => new external_value(PARAM_ALPHA, 'm or f, empty to keep it', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Changes how the current student's character looks.
     *
     * @param int $courseid
     * @param string $appearance
     * @param string $lineage
     * @param string $nameform
     * @return array
     */
    public static function execute(
        int $courseid,
        string $appearance,
        string $lineage = '',
        string $nameform = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'appearance' => $appearance,
            'lineage' => $lineage,
            'nameform' => $nameform,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        characters::update_look(
            $char,
            self::appearance($params['appearance']),
            $params['lineage'] === '' ? null : $params['lineage'],
            $params['nameform'] === '' ? null : $params['nameform']
        );
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

    /**
     * The look sent by the client, as an array.
     *
     * @param string $json
     * @return array
     */
    private static function appearance(string $json): array {
        $appearance = json_decode($json, true);
        if (!is_array($appearance)) {
            throw new \invalid_parameter_exception('The look must be a JSON object');
        }
        return $appearance;
    }
}
