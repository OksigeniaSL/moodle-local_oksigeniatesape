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
];
