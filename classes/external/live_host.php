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
use local_oksigeniatesape\local\regions;

/**
 * The battle on the class screen: open it, the next question, show the answer, mark the teams that answered aloud,
 * finish it; and how it is going, polled every second and a half.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class live_host extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'state, start, next, reveal, mark, finish, startvote or vote'),
            'mode' => new external_value(PARAM_ALPHA, 'start: phones or aloud', VALUE_DEFAULT, 'phones'),
            'count' => new external_value(PARAM_INT, 'start: questions', VALUE_DEFAULT, 6),
            'secs' => new external_value(PARAM_INT, 'start: seconds per question (0: no limit)', VALUE_DEFAULT, 30),
            'groupid' => new external_value(PARAM_INT, 'mark: the team', VALUE_DEFAULT, 0),
            'right' => new external_value(PARAM_BOOL, 'mark: it got it right', VALUE_DEFAULT, false),
            'sceneid' => new external_value(PARAM_INT, 'startvote: the scene the class plays', VALUE_DEFAULT, 0),
            'text' => new external_value(PARAM_TEXT, 'vote: what the scene asks', VALUE_DEFAULT, ''),
            'options' => new external_value(PARAM_RAW, 'vote: the options, in JSON', VALUE_DEFAULT, '[]'),
            'round' => new external_value(PARAM_INT, 'next and reveal: round on the class screen (-1: any)', VALUE_DEFAULT, -1),
            'final' => new external_value(PARAM_BOOL, 'start: the final battle of the mission', VALUE_DEFAULT, false),
            'turns' => new external_value(PARAM_BOOL, 'start: by turns, with the knots of the mission', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Does something with the battle of the course and says how it is going.
     *
     * @param int $courseid
     * @param string $action
     * @param string $mode
     * @param int $count
     * @param int $secs
     * @param int $groupid
     * @param bool $right
     * @param int $sceneid
     * @param string $text
     * @param string $options
     * @param int $round
     * @param bool $final
     * @param bool $turns
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        string $mode = 'phones',
        int $count = 6,
        int $secs = 30,
        int $groupid = 0,
        bool $right = false,
        int $sceneid = 0,
        string $text = '',
        string $options = '[]',
        int $round = -1,
        bool $final = false,
        bool $turns = false
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'mode' => $mode,
            'count' => $count,
            'secs' => $secs,
            'groupid' => $groupid,
            'right' => $right,
            'sceneid' => $sceneid,
            'text' => $text,
            'options' => $options,
            'round' => $round,
            'final' => $final,
            'turns' => $turns,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        $courseid = $params['courseid'];
        $now = time();
        if ($params['action'] === 'state') {
            $live = live::recent($courseid, $now, true);
            if (!$live) {
                return [];
            }
            if (!$live->timeend) {
                live::heartbeat($live, $now);
                // A battle by turns moves on by itself when the time of a stage is over.
                $award = has_capability('local/oksigeniatesape:award', $context);
                $userid = (int) $USER->id;
                $live = live::locked($courseid, fn() => live::tick(live::recent($courseid, $now, true) ?? $live, $userid, $award, $now));
            }
            return ['live' => live::board_view($live, $now)];
        }
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $userid = (int) $USER->id;
        return live::locked($courseid, fn() => self::act($params, $context, $userid, $now));
    }

    /**
     * Does an action of the class screen, with the session read again inside the lock.
     *
     * @param array $params
     * @param \context_course $context
     * @param int $userid
     * @param int $now
     * @return array
     */
    private static function act(array $params, \context_course $context, int $userid, int $now): array {
        $courseid = $params['courseid'];
        $from = $params['round'] >= 0 ? $params['round'] : null;
        if ($params['action'] === 'start') {
            $live = live::start(
                $courseid,
                $params['mode'],
                $params['count'],
                $params['secs'],
                $now,
                $params['final'],
                $params['turns']
            );
            return ['live' => live::board_view($live, $now)];
        }
        if ($params['action'] === 'startvote') {
            $live = live::start_vote($courseid, $params['sceneid'], $now);
            return ['live' => live::board_view($live, $now)];
        }
        $live = live::current($courseid, $now, true);
        if (!$live) {
            throw new \moodle_exception('nobattle', 'local_oksigeniatesape');
        }
        // Back on the session: the phones see it again.
        live::heartbeat($live, $now);
        switch ($params['action']) {
            case 'next':
                $live = live::next($live, $now, $from);
                break;
            case 'reveal':
                $live = live::reveal($live, $now, $from);
                break;
            case 'mark':
                require_capability('local/oksigeniatesape:award', $context);
                $live = live::mark($live, $params['groupid'], $params['right'], $now);
                break;
            case 'finish':
                require_capability('local/oksigeniatesape:award', $context);
                if ($live->kind === 'battle' && !(int) $live->round) {
                    // Nobody has played yet: called off, without an outcome.
                    live::cancel($live, $now);
                    return [];
                }
                $live = live::finish($live, $userid, $now);
                break;
            case 'vote':
                $list = json_decode($params['options'], true);
                $live = live::vote($live, $params['text'], is_array($list) ? $list : [], $now);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        return ['live' => live::board_view($live, $now)];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'live' => structures::live_board(),
        ]);
    }
}
