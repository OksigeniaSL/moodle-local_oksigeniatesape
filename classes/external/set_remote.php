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
use local_oksigeniatesape\local\view;

/**
 * Marks that a student follows the expedition from far (home, hospital), or not.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_remote extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Student'),
            'remote' => new external_value(PARAM_BOOL, 'From far'),
            'shown' => new external_value(PARAM_BOOL, 'Their team sees it', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Marks that a student follows the expedition from far (home, hospital), or not, and whether their team sees it.
     *
     * @param int $courseid
     * @param int $userid
     * @param bool $remote
     * @param bool $shown
     * @return array
     */
    public static function execute(
        int $courseid,
        int $userid,
        bool $remote,
        bool $shown = false
    ): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'remote' => $remote,
            'shown' => $shown,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        view::require_students($params['courseid'], [$params['userid']]);
        $char = characters::get($params['userid']);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        // 1: only they and their teachers see it; 2: their team too.
        $value = $params['remote'] ? ($params['shown'] ? 2 : 1) : 0;
        $DB->update_record('local_oksigeniatesape_member', (object) ['id' => $member->id, 'remote' => $value,
            'timemodified' => time()]);
        return ['remote' => $value > 0, 'shown' => $value === 2];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'remote' => new external_value(PARAM_BOOL, 'From far'),
            'shown' => new external_value(PARAM_BOOL, 'Their team sees it'),
        ]);
    }
}
