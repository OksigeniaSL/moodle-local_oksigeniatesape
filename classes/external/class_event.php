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
use core_external\external_value;
use local_oksigeniatesape\local\events;

/**
 * The event of the day on the class screen: draw it, change it, skip it, and play it (the class answers the footprint;
 * a team gives breath to another in the exchange).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class class_event extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'draw, skip, solve, gift, aim, deliver or lend'),
            'code' => new external_value(PARAM_ALPHA, 'draw: this card instead of a random one', VALUE_DEFAULT, ''),
            'right' => new external_value(PARAM_BOOL, 'solve: the class got it right', VALUE_DEFAULT, false),
            'from' => new external_value(PARAM_INT, 'gift, lend: group of the team that gives or lends', VALUE_DEFAULT, 0),
            'to' => new external_value(
                PARAM_INT,
                'gift, deliver, lend: group of the team that receives; aim: the team the card goes to',
                VALUE_DEFAULT,
                0
            ),
            'item' => new external_value(PARAM_ALPHANUMEXT, 'lend: code of the item', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Does something with the event of the day.
     *
     * @param int $courseid
     * @param string $action
     * @param string $code
     * @param bool $right
     * @param int $from
     * @param int $to
     * @param string $item
     * @return array The event.
     */
    public static function execute(
        int $courseid,
        string $action,
        string $code = '',
        bool $right = false,
        int $from = 0,
        int $to = 0,
        string $item = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'code' => $code,
            'right' => $right,
            'from' => $from,
            'to' => $to,
            'item' => $item,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        $courseid = $params['courseid'];
        switch ($params['action']) {
            case 'draw':
                $event = events::draw($courseid, $params['code']);
                break;
            case 'skip':
                $event = events::skip($courseid);
                break;
            case 'solve':
                require_capability('local/oksigeniatesape:award', $context);
                $event = events::solve($courseid, $params['right'], (int) $USER->id);
                break;
            case 'gift':
                require_capability('local/oksigeniatesape:award', $context);
                $event = events::gift($courseid, $params['from'], $params['to'], (int) $USER->id);
                break;
            case 'aim':
                $event = events::aim($courseid, $params['to']);
                break;
            case 'deliver':
                require_capability('local/oksigeniatesape:award', $context);
                $event = events::deliver($courseid, $params['to']);
                break;
            case 'lend':
                require_capability('local/oksigeniatesape:award', $context);
                $event = events::lend($courseid, $params['from'], $params['to'], $params['item']);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        return events::export($event);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::event(VALUE_REQUIRED);
    }
}
