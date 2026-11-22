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
use local_oksigeniatesape\local\climb;

/**
 * The current student does something on the climb outside its scenes: chooses their hero, lights the lantern of the
 * place, names their creature.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class climb_act extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'hero, light, name, paint or skin'),
            'role' => new external_value(PARAM_ALPHA, 'hero: protector, healer, sage or scout', VALUE_DEFAULT, ''),
            'nameform' => new external_value(PARAM_ALPHA, 'hero: m or f', VALUE_DEFAULT, ''),
            'name' => new external_value(PARAM_TEXT, 'name: the name of the creature (empty: none)', VALUE_DEFAULT, ''),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'paint: a paint of the market (empty: none)', VALUE_DEFAULT, ''),
            'skin' => new external_value(PARAM_RAW_TRIMMED, 'skin: a skin of the game', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Does it.
     *
     * @param int $courseid
     * @param string $action
     * @param string $role
     * @param string $nameform
     * @param string $name
     * @param string $code
     * @param string $skin
     * @return array The state, as get_climb returns it.
     */
    public static function execute(
        int $courseid,
        string $action,
        string $role = '',
        string $nameform = '',
        string $name = '',
        string $code = '',
        string $skin = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'role' => $role, 'nameform' => $nameform, 'name' => $name, 'code' => $code, 'skin' => $skin]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $userid = (int) $USER->id;
        switch ($params['action']) {
            case 'hero':
                $state = climb::hero($params['courseid'], $userid, $params['role'], $params['nameform']);
                break;
            case 'light':
                $state = climb::light($params['courseid'], $userid);
                break;
            case 'name':
                $state = climb::name($params['courseid'], $userid, $params['name']);
                break;
            case 'paint':
                $state = climb::paint($params['courseid'], $userid, $params['code']);
                break;
            case 'skin':
                $state = climb::skin($params['courseid'], $userid, $params['skin']);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
        return get_climb::pack($state);
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return get_climb::execute_returns();
    }
}
