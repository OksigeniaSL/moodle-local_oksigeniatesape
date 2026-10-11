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
use local_oksigeniatesape\local\names;
use local_oksigeniatesape\local\view;

/**
 * A teacher takes or turns down the own name a student proposed for their hero or their creature.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decide_name extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Student'),
            'what' => new external_value(PARAM_ALPHA, 'hero or creature'),
            'approve' => new external_value(PARAM_BOOL, 'Take it (or turn it down)'),
        ]);
    }

    /**
     * A teacher takes or turns down an own name.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $what
     * @param bool $approve
     * @return array The names still waiting.
     */
    public static function execute(int $courseid, int $userid, string $what, bool $approve): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'what' => $what,
            'approve' => $approve,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        view::require_students($params['courseid'], [$params['userid']]);
        names::decide($params['courseid'], $params['userid'], $params['what'], $params['approve']);
        return ['names' => names::pending($params['courseid'])];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure(['names' => structures::names_pending()]);
    }
}
