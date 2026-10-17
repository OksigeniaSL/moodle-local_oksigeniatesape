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
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\view;

/**
 * A minigame of the mission of the course, as the current student plays it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class get_minigame extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'game' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
        ]);
    }

    /**
     * A minigame of the mission of the course, as the current student plays it.
     *
     * @param int $courseid
     * @param string $game
     * @return array
     */
    public static function execute(
        int $courseid,
        string $game
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'game' => $game,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:play', $context);
        $char = characters::get((int) $USER->id);
        $mission = missions::get($params['courseid']);
        if (!$char || !$mission || !regions::enabled($params['courseid'])) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $params['courseid']);
        $config = minigames::config($mission, $params['game'], (int) $member->step);
        if (!$config) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        return $config;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
            'type' => new external_value(
                PARAM_ALPHA,
                'nest (Defensa del nido), errors (the hunt for mistakes) or whack (Who is alive?)',
                VALUE_DEFAULT,
                'nest'
            ),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'intro' => new external_value(PARAM_TEXT, 'What to do'),
            'win' => new external_value(PARAM_TEXT, 'Text when won'),
            'lose' => new external_value(PARAM_TEXT, 'Text when lost'),
            'nets' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Net'),
                'label' => new external_value(PARAM_TEXT, 'Name'),
                'hint' => new external_value(PARAM_TEXT, 'What its animals are like'),
            ])),
            'animals' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Animal'),
                'label' => new external_value(PARAM_TEXT, 'Name'),
                'net' => new external_value(PARAM_ALPHANUMEXT, 'Its net'),
                'why' => new external_value(PARAM_TEXT, 'Why, when it is tricky'),
            ])),
            'sheets' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Sheet'),
                'title' => new external_value(PARAM_TEXT, 'What it is about'),
                'parts' => new external_multiple_structure(new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'Plain text (empty for a mistake)'),
                    'mark' => new external_value(PARAM_INT, 'Number of the mistake (-1: plain text)'),
                    'wrong' => new external_value(PARAM_TEXT, 'What the sheet says'),
                    'right' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Accepted')),
                    'why' => new external_value(PARAM_TEXT, 'Why'),
                ])),
            ]), 'The sheets of a hunt for mistakes', VALUE_DEFAULT, []),
            'things' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Thing'),
                'label' => new external_value(PARAM_TEXT, 'Name'),
                'alive' => new external_value(PARAM_BOOL, 'Whether it is alive'),
                'why' => new external_value(PARAM_TEXT, 'Why, when it is tricky'),
            ]), 'What comes out of the holes in Who is alive?', VALUE_DEFAULT, []),
            'misses' => new external_value(PARAM_INT, 'Slips a hunt for mistakes or Who is alive? allows', VALUE_DEFAULT, 0),
            'goal' => new external_value(PARAM_INT, 'Animals to catch, or mistakes to find'),
            'shield' => new external_value(PARAM_INT, 'What the shield of the nest holds'),
            'speed' => new external_value(PARAM_INT, 'Speed, 1 to 4'),
        ]);
    }
}
