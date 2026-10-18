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
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniatesape\local\stains;

/**
 * The teacher takes a stain of the Embrollo as cleaned by the class (aloud, on the class screen), or rubs it out.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erase_stain extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'stainid' => new external_value(PARAM_INT, 'Stain'),
        ]);
    }

    /**
     * Takes a stain as cleaned.
     *
     * @param int $courseid
     * @param int $stainid
     * @return array
     */
    public static function execute(int $courseid, int $stainid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'stainid' => $stainid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        stains::erase($params['courseid'], $params['stainid']);
        return ['erased' => true];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'erased' => new external_value(PARAM_BOOL, 'Taken as cleaned'),
        ]);
    }
}
