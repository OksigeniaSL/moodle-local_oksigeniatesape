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
use local_oksigeniatesape\local\games;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * The games of a course, for its teachers: the one of the course and one more for each group that plays apart (5th A
 * and 5th B in the same course). Lists them, opens one for a group, or closes one with all it had.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manage_games extends external_api {
    /** @var string[] What can be done. */
    private const ACTIONS = ['list', 'create', 'delete'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Any game of the course'),
            'action' => new external_value(PARAM_ALPHA, 'list, create or delete'),
            'groupid' => new external_value(PARAM_INT, 'To create: the group that plays apart', VALUE_DEFAULT, 0),
            'name' => new external_value(PARAM_TEXT, 'To create: its name (that of the group if empty)', VALUE_DEFAULT, ''),
            'game' => new external_value(PARAM_INT, 'To delete: the game more', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Lists the games of the course, opens one or closes one.
     *
     * @param int $courseid
     * @param string $action
     * @param int $groupid
     * @param string $name
     * @param int $game
     * @return array
     */
    public static function execute(int $courseid, string $action, int $groupid = 0, string $name = '', int $game = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'groupid' => $groupid,
            'name' => $name,
            'game' => $game,
        ]);
        $context = games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
        $course = games::course($params['courseid']);
        $made = 0;
        if ($params['action'] === 'create') {
            $made = games::create($course, $params['groupid'], $params['name']);
        } else if ($params['action'] === 'delete') {
            if ($params['game'] >= 0 || games::course($params['game']) !== $course) {
                throw new \invalid_parameter_exception('Not a game more of the course');
            }
            games::delete($params['game']);
        }
        $list = [];
        foreach (games::menu($course) as $item) {
            $list[] = $item + [
                'groupname' => $item['group'] ? (string) groups_get_group_name($item['group']) : '',
                'students' => count(view::students($item['key'])),
                'enabled' => regions::enabled($item['key']),
            ];
        }
        $free = array_map(fn($g) => ['id' => (int) $g->id, 'name' => format_string($g->name),
            'members' => count(groups_get_members((int) $g->id, 'u.id'))], games::free_groups($course));
        return ['games' => $list, 'groups' => $free, 'made' => $made];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'games' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_INT, 'The game: the course, or minus the id of a game more'),
                'name' => new external_value(PARAM_TEXT, 'Its name; empty for the game of the course'),
                'group' => new external_value(PARAM_INT, 'The group that plays it; 0 for the game of the course'),
                'groupname' => new external_value(PARAM_TEXT, 'The name of the group'),
                'students' => new external_value(PARAM_INT, 'Students who play it'),
                'enabled' => new external_value(PARAM_BOOL, 'The game is on'),
            ])),
            'groups' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Its name'),
                'members' => new external_value(PARAM_INT, 'Its members'),
            ]), 'The groups of the course that can play apart'),
            'made' => new external_value(PARAM_INT, 'The key of the game just opened, or 0'),
        ]);
    }
}
