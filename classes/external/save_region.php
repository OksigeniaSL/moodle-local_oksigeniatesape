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
use local_oksigeniatesape\local\classroom;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * Turns the game on or off in a course and changes its settings; what is not given keeps its value.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class save_region extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'enabled' => new external_value(PARAM_BOOL, 'The game is on', VALUE_DEFAULT, null, NULL_ALLOWED),
            'family' => new external_value(PARAM_ALPHA, 'Family of role names', VALUE_DEFAULT, null, NULL_ALLOWED),
            'groupingid' => new external_value(PARAM_INT, 'Grouping of the teams', VALUE_DEFAULT, null, NULL_ALLOWED),
            'weeklycap' => new external_value(PARAM_INT, 'Weekly cap of knowledge', VALUE_DEFAULT, null, NULL_ALLOWED),
            'dailymanualcap' => new external_value(PARAM_INT, 'Daily cap given by hand', VALUE_DEFAULT, null, NULL_ALLOWED),
            'dailyenergylosscap' => new external_value(
                PARAM_INT,
                'Daily cap of energy lost',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'leaderboard' => new external_value(PARAM_BOOL, 'Team ranking shown', VALUE_DEFAULT, null, NULL_ALLOWED),
            'nicknames' => new external_value(PARAM_INT, 'Hero names', VALUE_DEFAULT, null, NULL_ALLOWED),
            'completionrule' => new external_value(
                PARAM_INT,
                'Knowledge for an activity that is not a challenge',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'timezone' => new external_value(PARAM_TEXT, 'Time zone of the days (99: the site)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'portal' => new external_value(PARAM_ALPHA, 'Cover of the course page', VALUE_DEFAULT, null, NULL_ALLOWED),
            'hideacts' => new external_value(PARAM_BOOL, 'Activities only inside the valley', VALUE_DEFAULT, null, NULL_ALLOWED),
            'gradebook' => new external_value(PARAM_BOOL, 'Ratings to the gradebook', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Turns the game on or off in a course and changes its settings; what is not given keeps its value.
     *
     * @param int $courseid
     * @param bool|null $enabled
     * @param string|null $family
     * @param int|null $groupingid
     * @param int|null $weeklycap
     * @param int|null $dailymanualcap
     * @param int|null $dailyenergylosscap
     * @param bool|null $leaderboard
     * @param int|null $nicknames
     * @param int|null $completionrule
     * @param string|null $timezone
     * @param string|null $portal
     * @param bool|null $hideacts
     * @param bool|null $gradebook
     * @return array
     */
    public static function execute(
        int $courseid,
        ?bool $enabled = null,
        ?string $family = null,
        ?int $groupingid = null,
        ?int $weeklycap = null,
        ?int $dailymanualcap = null,
        ?int $dailyenergylosscap = null,
        ?bool $leaderboard = null,
        ?int $nicknames = null,
        ?int $completionrule = null,
        ?string $timezone = null,
        ?string $portal = null,
        ?bool $hideacts = null,
        ?bool $gradebook = null
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'enabled' => $enabled,
            'family' => $family,
            'groupingid' => $groupingid,
            'weeklycap' => $weeklycap,
            'dailymanualcap' => $dailymanualcap,
            'dailyenergylosscap' => $dailyenergylosscap,
            'leaderboard' => $leaderboard,
            'nicknames' => $nicknames,
            'completionrule' => $completionrule,
            'timezone' => $timezone,
            'portal' => $portal,
            'hideacts' => $hideacts,
            'gradebook' => $gradebook,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        $settings = array_filter($params, fn($value) => $value !== null);
        unset($settings['courseid'], $settings['completionrule']);
        if ($params['completionrule'] !== null) {
            $settings['rules'] = ['completion' => $params['completionrule']];
        }
        if (!empty($settings['groupingid'])) {
            $grouping = groups_get_grouping($settings['groupingid']);
            if (!$grouping || (int) $grouping->courseid !== $params['courseid']) {
                throw new \invalid_parameter_exception('Unknown grouping');
            }
        }
        return view::region(regions::save($params['courseid'], $settings));
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::region();
    }
}
