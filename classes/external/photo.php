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
use local_oksigeniatesape\local\photos;

/**
 * The photos of the classroom as stickers of the map: a team sends them, the teacher approves them, the map and the
 * class screen show them.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class photo extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'mine, upload, list or review'),
            'chapter' => new external_value(PARAM_ALPHANUMEXT, 'upload: the place', VALUE_DEFAULT, ''),
            'picture' => new external_value(PARAM_RAW, 'upload: the picture, as a data URL', VALUE_DEFAULT, ''),
            'photoid' => new external_value(PARAM_INT, 'review: the photo', VALUE_DEFAULT, 0),
            'ok' => new external_value(PARAM_BOOL, 'review: approved', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Sends, lists or reviews photos.
     *
     * @param int $courseid
     * @param string $action
     * @param string $chapter
     * @param string $picture
     * @param int $photoid
     * @param bool $ok
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        string $chapter = '',
        string $picture = '',
        int $photoid = 0,
        bool $ok = false
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid, 'action' => $action,
            'chapter' => $chapter, 'picture' => $picture, 'photoid' => $photoid, 'ok' => $ok]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        $courseid = $params['courseid'];
        $userid = (int) $USER->id;
        switch ($params['action']) {
            case 'upload':
                require_capability('local/oksigeniatesape:play', $context);
                require_capability('local/oksigeniatesape:sendphoto', $context);
                photos::upload($courseid, $userid, $params['chapter'], $params['picture']);
                // No break: it answers with the photos of the team.
            case 'mine':
                require_capability('local/oksigeniatesape:play', $context);
                $out = [];
                foreach (photos::of_team($courseid, $userid) as $code => $p) {
                    $out[] = ['id' => 0, 'chapter' => (string) $code, 'status' => $p['status'], 'url' => $p['url'],
                        'mine' => $p['mine']];
                }
                return ['photos' => $out];
            case 'review':
                require_capability('local/oksigeniatesape:award', $context);
                photos::review($courseid, $params['photoid'], $params['ok'], $userid);
                // No break: it answers with the photos of the course.
            case 'list':
                require_capability('local/oksigeniatesape:award', $context);
                return ['photos' => photos::of_course($courseid)];
        }
        throw new \invalid_parameter_exception('Unknown action');
    }

    /**
     * What it returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'photos' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'The photo (0 for a student)'),
                'team' => new external_value(PARAM_TEXT, 'The team that sent it', VALUE_DEFAULT, ''),
                'chapter' => new external_value(PARAM_ALPHANUMEXT, 'Code of the place'),
                'place' => new external_value(PARAM_TEXT, 'Name of the place', VALUE_DEFAULT, ''),
                'status' => new external_value(PARAM_ALPHA, 'pending or approved (rejected, only to its team)'),
                'url' => new external_value(PARAM_URL, 'The picture, once it can be seen', VALUE_DEFAULT, ''),
                'mine' => new external_value(PARAM_BOOL, 'Sent by this student', VALUE_DEFAULT, false),
            ])),
        ]);
    }
}
