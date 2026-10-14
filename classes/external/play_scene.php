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
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\results;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\view;

/**
 * Plays a scene with what the student did: the server decides the ending and gives what it gives.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class play_scene extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'sceneid' => new external_value(PARAM_INT, 'Scene'),
            'events' => new external_value(PARAM_RAW, 'What the student did, in order (JSON list)'),
            'start' => new external_value(PARAM_ALPHANUMEXT, 'Node where the play started (empty: the start)', VALUE_DEFAULT, ''),
            'team' => new external_value(PARAM_RAW, 'Teammates who played it too (JSON list of user ids)', VALUE_DEFAULT, ''),
            'playid' => new external_value(PARAM_ALPHANUM, 'Mark of the play, made by the device', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Plays a scene with what the student did: the server decides the ending and gives what it gives.
     *
     * @param int $courseid
     * @param int $sceneid
     * @param string $events
     * @param string $start
     * @param string $team
     * @param string $playid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sceneid,
        string $events,
        string $start = '',
        string $team = '',
        string $playid = ''
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sceneid' => $sceneid,
            'events' => $events,
            'start' => $start,
            'team' => $team,
            'playid' => $playid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $events = json_decode($params['events'], true);
        if (!is_array($events) || !array_is_list($events) || count($events) > 500) {
            throw new \invalid_parameter_exception('Events must be a JSON list');
        }
        // The teammates who played it on the same device: a short list of ids, or none.
        $team = $params['team'] === '' ? [] : json_decode($params['team'], true);
        if (!is_array($team) || !array_is_list($team) || count($team) > 12) {
            throw new \invalid_parameter_exception('Team must be a JSON list of users');
        }
        if (strlen($params['playid']) > 20) {
            throw new \invalid_parameter_exception('The mark of a play is too long');
        }
        $result = scenes::play(
            $params['courseid'],
            (int) $USER->id,
            $params['sceneid'],
            $events,
            $params['start'] === '' ? null : $params['start'],
            null,
            array_map('intval', $team),
            $params['playid']
        );
        $mission = missions::get($params['courseid']);
        $r = $result['rewards'];
        $names = function (array $codes, string $kind) use ($mission): array {
            return array_map(function ($code) use ($mission, $kind) {
                if ($kind === 'items') {
                    return (string) ($mission->data['items'][$code]['name'] ?? $code);
                }
                if ($kind === 'pages') {
                    return (string) ($mission->data['pages'][$code]['title'] ?? $code);
                }
                foreach ($mission->data['chapters'] ?? [] as $chapter) {
                    if ($chapter['code'] === $code) {
                        return missions::text($chapter['name'], $mission->skin);
                    }
                }
                return $code;
            }, $codes);
        };
        return [
            'ending' => $result['ending'],
            'outcome' => $result['outcome'],
            'retry' => $result['retry'],
            'points' => $result['points'],
            'attempt' => $result['attempt'],
            'rewards' => [
                'saber' => (int) ($r['saber'] ?? 0),
                'capped' => (int) ($r['capped'] ?? 0),
                'gems' => (int) ($r['gems'] ?? 0),
                'items' => $names($r['items'] ?? [], 'items'),
                'lanterns' => $names($r['lanterns'] ?? [], 'chapters'),
                'pages' => $names($r['pages'] ?? [], 'pages'),
                'hatched' => !empty($r['hatched']),
                'grew' => !empty($r['grew']),
                'chapter' => ($r['chapter'] ?? '') === '' ? '' : $names([$r['chapter']], 'chapters')[0],
                'classlantern' => ($r['classlantern'] ?? '') === '' ? '' : $names([$r['classlantern']], 'chapters')[0],
                'hints' => (int) ($r['hints'] ?? 0),
                'cared' => (string) ($r['cared'] ?? ''),
                'shielded' => !empty($r['shielded']),
            ],
            'teamwith' => $result['teamwith'],
            'world' => view::world($params['courseid'], (int) $USER->id),
        ];
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'ending' => new external_value(PARAM_ALPHANUMEXT, 'Ending reached'),
            'outcome' => new external_value(PARAM_ALPHA, 'success, partial or retry'),
            'retry' => new external_value(PARAM_ALPHANUMEXT, 'Node to go back to, for a retry'),
            'points' => new external_value(PARAM_INT, 'Points'),
            'attempt' => new external_value(PARAM_INT, 'Number of the play'),
            'rewards' => new external_single_structure([
                'saber' => new external_value(PARAM_INT, 'Knowledge given'),
                'capped' => new external_value(PARAM_INT, 'Knowledge left out by the weekly cap'),
                'gems' => new external_value(PARAM_INT, 'Gems given'),
                'items' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Item')),
                'lanterns' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Lantern lit')),
                'pages' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Page of the book')),
                'hatched' => new external_value(PARAM_BOOL, 'The creature hatched'),
                'grew' => new external_value(PARAM_BOOL, 'The creature grew'),
                'chapter' => new external_value(PARAM_TEXT, 'Chapter completed now'),
                'classlantern' => new external_value(PARAM_TEXT, 'Lantern of the class lit now'),
                'hints' => new external_value(PARAM_INT, 'Breath paid for hints', VALUE_DEFAULT, 0),
                'cared' => new external_value(PARAM_TEXT, 'Teammate the healer gave a breath to', VALUE_DEFAULT, ''),
                'shielded' => new external_value(PARAM_BOOL, 'The protector\'s shield stopped a mistake', VALUE_DEFAULT, false),
            ]),
            'teamwith' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Teammate'),
                'The teammates it counted for too',
                VALUE_DEFAULT,
                []
            ),
            'world' => get_world::execute_returns(),
        ]);
    }
}
