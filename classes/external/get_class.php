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
 * The class of a course for its teachers: settings, students and their characters, teams and challenges.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_class extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'board' => new external_value(PARAM_BOOL, 'For the class screen: only what it shows', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * The class of a course for its teachers: settings, students and their characters, teams and challenges.
     *
     * @param int $courseid
     * @param bool $board
     * @return array
     */
    public static function execute(int $courseid, bool $board = false): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'board' => $board,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        return view::teacher($params['courseid'], $params['board']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'region' => structures::region(),
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'User'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'firstname' => new external_value(PARAM_TEXT, 'First name'),
                'picture' => new external_value(PARAM_URL, 'Picture (empty: none)'),
                'teamid' => new external_value(PARAM_INT, 'Group of the team (0: none)'),
                'teamplays' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Their play'),
                    'scene' => new external_value(PARAM_TEXT, 'Title of the scene'),
                    'by' => new external_value(PARAM_TEXT, 'Who played it, on their device'),
                    'time' => new external_value(PARAM_INT, 'When'),
                    'decisions' => new external_value(PARAM_INT, 'Decisions taken in turn (0: not said)', VALUE_DEFAULT, 0),
                    'decided' => new external_value(PARAM_INT, 'How many of them they took', VALUE_DEFAULT, 0),
                ]), 'Team plays a classmate made for them, newest first', VALUE_DEFAULT, []),
                'character' => structures::character(VALUE_OPTIONAL),
            ])),
            'teams' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'state' => new external_value(
                    PARAM_ALPHA,
                    'How the team says it is doing today: ready, working, help or empty',
                    VALUE_DEFAULT,
                    ''
                ),
                'banner' => new external_multiple_structure(
                    new external_value(PARAM_ALPHANUMEXT, 'Ornament of the banner'),
                    'What the team has of the market',
                    VALUE_DEFAULT,
                    []
                ),
                'bannerstage' => structures::bannerstage(),
            ])),
            'groupings' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Grouping'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
            ])),
            'challenges' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Challenge'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_RAW, 'What to do'),
                'saber' => new external_value(PARAM_INT, 'Knowledge'),
                'gems' => new external_value(PARAM_INT, 'Gems'),
                'steps' => new external_value(PARAM_SEQUENCE, 'Steps it counts for'),
                'teamwork' => new external_value(PARAM_BOOL, 'Done by the team'),
                'validations' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Student'),
                    'status' => new external_value(PARAM_ALPHA, 'done, merit or notyet'),
                    'note' => new external_value(PARAM_TEXT, 'Note'),
                ])),
                'parts' => new external_multiple_structure(new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'The role it is meant for (empty: anyone)'),
                    'text' => new external_value(PARAM_TEXT, 'What it is'),
                ]), 'A team task: its parts', VALUE_DEFAULT, []),
                'teamparts' => new external_multiple_structure(new external_single_structure([
                    'groupid' => new external_value(PARAM_INT, 'Team'),
                    'done' => new external_multiple_structure(new external_value(PARAM_INT, 'Part, from 0')),
                ]), 'The parts each team marked', VALUE_DEFAULT, []),
            ])),
            'energymax' => new external_value(PARAM_INT, 'Most energy'),
            'breathmax' => new external_value(PARAM_INT, 'Most breath'),
            'museum' => structures::museum(),
            'standings' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Team'),
                'saber' => new external_value(PARAM_INT, 'Knowledge this week'),
                'part' => new external_value(PARAM_INT, 'Part of each member'),
                'done' => new external_value(PARAM_INT, 'Members who have reached their part'),
                'needed' => new external_value(PARAM_INT, 'Members who have to reach it'),
                'goal' => new external_value(PARAM_INT, 'Knowledge of the parts needed'),
                'reached' => new external_value(PARAM_BOOL, 'Goal reached'),
                'players' => new external_value(PARAM_INT, 'Members who play'),
            ])),
            'activities' => new external_multiple_structure(new external_single_structure([
                'cmid' => new external_value(PARAM_INT, 'Activity'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'modname' => new external_value(PARAM_PLUGIN, 'Module'),
                'icon' => new external_value(PARAM_URL, 'Icon'),
                'url' => new external_value(PARAM_URL, 'Its settings'),
                'saber' => new external_value(PARAM_INT, 'Knowledge'),
                'gems' => new external_value(PARAM_INT, 'Gems'),
                'steps' => new external_value(PARAM_SEQUENCE, 'Steps it counts for'),
                'teamwork' => new external_value(PARAM_BOOL, 'Done by the team'),
                'tracked' => new external_value(PARAM_BOOL, 'Completion is tracked'),
                'done' => new external_value(PARAM_INT, 'Students who completed it'),
            ])),
            'powerspending' => new external_value(PARAM_INT, 'Requests of powers waiting'),
            'ideasunread' => new external_value(PARAM_INT, 'Ideas not read yet'),
            'pacts' => structures::teacher_pacts(),
            'pactspending' => new external_value(PARAM_INT, 'Commitments still to mark'),
            'namespending' => structures::names_pending(),
            'photos' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The photo'),
                'team' => new external_value(PARAM_TEXT, 'The team that sent it'),
                'chapter' => new external_value(PARAM_ALPHANUMEXT, 'Code of the place'),
                'place' => new external_value(PARAM_TEXT, 'Name of the place'),
                'status' => new external_value(PARAM_ALPHA, 'pending or approved'),
                'url' => new external_value(PARAM_URL, 'The picture'),
            ]), 'The photos of the classroom: waiting for the teacher, and on the map', VALUE_DEFAULT, []),
            'signals' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Student'),
                'kind' => new external_value(PARAM_ALPHA, 'stuck (today), hard or easy (their step)'),
                'text' => new external_value(PARAM_TEXT, 'What to look at'),
            ]), 'Who the teacher may want to look at, only in their panel', VALUE_DEFAULT, []),
            'powersqueue' => new external_multiple_structure(
                structures::power_request(),
                'Requests of powers waiting, for who approves them',
                VALUE_OPTIONAL
            ),
            'today' => structures::today(),
        ]);
    }
}
