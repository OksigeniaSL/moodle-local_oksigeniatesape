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
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\reserve;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * The reserve of breath of the team of the current student: propose a spending, confirm the one of a teammate, or take
 * back one's own.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class team_reserve extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'propose, confirm or cancel'),
            'kind' => new external_value(PARAM_ALPHA, 'propose: refresh or rise', VALUE_DEFAULT, ''),
            'spendid' => new external_value(PARAM_INT, 'confirm, cancel: the proposal', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Does it.
     *
     * @param int $courseid
     * @param string $action
     * @param string $kind
     * @param int $spendid
     * @return array The reserve.
     */
    public static function execute(int $courseid, string $action, string $kind = '', int $spendid = 0): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'kind' => $kind, 'spendid' => $spendid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        $team = teams::of($params['courseid'], (int) $USER->id);
        if (!$char || !$team || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('noteam', 'local_oksigeniatesape');
        }
        switch ($params['action']) {
            case 'propose':
                reserve::propose($params['courseid'], $char, $params['kind']);
                break;
            case 'confirm':
                reserve::confirm($params['courseid'], $char, $params['spendid']);
                break;
            case 'cancel':
                reserve::cancel($params['courseid'], $char, $params['spendid']);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        // The names of the team, as its members see them.
        $region = regions::get($params['courseid']);
        $names = [];
        foreach (groups_get_members($team->id, 'u.*') as $user) {
            $names[(int) $user->id] = view::display_name($user, characters::get((int) $user->id), $region);
        }
        return reserve::view($params['courseid'], (int) $team->id, $char, $names);
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::reserve();
    }
}
