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
use local_oksigeniatesape\local\edits;

/**
 * The teacher changes the mission of the course: sees its texts, changes one, changes the questions of the battle or
 * goes back to the original. The texts go as JSON: they are many, and of many shapes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class edit_mission extends external_api {
    /** @var string[] What can be done. */
    private const ACTIONS = ['view', 'save', 'questions', 'restore'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'view, save, questions or restore'),
            'scene' => new external_value(
                PARAM_RAW,
                'Code of the scene, # for the places of the mission, empty: all',
                VALUE_DEFAULT,
                ''
            ),
            'path' => new external_value(PARAM_RAW, 'Where the text is', VALUE_DEFAULT, ''),
            'value' => new external_value(
                PARAM_RAW,
                'The text (empty: the original), or the questions in JSON',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Sees or changes the texts of the mission of the course.
     *
     * @param int $courseid
     * @param string $action
     * @param string $scene
     * @param string $path
     * @param string $value
     * @return array json: what the teacher sees after it.
     */
    public static function execute(
        int $courseid,
        string $action,
        string $scene = '',
        string $path = '',
        string $value = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'scene' => $scene,
            'path' => $path,
            'value' => $value,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
        $scene = clean_param($params['scene'], PARAM_RAW_TRIMMED);
        if ($scene !== '' && $scene !== edits::MISSION && !preg_match('/^[A-Za-z0-9_-]+$/', $scene)) {
            throw new \invalid_parameter_exception('Not a scene: ' . $scene);
        }
        if ($params['action'] === 'save') {
            edits::save($params['courseid'], $scene === '' ? edits::MISSION : $scene, $params['path'], $params['value']);
        } else if ($params['action'] === 'questions') {
            $questions = json_decode($params['value'], true);
            if (!is_array($questions)) {
                throw new \invalid_parameter_exception('The questions are not a list');
            }
            edits::save_questions($params['courseid'], $questions);
        } else if ($params['action'] === 'restore') {
            edits::restore($params['courseid'], $scene === '' ? null : $scene);
        }
        return ['json' => json_encode(edits::view($params['courseid']))];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'The places, scenes and questions of the mission, with their texts'),
        ]);
    }
}
