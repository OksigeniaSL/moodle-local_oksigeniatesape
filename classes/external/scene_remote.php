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
use local_oksigeniatesape\local\remote;

/**
 * The remote of a scene played by the whole class: the class screen says its buttons (publish) and asks what was
 * pressed (alive), the phone of the teacher reads them (read) and presses one (press); the class screen closes it
 * (clear).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scene_remote extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'publish, alive, read, press or clear'),
            'title' => new external_value(PARAM_TEXT, 'publish: the title of the scene', VALUE_DEFAULT, ''),
            'text' => new external_value(PARAM_TEXT, 'publish: what the scene asks now', VALUE_DEFAULT, ''),
            'buttons' => new external_value(PARAM_RAW, 'publish: the buttons, key and label, in JSON', VALUE_DEFAULT, '[]'),
            'key' => new external_value(PARAM_ALPHANUMEXT, 'press: the button', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Publishes, keeps alive, reads, presses or clears the remote.
     *
     * @param int $courseid
     * @param string $action
     * @param string $title
     * @param string $text
     * @param string $buttons
     * @param string $key
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        string $title = '',
        string $text = '',
        string $buttons = '[]',
        string $key = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'title' => $title,
            'text' => $text,
            'buttons' => $buttons,
            'key' => $key,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        $now = time();
        $state = match ($params['action']) {
            'publish' => remote::publish($params['courseid'], $params['title'], $params['text'],
                array_values(array_filter((array) json_decode($params['buttons'], true), 'is_array')), $now),
            'alive' => remote::alive($params['courseid'], $now),
            'read' => remote::read($params['courseid'], $now),
            'press' => remote::press($params['courseid'], $params['key'], $now),
            'clear' => null,
            default => throw new \invalid_parameter_exception('Unknown action'),
        };
        if ($params['action'] === 'clear') {
            remote::clear($params['courseid']);
        }
        return $state ? ['on' => true, 'title' => $state['title'], 'text' => $state['text'], 'buttons' => $state['buttons'],
            'seq' => (int) $state['press']['seq'], 'pressed' => (string) $state['press']['key']] : ['on' => false];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'on' => new external_value(PARAM_BOOL, 'A class screen shows a scene with its remote'),
            'title' => new external_value(PARAM_TEXT, 'The title of the scene', VALUE_DEFAULT, ''),
            'text' => new external_value(PARAM_TEXT, 'What it asks now', VALUE_DEFAULT, ''),
            'buttons' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHANUMEXT, 'The button'),
                'label' => new external_value(PARAM_TEXT, 'What it says'),
            ]), 'What the class screen shows to press', VALUE_DEFAULT, []),
            'seq' => new external_value(PARAM_INT, 'Presses made from the phone', VALUE_DEFAULT, 0),
            'pressed' => new external_value(PARAM_ALPHANUMEXT, 'The last button pressed', VALUE_DEFAULT, ''),
        ]);
    }
}
