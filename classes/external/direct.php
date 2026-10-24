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
use local_oksigeniatesape\local\director;

/**
 * «What do they do?»: the teacher, as the director of the game, gives a mark of the story to a team or to the class, with
 * what they did in a line.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class direct extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'groupid' => new external_value(PARAM_INT, 'The team; 0: the whole class'),
            'flag' => new external_value(PARAM_ALPHANUMEXT, 'A mark of the mission for the director'),
            'note' => new external_value(PARAM_TEXT, 'What they did, in a line: the students see it'),
        ]);
    }

    /**
     * Gives the mark.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $flag
     * @param string $note
     * @return array given.
     */
    public static function execute(int $courseid, int $groupid, string $flag, string $note): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'groupid' => $groupid,
            'flag' => $flag,
            'note' => $note,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if ($params['groupid'] && (int) (groups_get_group($params['groupid'], 'courseid')->courseid ?? 0) !== $params['courseid']) {
            throw new \invalid_parameter_exception('Not a team of the course');
        }
        return ['given' => director::give(
            $params['courseid'],
            $params['groupid'],
            $params['flag'],
            $params['note'],
            (int) $USER->id
        )];
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'given' => new external_value(PARAM_INT, 'Students who got it'),
        ]);
    }
}
