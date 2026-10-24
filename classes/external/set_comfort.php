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
use core_external\external_value;
use local_oksigeniatesape\local\adapt;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * A student turns on for themselves the adaptations of comfort (big text, reading aloud, no movement, high contrast,
 * colours, no sounds): they do not change the game.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_comfort extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'adapt' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'Adaptation of comfort'),
                'Turned on',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Keeps the adaptations of comfort of the student and gives back their world, adapted.
     *
     * @param int $courseid
     * @param array $adapt
     * @return array
     */
    public static function execute(int $courseid, array $adapt = []): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'adapt' => $adapt,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        adapt::save_own(engine::member($char, $params['courseid']), $params['adapt']);
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
