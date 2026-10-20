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
use local_oksigeniatesape\local\secrets;

/**
 * A student finds a secret in the painting of a place.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class find_secret extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'id' => new external_value(PARAM_ALPHANUMEXT, 'The secret'),
        ]);
    }

    /**
     * Finds the secret: it goes to the album, with its mark or its page.
     *
     * @param int $courseid
     * @param string $id
     * @return array
     */
    public static function execute(int $courseid, string $id): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'id' => $id]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char) {
            throw new \moodle_exception('nosecret', 'local_oksigeniatesape');
        }
        return secrets::find($char, engine::member($char, $params['courseid']), $params['id']);
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'text' => new external_value(PARAM_TEXT, 'What the secret says'),
            'page' => new external_single_structure([
                'title' => new external_value(PARAM_TEXT, 'Title of the page of the Great Book it gave (empty: none)'),
                'text' => new external_value(PARAM_TEXT, 'Its text'),
            ]),
            'set' => new external_value(PARAM_TEXT, 'What the whole set says, if this was its last secret'),
        ]);
    }
}
