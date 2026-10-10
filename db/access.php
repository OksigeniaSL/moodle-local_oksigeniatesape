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
 * Capabilities: students play; teachers run the game in their course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$teachers = [
    'teacher' => CAP_ALLOW,
    'editingteacher' => CAP_ALLOW,
    'manager' => CAP_ALLOW,
];

$capabilities = [
    // Play in the course: the character, its team, the scenes and the minigames.
    'local/oksigeniatesape:play' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'student' => CAP_ALLOW,
        ],
    ],
    // Turn the game on in a course and set it up: family, teams, caps, rules, powers and the mission.
    'local/oksigeniatesape:manageregion' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    // Give saber, gems or items by hand.
    'local/oksigeniatesape:award' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
    // Approve or turn down the powers the students ask to use.
    'local/oksigeniatesape:approvepowers' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
    // Pause a student in the game, with a reason only they and the teachers see.
    'local/oksigeniatesape:pause' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
    // See the log of everything that adds and takes away in the course.
    'local/oksigeniatesape:viewlog' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
    // Undo an entry of the log.
    'local/oksigeniatesape:undo' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
    // Run the class screen and the battles, with the names and photos of the class.
    'local/oksigeniatesape:runclassscreen' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => $teachers,
    ],
];
