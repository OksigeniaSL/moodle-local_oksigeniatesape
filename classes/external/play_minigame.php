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
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * Plays a minigame with what the student did: the server counts it and gives what it gives, once.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class play_minigame extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'game' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
            'events' => new external_value(PARAM_RAW, 'What the student did, in order (JSON list of a and net)'),
            'playid' => new external_value(PARAM_ALPHANUM, 'Made by the device for the play: sent again, it counts once', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Plays a minigame with what the student did: the server counts it and gives what it gives, once.
     *
     * @param int $courseid
     * @param string $game
     * @param string $events
     * @param string $playid
     * @return array
     */
    public static function execute(
        int $courseid,
        string $game,
        string $events,
        string $playid = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'game' => $game,
            'events' => $events,
            'playid' => $playid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $events = json_decode($params['events'], true);
        if (!is_array($events) || !array_is_list($events) || count($events) > 100) {
            throw new \invalid_parameter_exception('Events must be a JSON list');
        }
        $result = minigames::play($params['courseid'], (int) $USER->id, $params['game'], $events, null, $params['playid']);
        $mission = missions::get($params['courseid']);
        $result['items'] = array_map(fn($code) => (string) ($mission->data['items'][$code]['name'] ?? $code), $result['items']);
        $result['world'] = view::world($params['courseid'], (int) $USER->id);
        return $result;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Won'),
            'caught' => new external_value(PARAM_INT, 'Animals caught with the right net'),
            'missed' => new external_value(PARAM_INT, 'Animals that got past'),
            'goal' => new external_value(PARAM_INT, 'Animals to catch'),
            'tries' => new external_value(PARAM_INT, 'Plays so far'),
            'first' => new external_value(PARAM_BOOL, 'Won for the first time'),
            'items' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Item got')),
            'coat' => new external_value(PARAM_BOOL, 'The creature can now be dressed'),
            'gems' => new external_value(PARAM_INT, 'Gems given the first time', VALUE_DEFAULT, 0),
            'grew' => new external_value(PARAM_BOOL, 'The creature grew (the first minigame won)', VALUE_DEFAULT, false),
            'world' => get_world::execute_returns(),
        ]);
    }
}
