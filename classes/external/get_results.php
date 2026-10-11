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
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\results;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\view;

/**
 * What the class did in the scenes of the mission: plays, objectives and the most common mix-ups.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_results extends external_api {
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
     * What the class did in the scenes of the mission: plays, objectives and the most common mix-ups.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:viewlog', $context);
        return results::mission($params['courseid']) ?? ['title' => '', 'players' => 0, 'chapters' => [], 'games' => []];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'title' => new external_value(PARAM_TEXT, 'Mission (empty: none installed)'),
            'players' => new external_value(PARAM_INT, 'Students who play in the course'),
            'chapters' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'lit' => new external_value(PARAM_BOOL, 'Lantern of the class lit'),
                'done' => new external_value(PARAM_INT, 'Students who completed it'),
                'scenes' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Scene'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'players' => new external_value(PARAM_INT, 'Students who played it'),
                    'done' => new external_value(PARAM_INT, 'Students who finished it'),
                    'attempts' => new external_value(PARAM_INT, 'Plays'),
                    'objectives' => new external_multiple_structure(new external_single_structure([
                        'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                        'name' => new external_value(PARAM_TEXT, 'Objective'),
                        'ok' => new external_value(PARAM_INT, 'Right answers'),
                        'total' => new external_value(PARAM_INT, 'Answers'),
                    ])),
                    'confusions' => new external_multiple_structure(new external_single_structure([
                        'question' => new external_value(PARAM_TEXT, 'The question or the task'),
                        'text' => new external_value(PARAM_TEXT, 'The mix-up'),
                        'count' => new external_value(PARAM_INT, 'How many times'),
                    ])),
                ])),
            ])),
            'games' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'chapter' => new external_value(PARAM_ALPHANUMEXT, 'Chapter where it is played'),
                'players' => new external_value(PARAM_INT, 'Students who played it'),
                'won' => new external_value(PARAM_INT, 'Students who won it'),
                'tries' => new external_value(PARAM_INT, 'Plays'),
                'confusions' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'The mix-up'),
                    'count' => new external_value(PARAM_INT, 'How many times'),
                ])),
            ])),
        ]);
    }
}
