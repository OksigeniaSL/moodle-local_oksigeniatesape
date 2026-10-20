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
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;

/**
 * A student says how their team is doing today: ready, working or asking for help.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_team_state extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'state' => new external_value(PARAM_ALPHA, 'ready, working or help (empty: as it is)'),
            'first' => new external_value(
                PARAM_ALPHA,
                'The council: the role that starts the hand today (empty: none)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
        ]);
    }

    /**
     * The current student says it.
     *
     * @param int $courseid
     * @param string $state
     * @param string|null $first
     * @return array
     */
    public static function execute(int $courseid, string $state, ?string $first = null): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['courseid' => $courseid, 'state' => $state, 'first' => $first]
        );
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $team = teams::of($params['courseid'], (int) $USER->id);
        if (!$team || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('noteam', 'local_oksigeniatesape');
        }
        if ($params['state'] !== '') {
            teams::set_state($params['courseid'], (int) $team->id, $params['state']);
        }
        if ($params['first'] !== null) {
            teams::set_first($params['courseid'], (int) $team->id, $params['first']);
        }
        return ['state' => teams::states($params['courseid'])[(int) $team->id] ?? '',
            'first' => teams::firsts($params['courseid'])[(int) $team->id] ?? ''];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHA, 'How the team is doing now'),
            'first' => new external_value(PARAM_ALPHA, 'The role that starts the hand today (empty: none chosen)'),
        ]);
    }
}
