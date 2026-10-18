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
use local_oksigeniatesape\local\friends;
use local_oksigeniatesape\local\regions;

/**
 * The current student listens to what a friend of the expedition tells them.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class friend_talk extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'friend' => new external_value(PARAM_ALPHANUMEXT, 'The friend, as in the mission'),
            'at' => new external_value(PARAM_INT, 'The talk, by the pages it takes'),
        ]);
    }

    /**
     * Listens.
     *
     * @param int $courseid
     * @param string $friend
     * @param int $at
     * @return array
     */
    public static function execute(int $courseid, string $friend, int $at): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'friend' => $friend,
            'at' => $at]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        return friends::listen($params['courseid'], $char, $params['friend'], $params['at']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'text' => new external_value(PARAM_TEXT, 'What the friend says'),
            'gems' => new external_value(PARAM_INT, 'Gems given now (0: given before)'),
        ]);
    }
}
