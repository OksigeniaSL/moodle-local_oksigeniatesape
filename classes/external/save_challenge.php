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
 * Makes or changes a classroom challenge.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_challenge extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'id' => new external_value(PARAM_INT, 'Challenge to change (0: a new one)', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'description' => new external_value(PARAM_TEXT, 'What to do', VALUE_DEFAULT, ''),
            'saber' => new external_value(PARAM_INT, 'Knowledge', VALUE_DEFAULT, 0),
            'gems' => new external_value(PARAM_INT, 'Gems', VALUE_DEFAULT, 0),
            'steps' => new external_value(PARAM_SEQUENCE, 'Steps it counts for', VALUE_DEFAULT, '1,2,3,4'),
            'teamwork' => new external_value(PARAM_BOOL, 'Done by the team', VALUE_DEFAULT, false),
            'parts' => new external_value(
                PARAM_RAW,
                'A team task: its parts, role and text of each (JSON; empty: none, null: as it was)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
        ]);
    }

    /**
     * Makes or changes a classroom challenge.
     *
     * @param int $courseid
     * @param int $id
     * @param string $name
     * @param string $description
     * @param int $saber
     * @param int $gems
     * @param string $steps
     * @param bool $teamwork
     * @param string|null $parts
     * @return array
     */
    public static function execute(
        int $courseid,
        int $id,
        string $name,
        string $description = '',
        int $saber = 0,
        int $gems = 0,
        string $steps = '1,2,3,4',
        bool $teamwork = false,
        ?string $parts = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'saber' => $saber,
            'gems' => $gems,
            'steps' => $steps,
            'teamwork' => $teamwork,
            'parts' => $parts,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        $data = $params;
        unset($data['courseid'], $data['parts']);
        if ($params['parts'] !== null) {
            $data['parts'] = (array) (json_decode($params['parts'], true) ?: []);
        }
        $challenge = classroom::save($params['courseid'], $data);
        return ['id' => (int) $challenge->id];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure(['id' => new external_value(PARAM_INT, 'Challenge')]);
    }
}
