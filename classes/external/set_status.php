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
 * Pauses a student in the game, brings them back, or brings back a worn-out student after their recovery.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Student'),
            'action' => new external_value(PARAM_ALPHA, 'pause, resume or recover'),
            'reason' => new external_value(
                PARAM_TEXT,
                'Why, when pausing; only the student and the teachers see it',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Pauses a student in the game, brings them back, or brings back a worn-out student after their recovery.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $action
     * @param string $reason
     * @return array
     */
    public static function execute(
        int $courseid,
        int $userid,
        string $action,
        string $reason = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'action' => $action,
            'reason' => $reason,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:pause', $context);
        view::require_students($params['courseid'], [$params['userid']]);
        $char = characters::get($params['userid']);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        switch ($params['action']) {
            case 'pause':
                $member = engine::pause($member, $params['reason'], (int) $USER->id);
                break;
            case 'resume':
                $member = engine::resume($member);
                break;
            case 'recover':
                engine::recover($member, 'recovery', (int) $USER->id);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        $member = engine::member($char, $params['courseid']);
        return ['status' => $member->status, 'energy' => (int) $member->energy];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'active, fallen or paused'),
            'energy' => new external_value(PARAM_INT, 'Energy'),
        ]);
    }
}
