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
use local_oksigeniatesape\local\encounters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;

/**
 * The current student says who the being that peeked out in a place is, with the clues they opened.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class answer_encounter extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'place' => new external_value(PARAM_ALPHANUMEXT, 'The place'),
            'choice' => new external_value(PARAM_ALPHANUMEXT, 'The being chosen'),
            'clues' => new external_value(PARAM_INT, 'Clues opened, 1 to 3'),
        ]);
    }

    /**
     * Says who the being is.
     *
     * @param int $courseid
     * @param string $place
     * @param string $choice
     * @param int $clues
     * @return array
     */
    public static function execute(int $courseid, string $place, string $choice, int $clues): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'place' => $place,
            'choice' => $choice, 'clues' => $clues]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $region = regions::get($params['courseid']);
        $char = characters::get((int) $USER->id);
        $mission = missions::get($params['courseid']);
        if (!$region || !$region->enabled || !$char || !$mission) {
            throw new \moodle_exception('noencounter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        $clues = max(1, min(3, $params['clues']));
        return encounters::answer($char, $member, $mission, $params['place'], $params['choice'], $clues);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'right' => new external_value(PARAM_BOOL, 'Recognised'),
            'golden' => new external_value(PARAM_BOOL, 'With one clue: a golden card'),
            'gems' => new external_value(PARAM_INT, 'Gems'),
            'name' => new external_value(PARAM_TEXT, 'Its name, once recognised'),
            'group' => new external_value(PARAM_ALPHA, 'Its group, once recognised'),
            'fact' => new external_value(PARAM_TEXT, 'Something curious about it, once recognised'),
            'again' => new external_value(
                PARAM_BOOL,
                'Missed with «Fresh track»: it stays, and they choose again',
                VALUE_DEFAULT,
                false
            ),
            'crossed' => new external_value(PARAM_ALPHANUMEXT, 'The name missed, crossed out', VALUE_DEFAULT, ''),
        ]);
    }
}
