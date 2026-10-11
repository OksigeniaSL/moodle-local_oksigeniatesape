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
use core_external\external_value;
use local_oksigeniatesape\local\names;
use local_oksigeniatesape\local\view;

/**
 * The current student names their hero or their creature: from the list, at once, or their own, for a teacher to read.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_name extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'what' => new external_value(PARAM_ALPHA, 'hero or creature'),
            'option' => new external_value(PARAM_INT, 'Index in the names offered (-1: their own)'),
            'own' => new external_value(PARAM_TEXT, 'Their own name', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * The current student names their hero or their creature.
     *
     * @param int $courseid
     * @param string $what
     * @param int $option
     * @param string $own
     * @return array The world of the student.
     */
    public static function execute(int $courseid, string $what, int $option, string $own = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'what' => $what,
            'option' => $option,
            'own' => $own,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        names::choose($params['courseid'], (int) $USER->id, $params['what'], $params['option'], $params['own']);
        return view::world($params['courseid'], (int) $USER->id);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return get_world::execute_returns();
    }
}
