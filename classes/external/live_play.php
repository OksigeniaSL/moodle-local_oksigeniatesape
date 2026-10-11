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
use local_oksigeniatesape\local\live;

/**
 * The battle on the phone of a student: whether there is one, come in, answer.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class live_play extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'state, join or answer'),
            'round' => new external_value(PARAM_INT, 'answer: the round', VALUE_DEFAULT, 0),
            'answer' => new external_value(PARAM_INT, 'answer: index of the option', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Does something in the battle of the course and says how it is going for this student.
     *
     * @param int $courseid
     * @param string $action
     * @param int $round
     * @param int $answer
     * @return array
     */
    public static function execute(int $courseid, string $action, int $round = 0, int $answer = -1): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'round' => $round,
            'answer' => $answer,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $now = time();
        $live = $params['action'] === 'state' ? live::recent($params['courseid'], $now) :
            live::current($params['courseid'], $now);
        $char = characters::get((int) $USER->id);
        if (!$live || !$char) {
            if ($params['action'] !== 'state') {
                throw new \moodle_exception('nobattle', 'local_oksigeniatesape');
            }
            return [];
        }
        $member = engine::member($char, $params['courseid'], $now);
        switch ($params['action']) {
            case 'state':
                break;
            case 'join':
                live::join($live, $member, (int) $USER->id, $now);
                break;
            case 'answer':
                live::answer($live, $member, $params['round'], $params['answer'], $now);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        return ['live' => live::device_view($live, $member, $now)];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'live' => structures::live_device(),
        ]);
    }
}
