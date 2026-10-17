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
use local_oksigeniatesape\local\pacts;

/**
 * The teacher marks a commitment of a student: kept, not kept, or back to pending; or several at once (all the team kept
 * them, and then the teacher unmarks who did not).
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
            'pactid' => new external_value(PARAM_INT, 'Commitment (0: those of pactids)', VALUE_DEFAULT, 0),
            'status' => new external_value(PARAM_ALPHA, 'kept, broken or open'),
            'pactids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Commitment'),
                'Several commitments marked at once',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * The teacher marks a commitment of a student.
     *
     * @param int $courseid
     * @param int $pactid
     * @param string $status
     * @param int[] $pactids
     * @return array The commitments to mark.
     */
    public static function execute(int $courseid, int $pactid, string $status, array $pactids = []): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'pactid' => $pactid,
            'status' => $status,
            'pactids' => $pactids,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        $ids = array_values(array_unique(array_map('intval', $params['pactids'] ?: [$params['pactid']])));
        if (!$ids || count($ids) > 100) {
            throw new \invalid_parameter_exception('Between 1 and 100 commitments');
        }
        foreach ($ids as $id) {
            $pact = $DB->get_record('local_oksigeniatesape_pact', ['id' => $id], '*', MUST_EXIST);
            if ((int) $pact->courseid !== $params['courseid']) {
                throw new \invalid_parameter_exception('The commitment belongs to another course');
            }
        }
        $transaction = $DB->start_delegated_transaction();
        foreach ($ids as $id) {
            pacts::decide($id, $params['status'], (int) $USER->id);
        }
        $transaction->allow_commit();
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
