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
use local_oksigeniatesape\local\campaigns;
use local_oksigeniatesape\local\games;

/**
 * The campaign of a course with other courses, for its teachers: makes one, joins one by its code, or leaves it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaign extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'create, join or leave'),
            'name' => new external_value(PARAM_TEXT, 'To create: its name', VALUE_DEFAULT, ''),
            'code' => new external_value(PARAM_ALPHANUM, 'To join: its code', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Makes a campaign, joins one or leaves it.
     *
     * @param int $courseid
     * @param string $action
     * @param string $name
     * @param string $code
     * @return array
     */
    public static function execute(int $courseid, string $action, string $name = '', string $code = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'name' => $name,
            'code' => $code,
        ]);
        $context = games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        if ($params['action'] === 'create') {
            campaigns::create($params['courseid'], $params['name']);
        } else if ($params['action'] === 'join') {
            campaigns::join($params['courseid'], $params['code']);
        } else if ($params['action'] === 'leave') {
            campaigns::leave($params['courseid']);
        } else {
            throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
        $view = campaigns::view($params['courseid'], true);
        return $view ? ['campaign' => $view] : [];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'campaign' => structures::campaign(),
        ]);
    }
}
