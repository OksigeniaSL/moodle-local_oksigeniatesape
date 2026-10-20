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
use local_oksigeniatesape\local\nextclass;

/**
 * The plan for the next class, made by the AI of the site from what confuses the class.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class next_class extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'state, accept (the AI policy of the site) or generate'),
        ]);
    }

    /**
     * Whether there is AI and the last plan; accepts the AI policy or asks for a new plan.
     *
     * @param int $courseid
     * @param string $action
     * @return array
     */
    public static function execute(int $courseid, string $action): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:viewlog', $context);
        if ($params['action'] === 'accept') {
            nextclass::accept_policy($params['courseid'], (int) $USER->id);
        } else if ($params['action'] === 'generate') {
            nextclass::generate($params['courseid'], (int) $USER->id);
        } else if ($params['action'] !== 'state') {
            throw new \invalid_parameter_exception('Unknown action');
        }
        $status = nextclass::status($params['courseid'], (int) $USER->id);
        $result = $status + ['policytext' => $status['canuse'] && !$status['policy'] ? get_string('userpolicy', 'core_ai') : ''];
        // The last plan of the class is shown even to whoever cannot make another.
        $plan = nextclass::last($params['courseid']);
        if ($plan) {
            $result['plan'] = $plan;
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
            'ai' => new external_value(PARAM_BOOL, 'The site has AI for this course'),
            'policy' => new external_value(PARAM_BOOL, 'The teacher accepted the AI policy of the site'),
            'canuse' => new external_value(PARAM_BOOL, 'The teacher can use the AI (or accept its policy)', VALUE_DEFAULT, false),
            'policytext' => new external_value(PARAM_RAW, 'The AI policy of the site, to accept it (empty if accepted)'),
            'plan' => new external_single_structure([
                'summary' => new external_value(PARAM_TEXT, 'What confuses the class most'),
                'review' => new external_multiple_structure(new external_single_structure([
                    'point' => new external_value(PARAM_TEXT, 'What to review'),
                    'how' => new external_value(PARAM_TEXT, 'How, in a few minutes'),
                ])),
                'example' => new external_value(PARAM_TEXT, 'A new example from everyday life'),
                'opener' => new external_value(PARAM_TEXT, 'A question to open the class'),
                'challenge' => new external_value(PARAM_INT, 'The classroom challenge that fits (0: none)'),
                'challengename' => new external_value(PARAM_TEXT, 'Its name'),
                'challengewhy' => new external_value(PARAM_TEXT, 'Why it fits'),
                'time' => new external_value(PARAM_INT, 'When it was made'),
            ], 'The last plan', VALUE_OPTIONAL),
        ]);
    }
}
