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
            'devicemusic' => new external_value(
                PARAM_INT,
                'Music on the devices: 0 never, 1 only in battles, 2 always',
                VALUE_DEFAULT,
                1,
            ),
            'energymax' => new external_value(PARAM_INT, 'Most energy'),
            'breathmax' => new external_value(PARAM_INT, 'Most breath'),
            'character' => structures::character(VALUE_OPTIONAL),
            'team' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'state' => new external_value(
                    PARAM_ALPHA,
                    'How the team says it is doing today: ready, working, help or empty',
                    VALUE_DEFAULT,
                    ''
                ),
                'first' => new external_value(PARAM_ALPHA, 'The role that starts the hand today (empty: none)', VALUE_DEFAULT, ''),
                'council' => new external_single_structure([
                    'place' => new external_value(PARAM_TEXT, 'The place of today'),
                    'task' => new external_value(PARAM_TEXT, 'Its task for the team (empty: none)'),
                    'parts' => new external_multiple_structure(new external_single_structure([
                        'role' => new external_value(PARAM_ALPHA, 'Role'),
                        'text' => new external_value(PARAM_TEXT, 'What the part is'),
                        'done' => new external_value(PARAM_BOOL, 'Done'),
                        'who' => new external_value(PARAM_TEXT, 'Who of the team has that role (empty: nobody)'),
                    ])),
                ], 'The council of the team: the task of today and its parts', VALUE_OPTIONAL),
                'reserve' => structures::reserve(VALUE_OPTIONAL),
                'members' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'User'),
                    'name' => new external_value(PARAM_TEXT, 'Name shown to classmates'),
                    'me' => new external_value(PARAM_BOOL, 'The current student'),
                    'character' => structures::character(VALUE_OPTIONAL),
                    'at' => new external_value(PARAM_INT, 'Chapter where they last played, 0 if none', VALUE_DEFAULT, 0),
                    'gifts' => new external_multiple_structure(
                        new external_value(PARAM_ALPHA, 'guard or trail'),
                        'What they bring to a play as a team, by their talents',
                        VALUE_DEFAULT,
                        []
                    ),
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
                'bannerstage' => structures::bannerstage(),
                'brought' => new external_value(
                    PARAM_BOOL,
                    'The messengers of another team brought it a hint today: the first of each scene is free',
                    VALUE_DEFAULT,
                    false
                ),
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
            'welcome' => new external_single_structure([
                'kind' => new external_value(PARAM_ALPHA, 'back (after a long time away) or new (arriving late)'),
                'days' => new external_value(PARAM_INT, 'Days away'),
                'lanterns' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Place the class lit meanwhile')),
                'path' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Scene'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'place' => new external_value(PARAM_TEXT, 'Place'),
                ]), 'A short way to catch up'),
                'team' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Teammate')),
            ], 'A welcome back, or to who arrives late', VALUE_OPTIONAL),
            'battles' => new external_multiple_structure(new external_single_structure([
                'outcome' => new external_value(PARAM_ALPHA, 'won or lost'),
                'time' => new external_value(PARAM_INT, 'When it ended'),
                'final' => new external_value(PARAM_BOOL, 'The final battle of the mission'),
                'lantern' => new external_value(PARAM_TEXT, 'The lantern it lit (empty: none)'),
            ]), 'The battles of the class missed by who follows from far', VALUE_OPTIONAL),
            'badge' => new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name of the badge'),
                'issued' => new external_value(PARAM_BOOL, 'The student has it'),
                'image' => new external_value(PARAM_URL, 'Image of the badge'),
                'url' => new external_value(PARAM_URL, 'Where the student sees their badges'),
            ], 'The badge of the mission, when the teacher turned it on', VALUE_OPTIONAL),
            'stains' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Stain'),
                'chapter' => new external_value(PARAM_ALPHANUMEXT, 'The place stained'),
                'place' => new external_value(PARAM_TEXT, 'Its name'),
                'title' => new external_value(PARAM_TEXT, 'The title of the sheet'),
                'parts' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Plain text'),
                    'mark' => new external_value(PARAM_INT, 'A mistake (-1: plain text)'),
                    'wrong' => new external_value(PARAM_TEXT, 'What it says'),
                ])),
                'kind' => new external_value(PARAM_ALPHA, 'stain, or page: a page of the Great Book stolen', VALUE_DEFAULT, 'stain'),
                'claim' => new external_value(PARAM_TEXT, 'What the Embrollo claims, for a page', VALUE_DEFAULT, ''),
                'question' => new external_value(PARAM_TEXT, 'The question of today to bring a page back', VALUE_DEFAULT, ''),
                'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option'), 'Its options', VALUE_DEFAULT, []),
                'tried' => new external_value(PARAM_BOOL, 'The student tried today to bring it back', VALUE_DEFAULT, false),
                'forteam' => new external_value(PARAM_BOOL, 'A knot for the team of the student, from a card', VALUE_DEFAULT, false),
            ]), 'What the Embrollo stained or stole in the valley', VALUE_DEFAULT, []),
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
                'parts' => new external_multiple_structure(new external_single_structure([
                    'role' => new external_value(PARAM_ALPHA, 'The role it is meant for (empty: anyone)'),
                    'text' => new external_value(PARAM_TEXT, 'What it is'),
                    'done' => new external_value(PARAM_BOOL, 'Done in their team'),
                    'by' => new external_value(PARAM_TEXT, 'Who marked it'),
                    'mine' => new external_value(PARAM_BOOL, 'They marked it'),
                ]), 'A team task: its parts', VALUE_DEFAULT, []),
            ])),
            'items' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_TEXT, 'Description'),
                'lent' => new external_value(PARAM_TEXT, 'Trade: the team it is lent to today', VALUE_DEFAULT, ''),
                'borrowed' => new external_value(PARAM_TEXT, 'Trade: the team that lent it today', VALUE_DEFAULT, ''),
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
            'opening' => new external_single_structure([
                'beats' => new external_multiple_structure(new external_single_structure([
                    'image' => new external_value(PARAM_ALPHANUMEXT, 'Key of its picture'),
                    'speaker' => new external_value(PARAM_ALPHANUMEXT, 'Who speaks (empty: nobody)'),
                    'speakername' => new external_value(PARAM_TEXT, 'Their name'),
                    'text' => new external_value(PARAM_TEXT, 'What is told'),
                ])),
                'ask' => new external_value(PARAM_TEXT, 'The question that makes them choose their role'),
                'mirror' => new external_value(PARAM_TEXT, 'Where they choose their look'),
                'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin of the mission'),
                'place' => new external_value(PARAM_ALPHANUMEXT, 'The first place, for the pictures'),
            ], 'The prologue of the mission, for who has no character yet', VALUE_OPTIONAL),
            'hascoat' => new external_value(PARAM_BOOL, 'The creature can be dressed'),
        ]);
    }
}
