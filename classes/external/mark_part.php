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
use local_oksigeniatesape\local\classroom;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;

/**
 * A member of a team marks a part of a team task as done («My part is done»), or takes it back.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mark_part extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'challengeid' => new external_value(PARAM_INT, 'The team task'),
            'part' => new external_value(PARAM_INT, 'Which part, from 0'),
            'done' => new external_value(PARAM_BOOL, 'Done, or taken back'),
        ]);
    }

    /**
     * The current student marks it.
     *
     * @param int $courseid
     * @param int $challengeid
     * @param int $part
     * @param bool $done
     * @return array
     */
    public static function execute(int $courseid, int $challengeid, int $part, bool $done): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid,
            'challengeid' => $challengeid, 'part' => $part, 'done' => $done]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $challenge = classroom::get($params['challengeid']);
        if ((int) $challenge->courseid !== $params['courseid'] || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('partnotyours', 'local_oksigeniatesape');
        }
        classroom::mark_part($params['challengeid'], (int) $USER->id, $params['part'], $params['done']);
        $team = teams::of($params['courseid'], (int) $USER->id);
        return ['parts' => classroom::team_parts($challenge, (int) $team->id, (int) $USER->id)];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'parts' => new external_multiple_structure(new external_single_structure([
                'role' => new external_value(PARAM_ALPHA, 'The role it is meant for (empty: anyone)'),
                'text' => new external_value(PARAM_TEXT, 'What it is'),
                'done' => new external_value(PARAM_BOOL, 'Done in their team'),
                'by' => new external_value(PARAM_TEXT, 'Who marked it'),
                'mine' => new external_value(PARAM_BOOL, 'They marked it'),
            ])),
        ]);
    }
}
