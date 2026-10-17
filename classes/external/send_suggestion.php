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
use local_oksigeniatesape\local\suggestions;

/**
 * Sends a suggestion of a teacher or an administrator to Oksigenia, when the site has suggestions on.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class send_suggestion extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course whose panel it is sent from'),
            'text' => new external_value(PARAM_RAW, 'The suggestion (tags are taken out)'),
            'email' => new external_value(PARAM_RAW_TRIMMED, 'An email to answer, only if they wrote it', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Sends a suggestion of a teacher or an administrator to Oksigenia.
     *
     * @param int $courseid
     * @param string $text
     * @param string $email
     * @return array
     */
    public static function execute(int $courseid, string $text, string $email = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'text' => $text,
            'email' => $email,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        suggestions::send((int) $USER->id, $params['text'], $params['email']);
        return ['sent' => true];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'sent' => new external_value(PARAM_BOOL, 'It reached Oksigenia'),
        ]);
    }
}
