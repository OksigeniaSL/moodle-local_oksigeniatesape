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

/**
 * Web services of the game, for its client and the teacher panel.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_oksigeniatesape_get_world' => [
        'classname' => 'local_oksigeniatesape\external\get_world',
        'description' => 'The world of the current student in a course: their character, team, classroom challenges and log.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_create_character' => [
        'classname' => 'local_oksigeniatesape\external\create_character',
        'description' => 'Makes the character of the current student.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_update_look' => [
        'classname' => 'local_oksigeniatesape\external\update_look',
        'description' => 'Changes how the current student\'s character looks.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_get_class' => [
        'classname' => 'local_oksigeniatesape\external\get_class',
        'description' => 'The class of a course for its teachers: settings, students and their characters, teams and challenges.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_save_region' => [
        'classname' => 'local_oksigeniatesape\external\save_region',
        'description' => 'Turns the game on or off in a course and changes its settings; what is not given keeps its value.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_award' => [
        'classname' => 'local_oksigeniatesape\external\award',
        'description' => 'Gives knowledge, gems or breath to some students, or takes energy for a commitment not kept.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_validate' => [
        'classname' => 'local_oksigeniatesape\external\validate',
        'description' => 'The teacher\'s word on a classroom challenge for some students: done, done with a mention, or not yet.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_save_challenge' => [
        'classname' => 'local_oksigeniatesape\external\save_challenge',
        'description' => 'Makes or changes a classroom challenge.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_delete_challenge' => [
        'classname' => 'local_oksigeniatesape\external\delete_challenge',
        'description' => 'Deletes a classroom challenge; what it gave stays in the log.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_set_status' => [
        'classname' => 'local_oksigeniatesape\external\set_status',
        'description' => 'Pauses a student in the game, brings them back, or brings back a worn-out student after their recovery.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:pause',
    ],
    'local_oksigeniatesape_set_step' => [
        'classname' => 'local_oksigeniatesape\external\set_step',
        'description' => 'Moves a student to another step of the mission.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_get_log' => [
        'classname' => 'local_oksigeniatesape\external\get_log',
        'description' => 'The log of a course, newest first.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:viewlog',
    ],
    'local_oksigeniatesape_undo' => [
        'classname' => 'local_oksigeniatesape\external\undo',
        'description' => 'Undoes an entry of the log of a course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:undo',
    ],
    'local_oksigeniatesape_get_scene' => [
        'classname' => 'local_oksigeniatesape\\external\\get_scene',
        'description' => 'A scene of the mission of the course, to play it.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_play_scene' => [
        'classname' => 'local_oksigeniatesape\\external\\play_scene',
        'description' => 'Plays a scene with what the student did: the server decides the ending and gives what it gives.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_get_missions' => [
        'classname' => 'local_oksigeniatesape\\external\\get_missions',
        'description' => 'The missions that come with the plugin, and the one of the course.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_install_mission' => [
        'classname' => 'local_oksigeniatesape\\external\\install_mission',
        'description' => 'Installs a mission in the course, or changes its skin or local examples keeping what the students did.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_get_results' => [
        'classname' => 'local_oksigeniatesape\\external\\get_results',
        'description' => 'What the class did in the scenes of the mission: plays, objectives and the most common mix-ups.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:viewlog',
    ],
    'local_oksigeniatesape_use_power' => [
        'classname' => 'local_oksigeniatesape\\external\\use_power',
        'description' => 'Asks to use a power, spending its breath; it acts when a teacher approves it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_get_minigame' => [
        'classname' => 'local_oksigeniatesape\\external\\get_minigame',
        'description' => 'A minigame of the mission of the course, as the current student plays it.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_play_minigame' => [
        'classname' => 'local_oksigeniatesape\\external\\play_minigame',
        'description' => 'Plays a minigame with what the student did: the server counts it and gives what it gives, once.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_set_coat' => [
        'classname' => 'local_oksigeniatesape\\external\\set_coat',
        'description' => 'Dresses the creature of the current student with fur, feathers or scales.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_send_idea' => [
        'classname' => 'local_oksigeniatesape\\external\\send_idea',
        'description' => 'Sends an idea of the current student to the teachers of the course.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
    'local_oksigeniatesape_get_powers' => [
        'classname' => 'local_oksigeniatesape\\external\\get_powers',
        'description' => 'The powers of the course and the requests of the students, for its teachers.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:approvepowers',
    ],
    'local_oksigeniatesape_decide_power' => [
        'classname' => 'local_oksigeniatesape\\external\\decide_power',
        'description' => 'A teacher approves or turns down a request to use a power.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:approvepowers',
    ],
    'local_oksigeniatesape_save_power' => [
        'classname' => 'local_oksigeniatesape\\external\\save_power',
        'description' => 'Changes a power of the course, or makes a new one (a privilege of the classroom).',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_make_teams' => [
        'classname' => 'local_oksigeniatesape\\external\\make_teams',
        'description' => 'Makes the teams of the course in one go, with the roles spread, in a new grouping.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:manageregion',
    ],
    'local_oksigeniatesape_get_ideas' => [
        'classname' => 'local_oksigeniatesape\\external\\get_ideas',
        'description' => 'The ideas the students of the course have sent, for its teachers.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_handle_idea' => [
        'classname' => 'local_oksigeniatesape\\external\\handle_idea',
        'description' => 'A teacher marks an idea of a student as read, or deletes it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:award',
    ],
    'local_oksigeniatesape_get_board' => [
        'classname' => 'local_oksigeniatesape\\external\\get_board',
        'description' => 'The class screen: the map of the mission with the lanterns of the class, and the event of the day.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:runclassscreen',
    ],
    'local_oksigeniatesape_class_event' => [
        'classname' => 'local_oksigeniatesape\\external\\class_event',
        'description' => 'The event of the day on the class screen: draw, change, skip or play it.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/oksigeniatesape:runclassscreen',
    ],
    'local_oksigeniatesape_live_host' => [
        'classname' => 'local_oksigeniatesape\\external\\live_host',
        'description' => 'The battle on the class screen: open it, next question, answer, mark teams, finish; and its state.',
        'type' => 'write',
        'ajax' => true,
        'readonlysession' => true,
        'capabilities' => 'local/oksigeniatesape:runclassscreen',
    ],
    'local_oksigeniatesape_live_play' => [
        'classname' => 'local_oksigeniatesape\\external\\live_play',
        'description' => 'The battle on the phone of a student: whether there is one, come in, answer.',
        'type' => 'write',
        'ajax' => true,
        'readonlysession' => true,
        'capabilities' => 'local/oksigeniatesape:play',
    ],
];
