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
use local_oksigeniatesape\local\minions;

/**
 * A student answers the minion of the Embrollo that stands on the road of the map.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class untangle_minion extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'The minion'),
            'answer' => new external_value(PARAM_INT, 'Option chosen, as shown'),
        ]);
    }

    /**
     * Answers the question of the minion, once a day.
     *
     * @param int $courseid
     * @param string $code
     * @param int $answer
     * @return array
     */
    public static function execute(int $courseid, string $code, int $answer): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'code' => $code,
            'answer' => $answer]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char) {
            throw new \moodle_exception('nominion', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        return minions::answer($char, $member, $params['code'], $params['answer']);
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'right' => new external_value(PARAM_BOOL, 'It was right: the minion is untangled'),
            'correct' => new external_value(PARAM_INT, 'The right option, as shown'),
            'why' => new external_value(PARAM_TEXT, 'Why'),
            'gems' => new external_value(PARAM_INT, 'Gems given'),
            'weakness' => new external_value(PARAM_TEXT, 'What untangles it, once untangled'),
        ]);
    }
}
