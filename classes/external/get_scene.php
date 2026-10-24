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
use local_oksigeniatesape\local\edits;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\results;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\talents;
use local_oksigeniatesape\local\view;

/**
 * A scene of the mission of the course, to play it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_scene extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'sceneid' => new external_value(PARAM_INT, 'Scene'),
        ]);
    }

    /**
     * A scene of the mission of the course, to play it.
     *
     * @param int $courseid
     * @param int $sceneid
     * @return array
     */
    public static function execute(
        int $courseid,
        int $sceneid
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sceneid' => $sceneid,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        if (!has_capability('local/oksigeniatesape:play', $context)) {
            // The class screen plays scenes with the whole class.
            require_capability('local/oksigeniatesape:runclassscreen', $context);
        }
        $scene = scenes::get($params['courseid'], $params['sceneid']);
        $mission = missions::get($params['courseid']);
        $speakers = [];
        foreach ($mission->data['speakers'] ?? [] as $code => $name) {
            $speakers[$code] = missions::text($name, $mission->skin);
        }
        $items = [];
        foreach ($mission->data['items'] ?? [] as $code => $item) {
            $items[$code] = (string) ($item['name'] ?? $code);
        }
        // What the student brings: flags of other scenes, their role and the breath they have for hints.
        $flags = [];
        $role = '';
        $breath = 0;
        $aided = false;
        $char = characters::get((int) $USER->id);
        if ($char && has_capability('local/oksigeniatesape:play', $context)) {
            // The memory of the expedition of the scene: a past one, in the free expedition.
            [, , $flags, $role] = scenes::context($char, $params['courseid'], (int) $scene->missionid);
            $member = engine::member($char, $params['courseid']);
            $breath = (int) $member->breath;
            $aided = scenes::aided($member, $scene);
        } else {
            // On the class screen, the memory of the class.
            $flags = scenes::class_flags($params['courseid'], (int) $mission->id);
        }
        return [
            'id' => (int) $scene->id,
            // With the changes of the teacher of the course on it, if there are any.
            'definition' => empty(edits::of($params['courseid'], (string) $mission->code)[$scene->code]) ? $scene->definition :
                json_encode($scene->data),
            'skin' => $mission->skin,
            // The place of the scene's chapter, for its picture when a node has none of its own.
            'place' => self::place($mission, (string) $DB->get_field(
                'local_oksigeniatesape_chapter',
                'code',
                ['id' => $scene->chapterid]
            )),
            'variant' => $mission->variant,
            'speakers' => json_encode($speakers),
            'items' => json_encode($items),
            'flags' => json_encode($flags),
            'role' => $role,
            'breath' => $breath,
            'aided' => $aided,
            // «Hints of the house»: the first two hints free, for the sage who has it.
            'househints' => $char && $role === 'sage' && talents::has((int) $char->id, 'househints', $params['courseid']),
            // The mistakes the shield of the protector stops in a play: two with «Long shield».
            'shields' => $char ? scenes::shields($char, $params['courseid']) : 0,
            'pictures' => json_encode((object) self::pictures($mission, $scene->data)),
        ];
    }

    /**
     * The drawings of the things to sort of a scene that are in the catalog of the mission (by their id, or by `thing`):
     * the same drawing as in the minigames and the encounters.
     *
     * @param \stdClass $mission With data.
     * @param array $definition Of the scene.
     * @return array Thing of the scene => key of its drawing.
     */
    private static function pictures(\stdClass $mission, array $definition): array {
        $pictures = [];
        foreach ($definition['nodes'] ?? [] as $node) {
            foreach (($node['type'] ?? '') === 'classify' ? $node['items'] ?? [] : [] as $item) {
                $id = (string) ($item['thing'] ?? $item['id'] ?? '');
                if ($id !== '' && isset($mission->data['things'][$id])) {
                    $pictures[(string) $item['id']] = minigames::picture($mission, $id, true);
                }
            }
        }
        return $pictures;
    }

    /**
     * The place whose pictures a scene uses: its chapter, or the landscape a chapter of a teacher's mission takes.
     *
     * @param \stdClass $mission With data.
     * @param string $chapter
     * @return string
     */
    private static function place(\stdClass $mission, string $chapter): string {
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $chapter) {
                return (string) ($info['art'] ?? $chapter);
            }
        }
        return $chapter;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Scene'),
            'definition' => new external_value(PARAM_RAW, 'The scene (JSON)'),
            'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin of the mission'),
            'place' => new external_value(PARAM_ALPHANUMEXT, 'Place of the chapter of the scene', VALUE_DEFAULT, ''),
            'pictures' => new external_value(PARAM_RAW, 'Things to sort in the catalog: thing => drawing (JSON)', VALUE_DEFAULT, '{}'),
            'variant' => new external_value(PARAM_ALPHANUMEXT, 'Local examples'),
            'speakers' => new external_value(PARAM_RAW, 'Names of who speaks (JSON)'),
            'items' => new external_value(PARAM_RAW, 'Names of the items (JSON)'),
            'flags' => new external_value(PARAM_RAW, 'Flags of the mission the student carries (JSON)', VALUE_DEFAULT, '[]'),
            'role' => new external_value(PARAM_ALPHA, 'Role of the student (empty on the class screen)', VALUE_DEFAULT, ''),
            'breath' => new external_value(PARAM_INT, 'Breath of the student, for the hints', VALUE_DEFAULT, 0),
            'aided' => new external_value(PARAM_BOOL, 'With the aids of the first steps', VALUE_DEFAULT, false),
            'househints' => new external_value(PARAM_BOOL, 'The first two hints free, by a talent', VALUE_DEFAULT, false),
            'shields' => new external_value(PARAM_INT, 'Mistakes the shield of the protector stops in a play', VALUE_DEFAULT, 0),
        ]);
    }
}
