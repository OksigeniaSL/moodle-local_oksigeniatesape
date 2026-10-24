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
use local_oksigeniatesape\local\tangles;

/**
 * Be the Embrollo, for a teacher: approves the tangle of a team, gives it back, or takes it to the battle.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_tangle extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'tangleid' => new external_value(PARAM_INT, 'Tangle'),
            'action' => new external_value(PARAM_ALPHA, 'approve, reject or battle'),
        ]);
    }

    /**
     * Approves, gives back or takes to the battle.
     *
     * @param int $courseid
     * @param int $tangleid
     * @param string $action
     * @return array The tangles of the course.
     */
    public static function execute(int $courseid, int $tangleid, string $action): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'tangleid' => $tangleid,
            'action' => $action,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        if ($params['action'] === 'battle') {
            // The questions of the battle are the mission of the course, as the workshop changes it.
            require_capability('local/oksigeniatesape:manageregion', $context);
            tangles::to_battle($params['courseid'], $params['tangleid']);
        } else if (in_array($params['action'], ['approve', 'reject'], true)) {
            require_capability('local/oksigeniatesape:award', $context);
            tangles::review($params['courseid'], $params['tangleid'], $params['action'] === 'approve', (int) $USER->id);
        } else {
            throw new \invalid_parameter_exception('Not an action: ' . $params['action']);
        }
        return ['tangles' => tangles::for_teacher($params['courseid'])];
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(['tangles' => structures::tangles_teacher()]);
    }
}
