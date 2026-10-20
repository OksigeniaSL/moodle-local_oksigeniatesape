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
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\stains;

/**
 * The current student cleans a stain the Embrollo left in the valley: the mistakes of its sheet, touched.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class clean_stain extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'stainid' => new external_value(PARAM_INT, 'Stain'),
            'payload' => new external_value(PARAM_RAW, 'What was touched: marks (the mistakes) and misses (words right), in JSON'),
        ]);
    }

    /**
     * The current student cleans a stain.
     *
     * @param int $courseid
     * @param int $stainid
     * @param string $payload
     * @return array
     */
    public static function execute(int $courseid, int $stainid, string $payload): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'stainid' => $stainid,
            'payload' => $payload]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $region = regions::get($params['courseid']);
        $char = characters::get((int) $USER->id);
        if (!$region || !$region->enabled || !$char) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        $done = (array) (json_decode($params['payload'], true) ?: []);
        return stains::clean($params['courseid'], $params['stainid'], engine::member($char, $params['courseid']), $done);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'clean' => new external_value(PARAM_BOOL, 'It is clean now'),
            'gems' => new external_value(PARAM_INT, 'Gems for cleaning it'),
            'complaint' => new external_value(PARAM_TEXT, 'What the Embrollo says'),
            'solution' => new external_multiple_structure(new external_single_structure([
                'mark' => new external_value(PARAM_INT, 'The mistake'),
                'right' => new external_multiple_structure(new external_value(PARAM_TEXT, 'What it should say')),
                'why' => new external_value(PARAM_TEXT, 'Why'),
            ]), 'Each mistake with what it should say, once clean'),
        ]);
    }
}
