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
use local_oksigeniatesape\local\competencies;

/**
 * The teacher chooses the Moodle competency an objective of the mission leaves evidence in.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_competency extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'objective' => new external_value(PARAM_ALPHANUMEXT, 'Code of the objective'),
            'competencyid' => new external_value(PARAM_INT, 'Competency (0: none)'),
        ]);
    }

    /**
     * The teacher chooses the Moodle competency an objective of the mission leaves evidence in.
     *
     * @param int $courseid
     * @param string $objective
     * @param int $competencyid
     * @return array
     */
    public static function execute(int $courseid, string $objective, int $competencyid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'objective' => $objective,
            'competencyid' => $competencyid,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        competencies::set($params['courseid'], $params['objective'], $params['competencyid']);
        return competencies::view($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::competencies();
    }
}
