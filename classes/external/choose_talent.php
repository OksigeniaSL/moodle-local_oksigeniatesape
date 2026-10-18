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
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\talents;

/**
 * A student chooses the talent that a level brought them, one of the two of their role.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class choose_talent extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course where they play'),
            'level' => new external_value(PARAM_INT, 'The level that brought the choice'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'The talent chosen'),
        ]);
    }

    /**
     * The current student chooses.
     *
     * @param int $courseid
     * @param int $level
     * @param string $code
     * @return array
     */
    public static function execute(int $courseid, int $level, string $code): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'level' => $level,
            'code' => $code]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        talents::choose($char, $params['level'], $params['code']);
        return ['talents' => talents::view($char)];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'talents' => new external_multiple_structure(new external_single_structure([
                'level' => new external_value(PARAM_INT, 'The level that brought it'),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Talent'),
                'name' => new external_value(PARAM_TEXT, 'Its name'),
                'description' => new external_value(PARAM_TEXT, 'What it does'),
            ])),
        ]);
    }
}
