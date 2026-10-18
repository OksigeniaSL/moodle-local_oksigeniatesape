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
use local_oksigeniatesape\local\live;
use local_oksigeniatesape\local\scenes;

/**
 * A scene played by the whole class on the class screen: the decisions of the class, and what each player gets.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class class_scene extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'sceneid' => new external_value(PARAM_INT, 'Scene'),
            'events' => new external_value(PARAM_RAW, 'What the class did, in JSON'),
            'start' => new external_value(PARAM_ALPHANUMEXT, 'Node where the play started (empty: the start)', VALUE_DEFAULT, ''),
            'phones' => new external_value(PARAM_BOOL, 'The class voted with phones: only those who came in play'),
        ]);
    }

    /**
     * Plays the scene of the class again on the server and gives what it gives.
     *
     * @param int $courseid
     * @param int $sceneid
     * @param string $events
     * @param string $start
     * @param bool $phones
     * @return array
     */
    public static function execute(int $courseid, int $sceneid, string $events, string $start, bool $phones): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sceneid' => $sceneid,
            'events' => $events,
            'start' => $start,
            'phones' => $phones,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        require_capability('local/oksigeniatesape:award', $context);
        $list = json_decode($params['events'], true);
        if (!is_array($list)) {
            throw new \invalid_parameter_exception('The events are not a list');
        }
        $charids = [];
        $live = null;
        if ($params['phones']) {
            $live = live::current($params['courseid'], null, true);
            if (!$live || $live->kind !== 'vote') {
                throw new \moodle_exception('nobattle', 'local_oksigeniatesape');
            }
            $charids = live::joined($live) ?: [0];
        }
        $result = scenes::play_class(
            $params['courseid'],
            $params['sceneid'],
            $list,
            $params['start'] ?: null,
            $charids,
            null,
            $live ? live::votes($live) : null
        );
        // The vote ends only once the scene is played: if playing it fails, the class screen can try again.
        if ($live) {
            live::finish($live, 0);
        }
        $result['objectives'] = json_encode($result['objectives']);
        return $result;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'ending' => new external_value(PARAM_ALPHANUMEXT, 'Ending reached'),
            'outcome' => new external_value(PARAM_ALPHA, 'success, partial or retry'),
            'retry' => new external_value(PARAM_ALPHANUMEXT, 'Node to go back to, for a retry'),
            'points' => new external_value(PARAM_INT, 'Points'),
            'objectives' => new external_value(PARAM_RAW, 'Right and total by objective, in JSON'),
            'rewarded' => new external_value(PARAM_INT, 'Players who got something'),
        ]);
    }
}
