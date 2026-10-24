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
 * Undoes an entry of the log of a course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class undo extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'entryid' => new external_value(PARAM_INT, 'Entry'),
        ]);
    }

    /**
     * Undoes an entry of the log of a course.
     *
     * @param int $courseid
     * @param int $entryid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $entryid
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'entryid' => $entryid,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:undo', $context);
        $entry = $DB->get_record('local_oksigeniatesape_ledger', ['id' => $params['entryid']], '*', MUST_EXIST);
        if ($entry->courseid != $params['courseid']) {
            throw new \invalid_parameter_exception('The entry belongs to another course');
        }
        $undo = engine::undo((int) $entry->id, (int) $USER->id);
        return ['id' => (int) $undo->id, 'amount' => (int) $undo->amount];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new entry'),
            'amount' => new external_value(PARAM_INT, 'What it took back'),
        ]);
    }
}
