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
use local_oksigeniatesape\local\stains;

/**
 * The teacher takes a stain of the Embrollo as cleaned by the class (aloud, on the class screen), or rubs it out.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class erase_stain extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'stainid' => new external_value(PARAM_INT, 'Stain'),
            'payload' => new external_value(
                PARAM_RAW,
                'The class cleaning it on the class screen: the mistakes the teacher touched, in JSON (empty: taken as cleaned)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Takes a stain as cleaned.
     *
     * @param int $courseid
     * @param int $stainid
     * @param string $payload The mistakes the teacher touched with the class, in JSON; empty to take it as cleaned.
     * @return array
     */
    public static function execute(int $courseid, int $stainid, string $payload = ''): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'stainid' => $stainid,
            'payload' => $payload]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if ($params['payload'] !== '') {
            // Cleaned by the class, aloud: it counts only if every mistake was found.
            $said = json_decode($params['payload'], true);
            $result = stains::clean_class($params['courseid'], $params['stainid'], is_array($said) ? $said : []);
            return ['erased' => $result['clean']] + $result;
        }
        stains::erase($params['courseid'], $params['stainid']);
        return ['erased' => true];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'erased' => new external_value(PARAM_BOOL, 'Taken as cleaned'),
            'clean' => new external_value(PARAM_BOOL, 'Cleaned by the class: every mistake found', VALUE_DEFAULT, false),
            'complaint' => new external_value(PARAM_TEXT, 'What the Embrollo says', VALUE_DEFAULT, ''),
            'solution' => new external_multiple_structure(new external_single_structure([
                'mark' => new external_value(PARAM_INT, 'The mistake'),
                'right' => new external_multiple_structure(new external_value(PARAM_TEXT, 'What it should say')),
                'why' => new external_value(PARAM_TEXT, 'Why'),
            ]), 'Once clean: each mistake with what it should say', VALUE_DEFAULT, []),
        ]);
    }
}
