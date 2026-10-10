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
 * The log of a course, newest first.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_log extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Only this student (0: everybody)', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'How many', VALUE_DEFAULT, 50),
            'beforeid' => new external_value(PARAM_INT, 'Older than this entry (0: from the newest)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The log of a course, newest first.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $limit
     * @param int $beforeid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $userid = 0,
        int $limit = 50,
        int $beforeid = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'limit' => $limit,
            'beforeid' => $beforeid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:viewlog', $context);
        return view::log($params['courseid'], $params['userid'], max(1, min(200, $params['limit'])), $params['beforeid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_multiple_structure(structures::entry([
            'userid' => new external_value(PARAM_INT, 'Student'),
            'fullname' => new external_value(PARAM_TEXT, 'Student name'),
            'actor' => new external_value(PARAM_TEXT, 'Who did it (empty: the game)'),
        ]));
    }
}
