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
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * A teacher approves or turns down a request to use a power.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class decide_power extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'requestid' => new external_value(PARAM_INT, 'Request (0: those of requestids)', VALUE_DEFAULT, 0),
            'approve' => new external_value(PARAM_BOOL, 'Yes or no'),
            'requestids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Request'),
                'Several requests decided at once: those already decided are left as they are',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * A teacher approves or turns down a request to use a power.
     *
     * @param int $courseid
     * @param int $requestid
     * @param bool $approve
     * @param int[] $requestids
     * @return array
     */
    public static function execute(
        int $courseid,
        int $requestid,
        bool $approve,
        array $requestids = []
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'requestid' => $requestid,
            'approve' => $approve,
            'requestids' => $requestids,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:approvepowers', $context);
        $ids = array_values(array_unique(array_map('intval', $params['requestids'] ?: [$params['requestid']])));
        if (!$ids || count($ids) > 100) {
            throw new \invalid_parameter_exception('Between 1 and 100 requests');
        }
        foreach ($ids as $id) {
            if (powers::course_of($id) !== $params['courseid']) {
                throw new \invalid_parameter_exception('The request belongs to another course');
            }
        }
        if (count($ids) === 1) {
            powers::decide($ids[0], $params['approve'], (int) $USER->id);
        } else {
            // All of them or none; one that another teacher decided meanwhile stays as it is.
            $transaction = $DB->start_delegated_transaction();
            foreach ($ids as $id) {
                if ($DB->get_field('local_oksigeniatesape_powerreq', 'status', ['id' => $id]) === 'pending') {
                    powers::decide($id, $params['approve'], (int) $USER->id);
                }
            }
            $transaction->allow_commit();
        }
        return ['powers' => powers::for_teacher($params['courseid'])] + powers::requests($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::powers();
    }
}
