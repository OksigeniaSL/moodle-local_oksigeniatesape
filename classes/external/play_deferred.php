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
use local_oksigeniatesape\local\deferred;

/**
 * The current student, who follows from far, plays the last battle of the class in deferred.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class play_deferred extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'liveid' => new external_value(PARAM_INT, 'The battle'),
            'answers' => new external_multiple_structure(new external_value(PARAM_INT, 'The option chosen, in order')),
        ]);
    }

    /**
     * Plays it.
     *
     * @param int $courseid
     * @param int $liveid
     * @param int[] $answers
     * @return array right, total, gems, helped.
     */
    public static function execute(int $courseid, int $liveid, array $answers): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'liveid' => $liveid,
            'answers' => $answers,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        return deferred::play($params['courseid'], (int) $USER->id, $params['liveid'], $params['answers']);
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'right' => new external_value(PARAM_INT, 'Answers right'),
            'total' => new external_value(PARAM_INT, 'Questions'),
            'gems' => new external_value(PARAM_INT, 'Gems given'),
            'helped' => new external_value(PARAM_BOOL, 'The Embrollo has threads less for the rematch'),
        ]);
    }
}
