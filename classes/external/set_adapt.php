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
use local_oksigeniatesape\local\adapt;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\view;

/**
 * Keeps what the game adapts for a student: the adaptations the teacher marks, never a diagnosis.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class set_adapt extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userid' => new external_value(PARAM_INT, 'Student (0: those of userids)', VALUE_DEFAULT, 0),
            'adapt' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'Adaptation'),
                'Adaptations',
                VALUE_DEFAULT,
                []
            ),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Student'),
                'Several students at once, with a profile',
                VALUE_DEFAULT,
                []
            ),
            'profile' => new external_value(
                PARAM_ALPHA,
                'A profile of one touch (reading, calm or sight): what it marks is added to what each one has',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Keeps what the game adapts for a student: the adaptations the teacher marks, never a diagnosis.
     *
     * @param int $courseid
     * @param int $userid
     * @param array $adapt
     * @param int[] $userids
     * @param string $profile
     * @return array
     */
    public static function execute(
        int $courseid,
        int $userid,
        array $adapt = [],
        array $userids = [],
        string $profile = ''
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'adapt' => $adapt,
            'userids' => $userids,
            'profile' => $profile,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if ($params['profile'] !== '') {
            return self::profile($params['courseid'], $params['userids'] ?: [$params['userid']], $params['profile']);
        }
        view::require_students($params['courseid'], [$params['userid']]);
        $char = characters::get($params['userid']);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        return ['adapt' => adapt::save($member, $params['adapt'])];
    }

    /**
     * A profile of one touch for one or several students: what it marks is added to what the teacher marked for each.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param string $profile
     * @return array The adaptations of the first one, and of each one who has a character.
     */
    private static function profile(int $courseid, array $userids, string $profile): array {
        if (!isset(adapt::PROFILES[$profile])) {
            throw new \invalid_parameter_exception('Unknown profile');
        }
        $userids = array_values(array_unique(array_map('intval', $userids)));
        if (!$userids || count($userids) > 100) {
            throw new \invalid_parameter_exception('Between 1 and 100 students');
        }
        view::require_students($courseid, $userids);
        $students = [];
        foreach ($userids as $userid) {
            $char = characters::get($userid);
            if (!$char) {
                continue;
            }
            $member = engine::member($char, $courseid);
            $list = adapt::save($member, array_merge(adapt::teacher($member), adapt::PROFILES[$profile]));
            $students[] = ['userid' => $userid, 'adapt' => $list];
        }
        return ['adapt' => $students[0]['adapt'] ?? [], 'students' => $students];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'adapt' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Adaptation')),
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Student'),
                'adapt' => new external_multiple_structure(new external_value(PARAM_ALPHA, 'Adaptation')),
            ]), 'With a profile: each student who has a character, and how they are left', VALUE_OPTIONAL),
        ]);
    }
}
