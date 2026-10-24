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
use local_oksigeniatesape\local\nestboards;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\untangles;

/**
 * The battle on the phone of a student: whether there is one, come in, answer.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
            'action' => new external_value(
                PARAM_ALPHA,
                'state, join, answer, strike (by turns, a lane of another engine), token, carry (by turns, a dizzy team), ' .
                    'pull or parry (by turns: answer 1 if the touch came in time), card (by turns: item, a card of the deck)'
            ),
            'round' => new external_value(PARAM_INT, 'answer: the round', VALUE_DEFAULT, 0),
            'answer' => new external_value(PARAM_INT, 'answer: index of the option', VALUE_DEFAULT, -1),
            'target' => new external_value(
                PARAM_INT,
                'token, by turns: the team the shield or the cheer goes to; carry: the dizzy team',
                VALUE_DEFAULT,
                0
            ),
            'payload' => new external_value(PARAM_RAW, 'strike, by turns: what was done in a lane (JSON)', VALUE_DEFAULT, ''),
            'cover' => new external_value(PARAM_ALPHA, 'token, by turns: the verb of a role missing in the team', VALUE_DEFAULT, ''),
            'item' => new external_value(PARAM_ALPHA, 'card, by turns: the thing the card is', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Does something in the battle of the course and says how it is going for this student.
     *
     * @param int $courseid
     * @param string $action
     * @param int $round
     * @param int $answer
     * @param int $target
     * @param string $payload
     * @param string $cover
     * @param string $item
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        int $round = 0,
        int $answer = -1,
        int $target = 0,
        string $payload = '',
        string $cover = '',
        string $item = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'round' => $round,
            'answer' => $answer,
            'target' => $target,
            'payload' => $payload,
            'cover' => $cover,
            'item' => $item,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $now = time();
        $live = $params['action'] === 'state' ? live::recent($params['courseid'], $now) :
            live::current($params['courseid'], $now);
        // The phones ask every few seconds: whether there is a battle first, and the player only then.
        $member = $live && regions::enabled($params['courseid']) ? live::player((int) $USER->id, $params['courseid'], $now) : null;
        if (!$member) {
            if ($params['action'] !== 'state') {
                throw new \moodle_exception('nobattle', 'local_oksigeniatesape');
            }
            return [];
        }
        if ($live->kind === nestboards::KIND) {
            // The nest of a team on the class screen: only its tablets, each with its nets.
            if (!nestboards::device($live, $member, $now)) {
                if ($params['action'] !== 'state') {
                    throw new \moodle_exception('nestnotnow', 'local_oksigeniatesape');
                }
                return [];
            }
            if ($params['action'] === 'join') {
                live::join($live, $member, (int) $USER->id, $now);
            } else if ($params['action'] === 'card') {
                $live = nestboards::throw($live, $member, $params['item'], $params['round'], $now);
            } else if ($params['action'] !== 'state') {
                throw new \invalid_parameter_exception('Not for the nest');
            }
            return ['live' => live::device_view($live, $member, $now) +
                ['nest' => json_encode(nestboards::device($live, $member, $now))]];
        }
        if ($live->kind === untangles::KIND) {
            // The great untangling: to come in, and the micro-challenges.
            if ($params['action'] === 'join') {
                live::join($live, $member, (int) $USER->id, $now);
            } else if ($params['action'] === 'answer') {
                $live = untangles::answer($live, $member, $params['round'], $params['answer'], $now);
            } else if ($params['action'] !== 'state') {
                throw new \invalid_parameter_exception('Not for the great untangling');
            }
            return ['live' => live::device_view($live, $member, $now) +
                ['untangle' => json_encode(untangles::device($live, $member, $now))]];
        }
        switch ($params['action']) {
            case 'state':
                break;
            case 'join':
                live::join($live, $member, (int) $USER->id, $now);
                break;
            case 'token':
                live::token($live, $member, $now, $params['target'], $params['cover']);
                break;
            case 'carry':
                live::carry($live, $member, $params['target'], $now);
                break;
            case 'answer':
                live::answer($live, $member, $params['round'], $params['answer'], $now);
                break;
            case 'strike':
                live::strike($live, $member, $params['round'], (array) (json_decode($params['payload'], true) ?: []), $now);
                break;
            case 'pull':
                live::pull($live, $member, $params['round'], $params['answer'] === 1, $now);
                break;
            case 'parry':
                live::parry($live, $member, $params['answer'] === 1, $now);
                break;
            case 'card':
                live::card($live, $member, $params['item'], $now);
                break;
            case 'tactic':
                $live = live::tactic($live, $member, $params['item'], $now);
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
