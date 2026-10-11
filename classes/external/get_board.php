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
use local_oksigeniatesape\local\view;

/**
 * The class screen: the map of the mission with the lanterns of the class, and the event of the day.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_board extends external_api {
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
     * The class screen of a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        return view::board($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'cards' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Card of the deck')),
            'mission' => new external_single_structure([
                'items' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'Item somebody carries')),
                'roles' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Role somebody plays')),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Mission'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'skin' => new external_value(PARAM_ALPHANUMEXT, 'Story'),
                'chapters' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                    'x' => new external_value(PARAM_INT, 'Place on the map'),
                    'y' => new external_value(PARAM_INT, 'Place on the map'),
                    'lit' => new external_value(PARAM_BOOL, 'Lantern of the class lit'),
                    'scenes' => new external_multiple_structure(new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Scene'),
                        'title' => new external_value(PARAM_TEXT, 'Title'),
                    ])),
                ])),
                'final' => new external_value(PARAM_BOOL, 'The mission saves something for a final battle', VALUE_DEFAULT, false),
                'ending' => new external_value(PARAM_TEXT, 'Text of the end of the mission', VALUE_DEFAULT, ''),
            ], 'Mission of the course', VALUE_OPTIONAL),
            'event' => structures::event(),
            'opener' => new external_value(PARAM_TEXT, 'A question to open the class, from the plan of the AI', VALUE_OPTIONAL),
        ]);
    }
}
