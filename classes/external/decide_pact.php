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
use local_oksigeniatesape\local\pacts;

/**
 * The teacher marks a commitment of a student: kept, not kept, or back to pending.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decide_pact extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'pactid' => new external_value(PARAM_INT, 'Commitment'),
            'status' => new external_value(PARAM_ALPHA, 'kept, broken or open'),
        ]);
    }

    /**
     * The teacher marks a commitment of a student.
     *
     * @param int $courseid
     * @param int $pactid
     * @param string $status
     * @return array The commitments to mark.
     */
    public static function execute(int $courseid, int $pactid, string $status): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'pactid' => $pactid,
            'status' => $status,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        $pact = $DB->get_record('local_oksigeniatesape_pact', ['id' => $params['pactid']], '*', MUST_EXIST);
        if ((int) $pact->courseid !== $params['courseid']) {
            throw new \invalid_parameter_exception('The commitment belongs to another course');
        }
        pacts::decide($params['pactid'], $params['status'], (int) $USER->id);
        $result = ['pactspending' => pacts::pending($params['courseid'])];
        $list = pacts::for_teacher($params['courseid']);
        if ($list) {
            $result['pacts'] = $list;
        }
        return $result;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'pacts' => structures::teacher_pacts(),
            'pactspending' => new external_value(PARAM_INT, 'Commitments still to mark'),
        ]);
    }
}
