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
use local_oksigeniatesape\local\stains;

/**
 * The current student answers the question of a page of the Great Book the Embrollo stole, once a day.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class recover_page extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'stainid' => new external_value(PARAM_INT, 'The page stolen (its action of the day)'),
            'answer' => new external_value(PARAM_INT, 'The option chosen'),
        ]);
    }

    /**
     * The current student answers.
     *
     * @param int $courseid
     * @param int $stainid
     * @param int $answer
     * @return array
     */
    public static function execute(int $courseid, int $stainid, int $answer): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'stainid' => $stainid,
            'answer' => $answer]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $region = regions::get($params['courseid']);
        $char = characters::get((int) $USER->id);
        if (!$region || !$region->enabled || !$char) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        return stains::recover($params['courseid'], $params['stainid'], $char, $member, $params['answer']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'right' => new external_value(PARAM_BOOL, 'The page is back'),
            'gems' => new external_value(PARAM_INT, 'Gems for bringing it back'),
            'title' => new external_value(PARAM_TEXT, 'The title of the page, once back'),
            'text' => new external_value(PARAM_TEXT, 'Its text, once back'),
            'why' => new external_value(PARAM_TEXT, 'The why of its knot, once back'),
        ]);
    }
}
