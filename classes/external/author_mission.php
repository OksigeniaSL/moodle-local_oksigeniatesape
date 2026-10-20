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
use local_oksigeniatesape\local\authoring;
use local_oksigeniatesape\local\missions;

/**
 * The teacher makes a mission from scratch: the drafts of the course, a new one, the draft to change it, what is still
 * missing in it, and making it the mission of the course. Drafts go as JSON: they are many texts, of many shapes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class author_mission extends external_api {
    /** @var string[] What can be done. */
    private const ACTIONS = ['list', 'create', 'get', 'save', 'publish', 'delete', 'preview', 'duplicate', 'propose'];

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(PARAM_ALPHA, 'list, create, get, save, publish or delete'),
            'code' => new external_value(PARAM_ALPHANUM, 'Code of the draft', VALUE_DEFAULT, ''),
            'value' => new external_value(PARAM_RAW, 'The title of a new one, or the draft in JSON', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Sees or changes the drafts of the course.
     *
     * @param int $courseid
     * @param string $action
     * @param string $code
     * @param string $value
     * @return array json
     */
    public static function execute(int $courseid, string $action, string $code = '', string $value = ''): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'code' => $code,
            'value' => $value,
        ]);
        $courseid = $params['courseid'];
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:manageregion', $context);
        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \invalid_parameter_exception('Unknown action: ' . $params['action']);
        }
        $code = $params['code'];
        if ($params['action'] === 'list') {
            return ['json' => json_encode(self::list($courseid))];
        }
        if ($params['action'] === 'create') {
            $code = authoring::create($courseid, $params['value'])->code;
        } else if (!$DB->record_exists('local_oksigeniatesape_own', ['courseid' => $courseid, 'code' => $code])) {
            throw new \invalid_parameter_exception('Not a draft of this course');
        }
        if ($params['action'] === 'preview') {
            return ['json' => json_encode(authoring::preview($courseid, $code, clean_param($params['value'], PARAM_ALPHANUMEXT)))];
        }
        if ($params['action'] === 'propose') {
            require_capability('local/oksigeniatesape:aidraft', $context);
            // The value: the code of the scene, a line break and what the teacher wants to happen in it.
            [$scene, $ask] = array_pad(explode("\n", $params['value'], 2), 2, '');
            $proposal = authoring::propose($courseid, $code, clean_param($scene, PARAM_ALPHANUMEXT), $ask, (int) $USER->id);
            return ['json' => json_encode($proposal)];
        }
        if ($params['action'] === 'duplicate') {
            authoring::duplicate($courseid, $code);
            return ['json' => json_encode(self::list($courseid))];
        }
        if ($params['action'] === 'save') {
            $draft = json_decode($params['value'], true);
            authoring::save($courseid, $code, is_array($draft) ? $draft : []);
        } else if ($params['action'] === 'publish') {
            require_capability('moodle/course:manageactivities', $context);
            authoring::publish($courseid, $code);
        } else if ($params['action'] === 'delete') {
            $current = missions::get($courseid);
            $mission = $DB->get_field('local_oksigeniatesape_mission', 'id', ['courseid' => $courseid, 'code' => $code]);
            if ($mission || ($current && $current->code === $code)) {
                // Played in the course already: it stays, so that nothing of the students is lost.
                throw new \moodle_exception('authoring_played', 'local_oksigeniatesape');
            }
            $DB->delete_records('local_oksigeniatesape_own', ['courseid' => $courseid, 'code' => $code]);
            return ['json' => json_encode(self::list($courseid))];
        }
        return ['json' => json_encode(self::draft($courseid, $code))];
    }

    /**
     * The drafts of a course, for the list.
     *
     * @param int $courseid
     * @return array
     */
    private static function list(int $courseid): array {
        $current = missions::get($courseid);
        return array_map(fn($d) => ['code' => $d->code, 'title' => (string) ($d->data['title'] ?? ''),
            'version' => (int) $d->version, 'current' => $current && $current->code === $d->code,
            'timemodified' => (int) $d->timemodified], authoring::drafts($courseid));
    }

    /**
     * A draft with what the workshop needs around it: what is missing, the speakers of the world and the limits.
     *
     * @param int $courseid
     * @param string $code
     * @return array
     */
    private static function draft(int $courseid, string $code): array {
        global $USER;
        foreach (authoring::drafts($courseid) as $record) {
            if ($record->code !== $code) {
                continue;
            }
            $problems = array_map(
                fn($p) => get_string($p['key'], 'local_oksigeniatesape', $p['a']),
                authoring::check($record->data)
            );
            $advice = array_map(
                fn($p) => get_string($p['key'], 'local_oksigeniatesape', $p['a']),
                authoring::advice($record->data)
            );
            $speakers = [];
            foreach (missions::packages()['livingthings']['speakers'] ?? [] as $speaker => $name) {
                $speakers[] = ['code' => $speaker, 'name' => missions::text($name, authoring::WORLD)];
            }
            $current = missions::get($courseid);
            // The draft by the AI has its own permission: the button shows only to who has it.
            $ai = \local_oksigeniatesape\local\nextclass::status($courseid, (int) $USER->id);
            $context = \context_course::instance($courseid);
            $ai['canuse'] = !empty($ai['canuse']) && has_capability('local/oksigeniatesape:aidraft', $context);
            return ['code' => $code, 'version' => (int) $record->version, 'draft' => $record->data,
                'ai' => $ai,
                'problems' => $problems, 'advice' => $advice, 'speakers' => $speakers, 'current' => $current && $current->code === $code,
                'limits' => ['minplaces' => authoring::MIN_PLACES, 'maxplaces' => authoring::MAX_PLACES,
                    'maxobjectives' => authoring::MAX_OBJECTIVES, 'maxmoments' => authoring::MAX_MOMENTS,
                    'minquestions' => authoring::MIN_QUESTIONS]];
        }
        throw new \invalid_parameter_exception('Not a draft of this course');
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'json' => new external_value(PARAM_RAW, 'The drafts, or a draft with what is missing in it'),
        ]);
    }
}
