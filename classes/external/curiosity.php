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
use local_oksigeniatesape\local\curiosities;
use local_oksigeniatesape\local\games;

/**
 * The shop of the naturalist for a student: buys a curiosity, opens it to read, or ends it with its questions.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curiosity extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'buy, read or finish'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'The curiosity'),
            'answers' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'An answer'),
                'To finish: the answer to each question, in their order',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Buys a curiosity, opens it to read, or ends it.
     *
     * @param int $courseid
     * @param string $action
     * @param string $code
     * @param string[] $answers
     * @return array
     */
    public static function execute(int $courseid, string $action, string $code, array $answers = []): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'code' => $code,
            'answers' => $answers,
        ]);
        $context = games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $userid = (int) $USER->id;
        switch ($params['action']) {
            case 'buy':
                curiosities::buy($params['courseid'], $userid, $params['code']);
                return ['read' => curiosities::read($params['courseid'], $userid, $params['code'])];
            case 'read':
                return ['read' => curiosities::read($params['courseid'], $userid, $params['code'])];
            case 'finish':
                return ['result' => curiosities::finish($params['courseid'], $userid, $params['code'], $params['answers'])];
            default:
                throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'read' => new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'The curiosity'),
                'title' => new external_value(PARAM_TEXT, 'Its title'),
                'pages' => new external_multiple_structure(new external_value(PARAM_TEXT, 'A page to read')),
                'questions' => new external_multiple_structure(new external_single_structure([
                    'prompt' => new external_value(PARAM_TEXT, 'The question'),
                    'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'An answer to choose')),
                    'answer' => new external_value(PARAM_TEXT, 'The right one'),
                    'why' => new external_value(PARAM_TEXT, 'Why'),
                ])),
                'fact' => new external_value(PARAM_TEXT, 'What to tell the class'),
                'back' => new external_value(PARAM_INT, 'Gems it gives back'),
                'done' => new external_value(PARAM_BOOL, 'Read before with its questions right'),
            ], 'The curiosity to read', VALUE_OPTIONAL),
            'result' => new external_single_structure([
                'right' => new external_value(PARAM_BOOL, 'All the questions right'),
                'first' => new external_value(PARAM_BOOL, 'The first time: the gems, the seal and the golden card'),
                'gems' => new external_value(PARAM_INT, 'Gems given back now'),
                'seals' => new external_value(PARAM_INT, 'Seals of curiosity of the student in the course'),
            ], 'How it ended', VALUE_OPTIONAL),
        ]);
    }
}
