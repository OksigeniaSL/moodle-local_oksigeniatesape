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
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * A teacher marks an idea of a student as read, or deletes it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class handle_idea extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'ideaid' => new external_value(PARAM_INT, 'Idea'),
            'action' => new external_value(PARAM_ALPHA, 'read or delete'),
        ]);
    }

    /**
     * A teacher marks an idea of a student as read, or deletes it.
     *
     * @param int $courseid
     * @param int $ideaid
     * @param string $action
     * @return array
     */
    public static function execute(
        int $courseid,
        int $ideaid,
        string $action
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'ideaid' => $ideaid,
            'action' => $action,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        ideas::handle($params['courseid'], $params['ideaid'], $params['action']);
        return ideas::list($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::ideas();
    }
}
