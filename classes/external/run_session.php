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
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\sessions;

/**
 * The teacher runs the session of their class: sees the one going on or the one that comes next with its sheet, starts
 * one, goes to a step, launches on the class screen what a step launches, and closes it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class run_session extends external_api {
    /** @var string[] What can be done. */
    private const ACTIONS = ['view', 'start', 'step', 'show', 'light', 'close'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'view, start, step, show, light or close'),
            'chapter' => new external_value(PARAM_ALPHANUMEXT, 'To start: the place, or entrada', VALUE_DEFAULT, ''),
            'number' => new external_value(PARAM_INT, 'To start: which session of the place', VALUE_DEFAULT, 0),
            'step' => new external_value(PARAM_INT, 'The step to go to', VALUE_DEFAULT, 0),
            'show' => new external_value(
                PARAM_ALPHA,
                'What the class screen shows: enter, card, timer, pacts or nothing',
                VALUE_DEFAULT,
                ''
            ),
            'minutes' => new external_value(PARAM_INT, 'Of the timer', VALUE_DEFAULT, 0),
            'target' => new external_value(PARAM_INT, 'The scene to play with the class', VALUE_DEFAULT, 0),
            'review' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Something to review'),
                'To close: what to review at the start of the next session',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Runs the session.
     *
     * @param int $courseid
     * @param string $action
     * @param string $chapter
     * @param int $number
     * @param int $step
     * @param string $show
     * @param int $minutes
     * @param int $target
     * @param array $review
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        string $chapter = '',
        int $number = 0,
        int $step = 0,
        string $show = '',
        int $minutes = 0,
        int $target = 0,
        array $review = []
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'chapter' => $chapter, 'number' => $number, 'step' => $step, 'show' => $show, 'minutes' => $minutes,
            'target' => $target, 'review' => $review]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \invalid_parameter_exception('Unknown action');
        }
        if ($params['action'] !== 'view' && !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        switch ($params['action']) {
            case 'start':
                sessions::start($params['courseid'], $params['chapter'], $params['number']);
                break;
            case 'step':
                sessions::step($params['courseid'], $params['step']);
                break;
            case 'show':
                if ($params['show'] === 'light') {
                    throw new \invalid_parameter_exception('The lantern is lit with light');
                }
                sessions::show($params['courseid'], $params['show'], $params['minutes'], $params['target']);
                break;
            case 'light':
                sessions::light($params['courseid']);
                break;
            case 'close':
                sessions::close($params['courseid'], $params['review']);
                break;
        }
        return ['json' => json_encode(sessions::teacher($params['courseid']))];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'The session going on or the next one, with its sheet, and the list of all'),
        ]);
    }
}
