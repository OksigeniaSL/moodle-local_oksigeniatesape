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
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
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
                'map' => new external_value(PARAM_ALPHANUMEXT, 'Code of the painted map', VALUE_DEFAULT, ''),
                'mapworld' => new external_value(PARAM_ALPHANUMEXT, 'World of a map in layers', VALUE_DEFAULT, ''),
                'mapheight' => new external_value(PARAM_INT, 'Height of a map in layers', VALUE_DEFAULT, 0),
                'seasons' => new external_single_structure([
                    'spring' => new external_value(PARAM_TEXT, 'What happens in spring'),
                    'summer' => new external_value(PARAM_TEXT, 'What happens in summer'),
                    'autumn' => new external_value(PARAM_TEXT, 'What happens in autumn'),
                    'winter' => new external_value(PARAM_TEXT, 'What happens in winter'),
                ], 'What happens in the world of the mission in each season', VALUE_OPTIONAL),
                'cinematics' => new external_multiple_structure(new external_single_structure([
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Cinematic'),
                    'title' => new external_value(PARAM_TEXT, 'Its title'),
                    'steps' => new external_multiple_structure(new external_single_structure([
                        'image' => new external_value(PARAM_ALPHANUMEXT, 'The painting: of a scene, or boss for the Embrollo'),
                        'move' => new external_value(PARAM_ALPHA, 'How the camera moves: in, out, left or right'),
                        'text' => new external_value(PARAM_TEXT, 'What is told'),
                    ])),
                ]), 'The cinematics of the mission for the class screen', VALUE_DEFAULT, []),
                'chapters' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Chapter'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                    'x' => new external_value(PARAM_INT, 'Place on the map'),
                    'y' => new external_value(PARAM_INT, 'Place on the map'),
                    'landmark' => new external_value(PARAM_ALPHANUMEXT, 'Landmark on a map in layers', VALUE_DEFAULT, ''),
                    'lit' => new external_value(PARAM_BOOL, 'Lantern of the class lit'),
                    'scenes' => new external_multiple_structure(new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Scene'),
                        'title' => new external_value(PARAM_TEXT, 'Title'),
                    ])),
                    'stickers' => new external_multiple_structure(new external_single_structure([
                        'flag' => new external_value(PARAM_ALPHANUMEXT, 'The mark, for its drawing'),
                        'text' => new external_value(PARAM_TEXT, 'What the class decided, in a few words'),
                    ]), 'The decisions of at least half of the class in this place', VALUE_DEFAULT, []),
                ])),
                'final' => new external_value(PARAM_BOOL, 'The mission saves something for a final battle', VALUE_DEFAULT, false),
                'knots' => new external_multiple_structure(new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Who carries it'),
                    'claim' => new external_value(PARAM_TEXT, 'What the Embrollo claims'),
                    'weakness' => new external_value(PARAM_TEXT, 'What undoes it'),
                    'why' => new external_value(PARAM_TEXT, 'Why'),
                ]), 'The knots of the Embrollo, for their cards on paper', VALUE_DEFAULT, []),
                'expedition' => new external_single_structure([
                    'strength' => new external_value(PARAM_FLOAT, 'From 0 to 1'),
                    'weaker' => new external_value(PARAM_INT, 'Percentage of threads the Embrollo starts the final without'),
                    'lanterns' => new external_multiple_structure(new external_value(PARAM_INT, 'Lit, of how many')),
                    'stains' => new external_value(PARAM_INT, 'Stains cleaned and pages recovered'),
                    'tasks' => new external_multiple_structure(new external_value(PARAM_INT, 'Done, of how many')),
                    'untangled' => new external_multiple_structure(
                        new external_value(PARAM_INT, 'Loosened, of how many'),
                        'The strands loosened in the last great untangling',
                        VALUE_DEFAULT,
                        []
                    ),
                    'known' => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Name of a knot the class still mixes up'),
                        'Knots the Embrollo keeps for the end of the final battle',
                        VALUE_DEFAULT,
                        []
                    ),
                ], 'The strength of the expedition', VALUE_OPTIONAL),
                'ending' => new external_value(PARAM_TEXT, 'Text of the end of the mission', VALUE_DEFAULT, ''),
                'remembered' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'A line of what the class did'),
                    'What the Great Book remembers of the class at the end',
                    VALUE_DEFAULT,
                    []
                ),
                'lines' => new external_multiple_structure(new external_single_structure([
                    'kind' => new external_value(PARAM_ALPHANUM, 'start, hit, recover, phase2, won or lost'),
                    'lines' => new external_multiple_structure(new external_value(PARAM_TEXT, 'What it says')),
                ]), 'What the Embrollo says in a battle, by moment', VALUE_DEFAULT, []),
            ], 'Mission of the course', VALUE_OPTIONAL),
            'event' => structures::event(),
            'opener' => new external_value(PARAM_TEXT, 'A question to open the class, from the plan of the AI', VALUE_OPTIONAL),
            'today' => structures::today(),
            'expeditions' => structures::expeditions(),
            'fog' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'A place in the fog'),
                'The places the class has forgotten, in the fog of the Embrollo',
                VALUE_DEFAULT,
                []
            ),
            'fromfar' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Who, by the name the class sees'),
                'right' => new external_value(PARAM_INT, 'Answers right'),
            ]), 'Who played the last battle in deferred, from far', VALUE_DEFAULT, []),
            'tokens' => new external_multiple_structure(new external_single_structure([
                'teamid' => new external_value(PARAM_INT, 'Team'),
                'team' => new external_value(PARAM_TEXT, 'Its name'),
                'place' => new external_value(PARAM_ALPHANUMEXT, 'Where most of it played last'),
            ]), 'The token of each team on the board', VALUE_DEFAULT, []),
        ]);
    }
}
