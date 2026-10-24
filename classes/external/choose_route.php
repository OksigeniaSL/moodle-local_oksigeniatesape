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
use local_oksigeniatesape\local\routes;

/**
 * The scout of a team (anyone, without one) chooses the way of the team into the place where the class is now.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class choose_route extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'route' => new external_value(PARAM_ALPHANUMEXT, 'Code of the way'),
        ]);
    }

    /**
     * Chooses it.
     *
     * @param int $courseid
     * @param string $route
     * @return array chosen: the ways of the team, place => way.
     */
    public static function execute(int $courseid, string $route): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'route' => $route]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $chosen = routes::choose($params['courseid'], (int) $USER->id, $params['route']);
        return ['chosen' => array_map(
            fn($place, $way) => ['place' => (string) $place, 'route' => (string) $way],
            array_keys($chosen),
            array_values($chosen)
        )];
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'chosen' => new external_multiple_structure(new external_single_structure([
                'place' => new external_value(PARAM_ALPHANUMEXT, 'The place'),
                'route' => new external_value(PARAM_ALPHANUMEXT, 'The way'),
            ])),
        ]);
    }
}
