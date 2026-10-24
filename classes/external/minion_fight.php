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
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\fights;

/**
 * The fight of the creature of the current student with a minion of the Embrollo on the road.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class minion_fight extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'start, sniff, net, strike or dodge'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'start: the minion', VALUE_DEFAULT, ''),
            'fightid' => new external_value(PARAM_INT, 'The fight', VALUE_DEFAULT, 0),
            'value' => new external_value(PARAM_INT, 'The net, the option or the place, as shown', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Starts the fight or plays a step of it.
     *
     * @param int $courseid
     * @param string $action
     * @param string $code
     * @param int $fightid
     * @param int $value
     * @return array The fight.
     */
    public static function execute(int $courseid, string $action, string $code = '', int $fightid = 0, int $value = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'code' => $code, 'fightid' => $fightid, 'value' => $value]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        $fight = $params['action'] === 'start' ? fights::start($char, $member, $params['code']) :
            fights::act($char, $member, $params['fightid'], $params['action'], $params['value']);
        return fights::view($fight);
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The fight'),
            'minion' => new external_value(PARAM_ALPHANUMEXT, 'The minion'),
            'name' => new external_value(PARAM_TEXT, 'Its name'),
            'claim' => new external_value(PARAM_TEXT, 'What it claims'),
            'laugh' => new external_value(PARAM_TEXT, 'How it laughs'),
            'picture' => new external_value(PARAM_RAW, 'Key of its drawing in the art pack'),
            'phase' => new external_value(PARAM_ALPHA, 'net, strike, dodge or end'),
            'threads' => new external_value(PARAM_INT, 'Threads of the minion left'),
            'maxthreads' => new external_value(PARAM_INT, 'Its threads'),
            'bonds' => new external_value(PARAM_INT, 'Bonds of the creature left'),
            'maxbonds' => new external_value(PARAM_INT, 'Its bonds'),
            'turn' => new external_value(PARAM_INT, 'The turn, from 0'),
            'turns' => new external_value(PARAM_INT, 'Turns before it escapes'),
            'moves' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'sniff, leap or double'),
                'What the creature knows how to do'
            ),
            'cansniff' => new external_value(PARAM_BOOL, 'The creature can sniff now'),
            'tangled' => new external_value(PARAM_BOOL, 'The creature is tangled: the next strike cuts one at most'),
            'nets' => new external_multiple_structure(new external_single_structure([
                'text' => new external_value(PARAM_TEXT, 'The idea of the net'),
                'struck' => new external_value(PARAM_BOOL, 'Struck out by the sniff'),
            ])),
            'net' => new external_value(PARAM_TEXT, 'The net chosen this turn'),
            'question' => new external_value(PARAM_TEXT, 'The question to strike'),
            'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option')),
            'aim' => new external_value(PARAM_INT, 'Where the thread falls (-1: not now)'),
            'holes' => new external_value(PARAM_INT, 'Places for the creature'),
            'dodge' => new external_value(PARAM_INT, 'Seconds to move it away'),
            'last' => new external_single_structure([
                'kind' => new external_value(PARAM_ALPHA, 'hit, weak, miss, dodged, leapt or caught'),
                'cut' => new external_value(PARAM_INT, 'Threads cut'),
                'goodnet' => new external_value(PARAM_BOOL, 'The net was the right one'),
                'correct' => new external_value(PARAM_INT, 'The right option'),
                'chosen' => new external_value(PARAM_INT, 'What was chosen'),
                'why' => new external_value(PARAM_TEXT, 'Why'),
                'aim' => new external_value(PARAM_INT, 'Where the thread fell'),
            ]),
            'outcome' => new external_value(PARAM_ALPHA, 'won or escaped, at the end'),
            'gems' => new external_value(PARAM_INT, 'Gems given'),
            'weakness' => new external_value(PARAM_TEXT, 'What undoes it, once won'),
        ]);
    }
}
