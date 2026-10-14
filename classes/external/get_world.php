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
            'quiet' => new external_value(PARAM_BOOL, 'No sound on the devices of the students', VALUE_DEFAULT, false),
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
                'week' => structures::week(),
                'energy' => new external_value(PARAM_INT, 'Energy of the whole team'),
                'energymax' => new external_value(PARAM_INT, 'Most energy of the whole team'),
                'carer' => new external_value(PARAM_BOOL, 'The student sees who is low on energy (healer, protector)'),
                'banner' => new external_multiple_structure(
                    new external_value(PARAM_ALPHANUMEXT, 'Ornament of the banner'),
                    'What the team has of the market',
                    VALUE_DEFAULT,
                    []
                ),
                'pacts' => structures::team_pacts(),
            ], 'Team', VALUE_OPTIONAL),
            'market' => structures::market(),
            'expeditions' => structures::expeditions(),
            'album' => structures::album(),
            'names' => new external_single_structure([
                'heroes' => new external_value(PARAM_BOOL, 'Hero names allowed in the course'),
                'own' => new external_value(PARAM_BOOL, 'Own names allowed, read by a teacher first'),
                'hero' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Hero name offered')),
                'creature' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Creature name offered')),
                'heropending' => new external_value(PARAM_TEXT, 'Own hero name waiting for a teacher'),
                'creaturepending' => new external_value(PARAM_TEXT, 'Own creature name waiting for a teacher'),
            ], 'Names the student can give', VALUE_OPTIONAL),
            'badge' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name of the badge'),
                'issued' => new external_value(PARAM_BOOL, 'The student has it'),
                'image' => new external_value(PARAM_URL, 'Image of the badge'),
                'url' => new external_value(PARAM_URL, 'Where the student sees their badges'),
            ], 'The badge of the mission, when the teacher turned it on', VALUE_OPTIONAL),
            'page' => new external_single_structure([
                'question' => new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'Question'),
                    'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option')),
                ]),
                'answered' => new external_value(PARAM_BOOL, 'Answered today'),
                'right' => new external_value(PARAM_BOOL, 'Answered right'),
                'mine' => new external_value(PARAM_INT, 'Option chosen (-1: none yet)'),
                'correct' => new external_value(PARAM_INT, 'Right option, once answered', VALUE_OPTIONAL),
                'why' => new external_value(PARAM_TEXT, 'Why, once answered', VALUE_OPTIONAL),
                'breath' => new external_value(PARAM_INT, 'Breath it gives answered right'),
                'streak' => new external_value(PARAM_INT, 'Days in a row answered right'),
                'glow' => new external_value(PARAM_INT, 'Days in a row for the creature to glow'),
            ], 'The page of the day the creature asks for', VALUE_OPTIONAL),
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
            'items' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_TEXT, 'Description'),
            ])),
            'mission' => structures::mission(),
            'log' => new external_multiple_structure(structures::entry()),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Activity'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module'),
                'icon' => new external_value(PARAM_URL, 'Icon'),
                'url' => new external_value(PARAM_URL, 'Where it opens'),
                'chapterid' => new external_value(PARAM_INT, 'Chapter of the mission (0: none)'),
                'challenge' => new external_value(PARAM_BOOL, 'Marked as a challenge'),
                'saber' => new external_value(PARAM_INT, 'Knowledge when completed'),
                'gems' => new external_value(PARAM_INT, 'Gems when completed'),
                'teamwork' => new external_value(PARAM_BOOL, 'Done by the team'),
                'tracked' => new external_value(PARAM_BOOL, 'Completion is tracked'),
                'done' => new external_value(PARAM_BOOL, 'Completed'),
            ])),
            'powers' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Power'),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'role' => new external_value(PARAM_ALPHA, 'Role, or all'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_TEXT, 'What it does'),
                'cost' => new external_value(PARAM_INT, 'Breath'),
                'minlevel' => new external_value(PARAM_INT, 'Level needed'),
                'target' => new external_value(PARAM_ALPHA, 'mate, fallen, team or none'),
                'locked' => new external_value(PARAM_BOOL, 'Level too low'),
                'pending' => new external_value(PARAM_BOOL, 'Already asked, waiting for the teacher'),
            ])),
            'powerrequests' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Request'),
                'name' => new external_value(PARAM_TEXT, 'Power'),
                'status' => new external_value(PARAM_ALPHA, 'pending, approved, rejected or used'),
                'target' => new external_value(PARAM_TEXT, 'Teammate it was for'),
                'time' => new external_value(PARAM_INT, 'When'),
            ])),
            'standings' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Team'),
                'saber' => new external_value(PARAM_INT, 'Knowledge this week'),
                'goal' => new external_value(PARAM_INT, 'Goal of the week'),
                'reached' => new external_value(PARAM_BOOL, 'Goal reached'),
            ]), 'Team ranking, if the teacher shows it'),
            'ideas' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Idea'),
                'message' => new external_value(PARAM_TEXT, 'The idea'),
                'anonymous' => new external_value(PARAM_BOOL, 'Sent without the name'),
                'status' => new external_value(PARAM_ALPHA, 'new or read'),
                'time' => new external_value(PARAM_INT, 'When'),
            ])),
            'hascoat' => new external_value(PARAM_BOOL, 'The creature can be dressed'),
        ]);
    }
}
