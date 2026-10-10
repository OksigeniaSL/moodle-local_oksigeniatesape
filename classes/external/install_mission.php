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
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\results;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\view;

/**
 * Installs a mission in the course, or changes its skin or local examples keeping what the students did.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class install_mission extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Mission'),
            'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin'),
            'variant' => new external_value(PARAM_ALPHANUMEXT, 'Local examples'),
            'activities' => new external_value(
                PARAM_BOOL,
                'Also make its Moodle activities, a section per chapter',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Installs a mission in the course, or changes its skin or local examples keeping what the students did.
     *
     * @param int $courseid
     * @param string $code
     * @param string $skin
     * @param string $variant
     * @param bool $activities
     * @return array
     */
    public static function execute(
        int $courseid,
        string $code,
        string $skin,
        string $variant,
        bool $activities = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'code' => $code,
            'skin' => $skin,
            'variant' => $variant,
            'activities' => $activities,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        if ($params['activities']) {
            require_capability('moodle/course:manageactivities', $context);
        }
        missions::install(
            $params['courseid'],
            $params['code'],
            $params['skin'],
            $params['variant'],
            null,
            $params['activities']
        );
        return get_missions::missions($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return get_missions::execute_returns();
    }
}
