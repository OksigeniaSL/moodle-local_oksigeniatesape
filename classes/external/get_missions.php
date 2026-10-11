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
 * The missions that come with the plugin, and the one of the course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_missions extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
        ]);
    }

    /**
     * The missions that come with the plugin, and the one of the course.
     *
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        return self::missions($params['courseid']);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'installed' => new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin'),
                'variant' => new external_value(PARAM_ALPHANUMEXT, 'Local examples'),
                'activitiesmissing' => new external_value(PARAM_BOOL, 'Some chapters bring Moodle activities not made yet'),
            ], 'Mission of the course', VALUE_OPTIONAL),
            'packages' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'subtitle' => new external_value(PARAM_TEXT, 'Subtitle'),
                'ages' => new external_value(PARAM_TEXT, 'Ages'),
                'lang' => new external_value(PARAM_ALPHANUMEXT, 'Language of the texts'),
                'chapters' => new external_value(PARAM_INT, 'Chapters'),
                'scenes' => new external_value(PARAM_INT, 'Scenes'),
                'skins' => new external_multiple_structure(new external_single_structure([
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                ])),
                'variants' => new external_multiple_structure(new external_single_structure([
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'name' => new external_value(PARAM_TEXT, 'Name'),
                ])),
            ])),
            'expeditions' => structures::expeditions(),
        ]);
    }

    /**
     * The missions that come with the plugin, and the one installed in a course.
     *
     * @param int $courseid
     * @return array
     */
    public static function missions(int $courseid): array {
        $result = ['packages' => []];
        foreach (missions::packages() as $code => $package) {
            $scenes = missions::package_scenes($code);
            $result['packages'][] = [
                'code' => $code,
                'title' => missions::text($package['title'] ?? $code, 'valley'),
                'subtitle' => missions::text($package['subtitle'] ?? '', 'valley'),
                'ages' => (string) ($package['ages'] ?? ''),
                'lang' => (string) ($package['lang'] ?? 'en'),
                'chapters' => count($package['chapters'] ?? []),
                'scenes' => count($scenes),
                'skins' => array_map(
                    fn($k, $v) => ['code' => $k, 'name' => $v],
                    array_keys($package['skins'] ?? []),
                    $package['skins'] ?? []
                ),
                'variants' => array_map(
                    fn($k, $v) => ['code' => $k, 'name' => $v],
                    array_keys($package['variants'] ?? []),
                    $package['variants'] ?? []
                ),
            ];
        }
        $mission = missions::get($courseid);
        if ($mission) {
            $result['installed'] = [
                'code' => $mission->code,
                'title' => missions::text($mission->data['title'] ?? $mission->code, $mission->skin),
                'skin' => $mission->skin,
                'variant' => $mission->variant,
                'activitiesmissing' => missions::activities_missing($mission),
            ];
        }
        $result['expeditions'] = view::expeditions($courseid);
        return $result;
    }
}
