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
 * The world of the current student in a course: their character, team, classroom challenges and log.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_world extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
        ]);
    }

    /**
     * The world of the current student in a course: their character, team, classroom challenges and log.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        return view::world($params['courseid'], (int) $USER->id);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'The game is on in the course'),
            'family' => new external_value(PARAM_ALPHA, 'Family of role names'),
            'nicknames' => new external_value(PARAM_INT, 'Hero names allowed'),
            'energymax' => new external_value(PARAM_INT, 'Most energy'),
            'breathmax' => new external_value(PARAM_INT, 'Most breath'),
            'character' => structures::character(VALUE_OPTIONAL),
            'team' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'members' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User'),
                    'name' => new external_value(PARAM_TEXT, 'Name shown to classmates'),
                    'me' => new external_value(PARAM_BOOL, 'The current student'),
                    'character' => structures::character(VALUE_OPTIONAL),
                ])),
            ], 'Team', VALUE_OPTIONAL),
            'challenges' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Challenge'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_RAW, 'What to do (HTML)'),
                'saber' => new external_value(PARAM_INT, 'Knowledge'),
                'gems' => new external_value(PARAM_INT, 'Gems'),
                'teamwork' => new external_value(PARAM_BOOL, 'Done by the team'),
                'status' => new external_value(PARAM_ALPHA, 'done, merit, notyet or empty'),
                'note' => new external_value(PARAM_TEXT, 'What the teacher said'),
            ])),
            'log' => new external_multiple_structure(structures::entry()),
        ]);
    }
}
