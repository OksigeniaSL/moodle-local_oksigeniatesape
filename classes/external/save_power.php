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
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * Changes a power of the course, or makes a new one (a privilege of the classroom).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_power extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'id' => new external_value(PARAM_INT, 'Power (0: a new one)'),
            'role' => new external_value(PARAM_ALPHA, 'Role, or all (only for new ones)', VALUE_DEFAULT, 'all'),
            'name' => new external_value(PARAM_RAW, 'Name (empty: the one of the game)', VALUE_DEFAULT, ''),
            'description' => new external_value(PARAM_RAW, 'What it does (empty: the one of the game)', VALUE_DEFAULT, ''),
            'cost' => new external_value(PARAM_INT, 'Breath', VALUE_DEFAULT, 2),
            'minlevel' => new external_value(PARAM_INT, 'Level needed', VALUE_DEFAULT, 1),
            'enabled' => new external_value(PARAM_BOOL, 'On', VALUE_DEFAULT, true),
            'autoapprove' => new external_value(PARAM_BOOL, 'Approved by itself', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Changes a power of the course, or makes a new one (a privilege of the classroom).
     *
     * @param int $courseid
     * @param int $id
     * @param string $role
     * @param string $name
     * @param string $description
     * @param int $cost
     * @param int $minlevel
     * @param bool $enabled
     * @param bool $autoapprove
     * @return array
     */
    public static function execute(
        int $courseid,
        int $id,
        string $role = 'all',
        string $name = '',
        string $description = '',
        int $cost = 2,
        int $minlevel = 1,
        bool $enabled = true,
        bool $autoapprove = false
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'id' => $id,
            'role' => $role,
            'name' => $name,
            'description' => $description,
            'cost' => $cost,
            'minlevel' => $minlevel,
            'enabled' => $enabled,
            'autoapprove' => $autoapprove,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        $data = $params;
        unset($data['courseid']);
        if ($data['id']) {
            unset($data['role']);
        }
        powers::save($params['courseid'], $data);
        return ['powers' => powers::for_teacher($params['courseid'])] + powers::requests($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::powers();
    }
}
