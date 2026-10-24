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
 * Be the Embrollo, for the current student: writes the tangle of their team, or undoes the tangle of another team.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class play_tangle extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'write (claim, truth, trap, why) or undo (tangleid, answer)'),
            'claim' => new external_value(PARAM_TEXT, 'What the Embrollo says', VALUE_DEFAULT, ''),
            'truth' => new external_value(PARAM_TEXT, 'What is true', VALUE_DEFAULT, ''),
            'trap' => new external_value(PARAM_TEXT, 'Another wrong idea', VALUE_DEFAULT, ''),
            'why' => new external_value(PARAM_TEXT, 'Why', VALUE_DEFAULT, ''),
            'tangleid' => new external_value(PARAM_INT, 'The tangle undone', VALUE_DEFAULT, 0),
            'answer' => new external_value(PARAM_INT, 'Position of the answer chosen', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Writes or undoes.
     *
     * @param int $courseid
     * @param string $action
     * @param string $claim
     * @param string $truth
     * @param string $trap
     * @param string $why
     * @param int $tangleid
     * @param int $answer
     * @return array tangles, and what undoing said.
     */
    public static function execute(
        int $courseid,
        string $action,
        string $claim = '',
        string $truth = '',
        string $trap = '',
        string $why = '',
        int $tangleid = 0,
        int $answer = -1
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'claim' => $claim,
            'truth' => $truth,
            'trap' => $trap,
            'why' => $why,
            'tangleid' => $tangleid,
            'answer' => $answer,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $out = [];
        if ($params['action'] === 'write') {
            tangles::write($params['courseid'], (int) $USER->id, $params);
        } else if ($params['action'] === 'undo') {
            $out['result'] = tangles::undo($params['courseid'], (int) $USER->id, $params['tangleid'], $params['answer']);
        } else {
            throw new \invalid_parameter_exception('Not an action: ' . $params['action']);
        }
        return $out + ['tangles' => \local_oksigeniatesape\local\view::tangles($params['courseid'], (int) $USER->id)];
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'tangles' => structures::tangles_student(),
            'result' => new external_single_structure([
                'right' => new external_value(PARAM_BOOL, 'Undone at the first try'),
                'truth' => new external_value(PARAM_TEXT, 'What is true'),
                'why' => new external_value(PARAM_TEXT, 'Why'),
                'gems' => new external_value(PARAM_INT, 'Gems given'),
            ], 'What undoing said', VALUE_OPTIONAL),
        ]);
    }
}
