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
 * The game in the Moodle app (and in apps made from it): an entry of the main menu with the games of the user, and a
 * tab in each course with a game. Both open the game inside the app, with the session.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_oksigeniatesape' => [
        'handlers' => [
            'games' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'mobile_games_view',
                'init' => 'mobile_games_init',
                'displaydata' => [
                    'title' => 'mobile_games',
                    'icon' => 'fas-compass',
                ],
                'priority' => 400,
            ],
            'course' => [
                'delegate' => 'CoreCourseOptionsDelegate',
                'method' => 'mobile_course_view',
                'init' => 'mobile_course_init',
                'displaydata' => [
                    'title' => 'mobile_course',
                    'class' => 'local-oksigeniatesape-course',
                ],
                'priority' => 400,
            ],
        ],
        'lang' => [
            ['mobile_games', 'local_oksigeniatesape'],
            ['mobile_course', 'local_oksigeniatesape'],
        ],
    ],
];
