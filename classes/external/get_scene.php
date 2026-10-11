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
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\results;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\view;

/**
 * A scene of the mission of the course, to play it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_scene extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'sceneid' => new external_value(PARAM_INT, 'Scene'),
        ]);
    }

    /**
     * A scene of the mission of the course, to play it.
     *
     * @param int $courseid
     * @param int $sceneid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sceneid
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sceneid' => $sceneid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        if (!has_capability('local/oksigeniatesape:play', $context)) {
            // The class screen plays scenes with the whole class.
            require_capability('local/oksigeniatesape:runclassscreen', $context);
        }
        $scene = scenes::get($params['courseid'], $params['sceneid']);
        $mission = missions::get($params['courseid']);
        $speakers = [];
        foreach ($mission->data['speakers'] ?? [] as $code => $name) {
            $speakers[$code] = missions::text($name, $mission->skin);
        }
        $items = [];
        foreach ($mission->data['items'] ?? [] as $code => $item) {
            $items[$code] = (string) ($item['name'] ?? $code);
        }
        return [
            'id' => (int) $scene->id,
            'definition' => $scene->definition,
            'skin' => $mission->skin,
            'variant' => $mission->variant,
            'speakers' => json_encode($speakers),
            'items' => json_encode($items),
        ];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Scene'),
            'definition' => new external_value(PARAM_RAW, 'The scene (JSON)'),
            'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin of the mission'),
            'variant' => new external_value(PARAM_ALPHANUMEXT, 'Local examples'),
            'speakers' => new external_value(PARAM_RAW, 'Names of who speaks (JSON)'),
            'items' => new external_value(PARAM_RAW, 'Names of the items (JSON)'),
        ]);
    }
}
