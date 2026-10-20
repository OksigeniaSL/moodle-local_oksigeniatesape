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
 * The teacher's word on a classroom challenge for some students: done, done with a mention, or not yet.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class validate extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'challengeid' => new external_value(PARAM_INT, 'Classroom challenge'),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'Student')),
            'status' => new external_value(PARAM_ALPHA, 'done, merit or notyet'),
            'note' => new external_value(
                PARAM_TEXT,
                'What the teacher tells them (null keeps it)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
        ]);
    }

    /**
     * The teacher's word on a classroom challenge for some students: done, done with a mention, or not yet.
     *
     * @param int $courseid
     * @param int $challengeid
     * @param int[] $userids
     * @param string $status
     * @param string|null $note
     * @return array
     */
    public static function execute(
        int $courseid,
        int $challengeid,
        array $userids,
        string $status,
        ?string $note = null
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'challengeid' => $challengeid,
            'userids' => $userids,
            'status' => $status,
            'note' => $note,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if (classroom::get($params['challengeid'])->courseid != $params['courseid']) {
            throw new \invalid_parameter_exception('The challenge belongs to another course');
        }
        view::require_students($params['courseid'], $params['userids']);
        $results = [];
        $done = classroom::validate(
            $params['challengeid'],
            $params['userids'],
            $params['status'],
            $params['note'],
            (int) $USER->id
        );
        foreach ($done as $userid => $status) {
            $results[] = ['userid' => $userid, 'status' => $status];
        }
        return $results;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::results();
    }
}
