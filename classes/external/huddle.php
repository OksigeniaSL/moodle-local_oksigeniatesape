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
use local_oksigeniatesape\local\huddles;

/**
 * The team session of a student: what their tablet sees, and what it does in it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class huddle extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'mine, open, join, start, publish, decide, leave or close'),
            'sceneid' => new external_value(PARAM_INT, 'open: the scene', VALUE_DEFAULT, 0),
            'huddleid' => new external_value(PARAM_INT, 'start, publish, decide, close: the session', VALUE_DEFAULT, 0),
            'node' => new external_value(PARAM_RAW, 'publish: the decision at hand (JSON)', VALUE_DEFAULT, ''),
            'option' => new external_value(PARAM_ALPHANUMEXT, 'decide: the option chosen', VALUE_DEFAULT, ''),
            'seq' => new external_value(PARAM_INT, 'decide: the number of the decision seen', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Does something in the session of the team of the current student, or a teacher closes one.
     *
     * @param int $courseid
     * @param string $action
     * @param int $sceneid
     * @param int $huddleid
     * @param string $node
     * @param string $option
     * @param int $seq
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        int $sceneid = 0,
        int $huddleid = 0,
        string $node = '',
        string $option = '',
        int $seq = 0
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'sceneid' => $sceneid, 'huddleid' => $huddleid, 'node' => $node, 'option' => $option, 'seq' => $seq]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        $now = time();
        $courseid = $params['courseid'];
        // A teacher closes a session from the class screen.
        if ($params['action'] === 'close' && has_capability('local/oksigeniatesape:award', $context)) {
            $found = $DB->get_record('local_oksigeniatesape_huddle', ['id' => $params['huddleid'], 'courseid' => $courseid]);
            if ($found) {
                huddles::close($found, $now);
            }
            return [];
        }
        require_capability('local/oksigeniatesape:play', $context);
        $userid = (int) $USER->id;
        switch ($params['action']) {
            case 'mine':
                $view = huddles::mine($courseid, $userid, $now);
                return $view ? ['huddle' => $view] : [];
            case 'open':
                huddles::open($courseid, $userid, $params['sceneid'], $now);
                return ['huddle' => huddles::mine($courseid, $userid, $now)];
            case 'join':
                $view = huddles::join($courseid, $userid, $now);
                return $view ? ['huddle' => $view] : [];
        }
        [$session, $char] = huddles::get($courseid, $userid, $params['huddleid'], $now);
        switch ($params['action']) {
            case 'start':
                huddles::start($session, $char, $now);
                break;
            case 'publish':
                huddles::publish($session, $char, (array) (json_decode($params['node'], true) ?: []), $now);
                break;
            case 'decide':
                huddles::decide($session, $char, $params['option'], $params['seq'], $now);
                break;
            case 'leave':
                huddles::leave($session, $char);
                return [];
            case 'close':
                if ((int) $session->charid !== (int) $char->id) {
                    throw new \moodle_exception('huddlenotyours', 'local_oksigeniatesape');
                }
                huddles::close($session, $now);
                return [];
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        return ['huddle' => huddles::view($session, $char, $now)];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'huddle' => new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Session'),
                'sceneid' => new external_value(PARAM_INT, 'Scene'),
                'scene' => new external_value(PARAM_TEXT, 'Its title'),
                'guide' => new external_value(PARAM_BOOL, 'The scene runs on this tablet'),
                'joined' => new external_value(PARAM_BOOL, 'This tablet is in'),
                'guidename' => new external_value(PARAM_TEXT, 'Who runs it'),
                'started' => new external_value(PARAM_BOOL, 'Started'),
                'members' => new external_multiple_structure(new external_single_structure([
                    'userid' => new external_value(PARAM_INT, 'Student'),
                    'name' => new external_value(PARAM_TEXT, 'Name classmates see'),
                    'me' => new external_value(PARAM_BOOL, 'This student'),
                ]), 'Who is in now, the guide first'),
                'players' => new external_multiple_structure(new external_value(PARAM_INT, 'Student'), 'Who plays (from the start)'),
                'seq' => new external_value(PARAM_INT, 'Number of the decision at hand'),
                'kind' => new external_value(PARAM_ALPHA, 'Kind of decision (choose, classify, order…; ending at the end)'),
                'node' => new external_value(PARAM_ALPHANUMEXT, 'Node of the scene'),
                'prompt' => new external_value(PARAM_TEXT, 'What it asks'),
                'options' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_ALPHANUMEXT, 'Option'),
                    'label' => new external_value(PARAM_TEXT, 'What it says'),
                ]), 'The options, if it is one to choose'),
                'hand' => new external_value(PARAM_INT, 'Who decides'),
                'handname' => new external_value(PARAM_TEXT, 'Their name'),
                'myturn' => new external_value(PARAM_BOOL, 'This student decides'),
                'piece' => new external_value(PARAM_TEXT, 'What only their role sees of the decision', VALUE_DEFAULT, ''),
                'known' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Something the answers told'),
                    'What the answers told so far',
                    VALUE_DEFAULT,
                    []
                ),
                'waiting' => new external_value(PARAM_BOOL, 'A choice waits for the guide'),
                'pending' => new external_single_structure([
                    'by' => new external_value(PARAM_INT, 'Who chose'),
                    'option' => new external_value(PARAM_ALPHANUMEXT, 'What'),
                    'seq' => new external_value(PARAM_INT, 'For which decision'),
                ], 'For the guide: the choice to play', VALUE_OPTIONAL),
            ], 'The session of the team', VALUE_OPTIONAL),
        ]);
    }
}
