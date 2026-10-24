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
use local_oksigeniatesape\local\missions;

/**
 * The teacher changes the mission of the course: sees its texts, changes one, changes the questions of the battle or
 * goes back to the original. The texts go as JSON: they are many, and of many shapes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class edit_mission extends external_api {
    /** @var string[] What can be done. */
    private const ACTIONS = ['view', 'save', 'questions', 'secrets', 'restore', 'export', 'import', 'courses', 'copy', 'skip',
        'challenge', 'knotdraft', 'knotaccept'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(
                PARAM_ALPHA,
                'view, save, questions, secrets (value, the secrets of the teacher in JSON), restore, export, import, ' .
                    'courses, copy, skip (a place: value on, or empty to go back), challenge (of a place: value, the ' .
                    'classroom challenge, 0 none), knotdraft (lanes for the knot in scene, by the AI) or knotaccept ' .
                    '(value, a question in JSON, for the knot in scene)'
            ),
            'scene' => new external_value(
                PARAM_RAW,
                'Code of the scene, # for the places of the mission, empty: all',
                VALUE_DEFAULT,
                ''
            ),
            'path' => new external_value(PARAM_RAW, 'Where the text is', VALUE_DEFAULT, ''),
            'value' => new external_value(
                PARAM_RAW,
                'The text (empty: the original), or the questions or the changes in JSON',
                VALUE_DEFAULT,
                ''
            ),
            'target' => new external_value(PARAM_INT, 'The course the changes are copied to', VALUE_DEFAULT, 0),
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
     * @param int $target
     * @return array json: what the teacher sees after it (or the changes, or the courses, or what was copied).
     */
    public static function execute(
        int $courseid,
        string $action,
        string $scene = '',
        string $path = '',
        string $value = '',
        int $target = 0
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'scene' => $scene,
            'path' => $path,
            'value' => $value,
            'target' => $target,
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
        } else if ($params['action'] === 'secrets') {
            $secrets = json_decode($params['value'], true);
            if (!is_array($secrets)) {
                throw new \invalid_parameter_exception('The secrets are not a list');
            }
            edits::save_secrets($params['courseid'], $secrets);
        } else if ($params['action'] === 'skip') {
            edits::skip($params['courseid'], $scene, $params['value'] === 'on');
        } else if ($params['action'] === 'challenge') {
            edits::place_challenge($params['courseid'], $scene, (int) $params['value']);
        } else if ($params['action'] === 'knotdraft') {
            // A proposal of the AI of the site, kept nowhere: the teacher takes what they want.
            require_capability('local/oksigeniatesape:aidraft', $context);
            return ['json' => json_encode(edits::knot_proposal($params['courseid'], $scene, (int) $USER->id))];
        } else if ($params['action'] === 'knotaccept') {
            $question = json_decode($params['value'], true);
            edits::knot_accept($params['courseid'], $scene, is_array($question) ? $question : []);
        } else if ($params['action'] === 'restore') {
            edits::restore($params['courseid'], $scene === '' ? null : $scene);
        } else if ($params['action'] === 'export') {
            return ['json' => json_encode(edits::export($params['courseid']))];
        } else if ($params['action'] === 'import') {
            $data = json_decode($params['value'], true);
            $done = edits::import($params['courseid'], is_array($data) ? $data : []);
            return ['json' => json_encode(['done' => $done] + edits::view($params['courseid']))];
        } else if ($params['action'] === 'courses') {
            return ['json' => json_encode(self::courses($params['courseid']))];
        } else if ($params['action'] === 'copy') {
            if (!array_key_exists($params['target'], self::courses($params['courseid']))) {
                throw new \invalid_parameter_exception('Not a course to copy to');
            }
            $done = edits::import($params['target'], edits::export($params['courseid']));
            return ['json' => json_encode(['done' => $done] + edits::view($params['courseid']))];
        }
        $view = edits::view($params['courseid']);
        // Whether the AI of the site can draft lanes for the knots here.
        $ai = \local_oksigeniatesape\local\nextclass::status($params['courseid'], (int) $USER->id);
        $view['ai'] = ['canuse' => $ai['canuse'] && has_capability('local/oksigeniatesape:aidraft', $context),
            'policy' => $ai['policy']];
        return ['json' => json_encode($view)];
    }

    /**
     * The other courses of the teacher with the same mission where they can change it.
     *
     * @param int $courseid
     * @return array id => full name.
     */
    private static function courses(int $courseid): array {
        global $USER;
        $mission = missions::get($courseid);
        $list = [];
        if (!$mission) {
            return $list;
        }
        foreach (enrol_get_users_courses((int) $USER->id, true, 'id, fullname') as $course) {
            if ((int) $course->id === $courseid) {
                continue;
            }
            $context = \context_course::instance((int) $course->id);
            $other = missions::get((int) $course->id);
            if ($other && $other->code === $mission->code && has_capability('local/oksigeniatesape:manageregion', $context)) {
                $list[(int) $course->id] = format_string($course->fullname, true, ['context' => $context]);
            }
        }
        return $list;
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
