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
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * The teacher makes the character of a student: for classes where the students do not use a device. If the student
 * comes in one day, they find it ready and can change how it looks.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class make_character extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Student'),
            'role' => new external_value(PARAM_ALPHA, 'protector, healer, sage or scout'),
            'nameform' => new external_value(PARAM_ALPHA, 'm or f: the form of the names of roles and lineages'),
            'lineage' => new external_value(PARAM_ALPHA, 'human, elf, dwarf or fairy (empty: none)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Makes the character of a student of the course and brings them into the game of the course.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $role
     * @param string $nameform
     * @param string $lineage
     * @return array
     */
    public static function execute(int $courseid, int $userid, string $role, string $nameform, string $lineage = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'role' => $role,
            'nameform' => $nameform,
            'lineage' => $lineage,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if (!regions::enabled($params['courseid'])) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        view::require_students($params['courseid'], [$params['userid']]);
        $char = characters::create($params['userid'], $params['role'], $params['nameform'], $params['lineage'] ?: null);
        engine::member($char, $params['courseid']);
        return ['id' => (int) $char->id];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure(['id' => new external_value(PARAM_INT, 'The character')]);
    }
}
