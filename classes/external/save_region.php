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
use local_oksigeniatesape\local\moodlebank;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * Turns the game on or off in a course and changes its settings; what is not given keeps its value.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
            'quiet' => new external_value(PARAM_BOOL, 'No sound on the devices of the students', VALUE_DEFAULT, null, NULL_ALLOWED),
            'look' => new external_value(PARAM_ALPHA, 'Theme of the class (empty: the world)', VALUE_DEFAULT, null, NULL_ALLOWED),
            'devices' => new external_value(PARAM_BOOL, 'The class plays with a tablet each', VALUE_DEFAULT, null, NULL_ALLOWED),
            'devicemusic' => new external_value(
                PARAM_INT,
                'Music on the devices: 0 never, 1 only in battles, 2 always',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'mischief' => new external_value(PARAM_ALPHA, 'off, calm or naughty', VALUE_DEFAULT, null, NULL_ALLOWED),
            'setup' => new external_value(
                PARAM_INT,
                'Steps of the first week gone through (6: the list of the first day closed too)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'reasons' => new external_value(
                PARAM_RAW,
                'The touches with a reason, as JSON: a list of text, resource (saber, gems or breath) and amount; ' .
                    '«default» goes back to those of the plugin',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'talentson' => new external_value(
                PARAM_BOOL,
                'The talents act in the course (no: they wait, and so does the choice)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'skillson' => new external_value(
                PARAM_BOOL,
                'The named skills show to the students',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'secretsshown' => new external_value(
                PARAM_INT,
                'Secrets shown at a time in the painting of a place (0: all of them)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'sharelibrary' => new external_value(
                PARAM_BOOL,
                'Other regions of the site can visit the library of the class',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
            'qbank' => new external_value(
                PARAM_INT,
                'Category of the question bank of Moodle for the battles (0: none)',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED
            ),
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
     * @param bool|null $quiet
     * @param string|null $look
     * @param bool|null $devices
     * @param int|null $devicemusic
     * @param string|null $mischief
     * @param int|null $setup
     * @param string|null $reasons
     * @param bool|null $talentson
     * @param bool|null $skillson
     * @param int|null $secretsshown
     * @param bool|null $sharelibrary
     * @param int|null $qbank
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
        ?bool $gradebook = null,
        ?bool $quiet = null,
        ?string $look = null,
        ?bool $devices = null,
        ?int $devicemusic = null,
        ?string $mischief = null,
        ?int $setup = null,
        ?string $reasons = null,
        ?bool $talentson = null,
        ?bool $skillson = null,
        ?int $secretsshown = null,
        ?bool $sharelibrary = null,
        ?int $qbank = null
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
            'quiet' => $quiet,
            'look' => $look,
            'devices' => $devices,
            'devicemusic' => $devicemusic,
            'mischief' => $mischief,
            'setup' => $setup,
            'reasons' => $reasons,
            'talentson' => $talentson,
            'skillson' => $skillson,
            'secretsshown' => $secretsshown,
            'sharelibrary' => $sharelibrary,
            'qbank' => $qbank,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        $settings = array_filter($params, fn($value) => $value !== null);
        unset(
            $settings['courseid'],
            $settings['completionrule'],
            $settings['reasons'],
            $settings['talentson'],
            $settings['skillson'],
            $settings['secretsshown'],
            $settings['sharelibrary'],
            $settings['qbank']
        );
        if ($params['reasons'] !== null) {
            $list = $params['reasons'] === 'default' ? null : json_decode($params['reasons'], true);
            if ($params['reasons'] !== 'default' && !is_array($list)) {
                throw new \invalid_parameter_exception('The touches go as a list');
            }
            $settings['reasons'] = $list;
        }
        if ($params['completionrule'] !== null) {
            $settings['rules'] = ['completion' => $params['completionrule']];
        }
        if ($params['secretsshown'] !== null) {
            $settings['rules'] = ($settings['rules'] ?? []) + ['secrets' => max(0, min(3, $params['secretsshown']))];
        }
        if ($params['talentson'] !== null) {
            $settings['rules'] = ($settings['rules'] ?? []) + ['talents' => $params['talentson'] ? 1 : 0];
        }
        if ($params['skillson'] !== null) {
            $settings['rules'] = ($settings['rules'] ?? []) + ['skills' => $params['skillson'] ? 1 : 0];
        }
        if ($params['sharelibrary'] !== null) {
            $settings['rules'] = ($settings['rules'] ?? []) + ['library' => $params['sharelibrary'] ? 1 : 0];
        }
        if ($params['qbank'] !== null) {
            if ($params['qbank'] && !moodlebank::allowed($params['courseid'], $params['qbank'])) {
                throw new \invalid_parameter_exception('Not a question category of the course');
            }
            $settings['rules'] = ($settings['rules'] ?? []) + ['qbank' => max(0, $params['qbank'])];
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
