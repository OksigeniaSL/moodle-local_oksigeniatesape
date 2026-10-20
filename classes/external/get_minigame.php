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
use local_oksigeniatesape\local\creatures;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\equipment;
use local_oksigeniatesape\local\ideas;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\talents;
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
        global $DB, $USER;
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
        // In Guess who, the being that hides depends on the plays kept.
        $plays = $DB->count_records('local_oksigeniatesape_minigame', ['charid' => $char->id, 'courseid' => $params['courseid'],
            'game' => $params['game']]);
        $config = minigames::config(
            $mission,
            $params['game'],
            (int) $member->step,
            (string) $char->role,
            talents::acting($char, $params['courseid']),
            creatures::sniffs($char, $params['courseid']),
            equipment::worn($char, $params['courseid']),
            (int) $char->id,
            $plays
        );
        if (!$config) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        // Won already, and the gems of the day for winning again given: training, with the streak alone.
        $config['training'] = minigames::training($char, $mission, $params['game']);
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
                'nest (Defensa del nido), errors (the hunt for mistakes), whack (Who is alive?), venn (the two circles), whoami ' .
                    'or guesswho',
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
                ]), 'Of a hunt for mistakes', VALUE_DEFAULT, []),
                'picture' => new external_value(
                    PARAM_RAW_TRIMMED,
                    'Of a hunt for mistakes: the drawing of the being it talks about',
                    VALUE_DEFAULT,
                    ''
                ),
                'groups' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Name of a circle'),
                    'The two circles',
                    VALUE_DEFAULT,
                    []
                ),
                'items' => new external_multiple_structure(new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Thing'),
                    'zone' => new external_value(PARAM_ALPHA, 'a, b or ab (both)'),
                    'why' => new external_value(PARAM_TEXT, 'Why, when it is tricky'),
                ]), 'Of the two circles', VALUE_DEFAULT, []),
            ]), 'The sheets of a hunt for mistakes or of the two circles', VALUE_DEFAULT, []),
            'things' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Thing'),
                'label' => new external_value(PARAM_TEXT, 'Name'),
                'alive' => new external_value(PARAM_BOOL, 'Whether it is alive'),
                'why' => new external_value(PARAM_TEXT, 'Why, when it is tricky'),
                'picture' => new external_value(PARAM_RAW_TRIMMED, 'Key of its drawing in the art pack', VALUE_DEFAULT, ''),
            ]), 'What comes out of the holes in Who is alive?', VALUE_DEFAULT, []),
            'riddles' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Riddle'),
                'answer' => new external_value(PARAM_TEXT, 'Who it is'),
                'clues' => new external_multiple_structure(new external_value(PARAM_TEXT, 'A clue, hardest first')),
                'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'An answer to choose')),
            ]), 'The shadows of Who am I?', VALUE_DEFAULT, []),
            'cards' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Being of the catalog'),
                'label' => new external_value(PARAM_TEXT, 'Name'),
                'name' => new external_value(PARAM_TEXT, 'Name with its article'),
                'picture' => new external_value(PARAM_RAW_TRIMMED, 'Key of its drawing in the art pack'),
            ]), 'The beings of Guess who', VALUE_DEFAULT, []),
            'questions' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_ALPHANUMEXT, 'Question'),
                'trait' => new external_value(PARAM_ALPHA, 'What it asks about: kind, cover, legs, breath, born, eats, habitat'),
                'text' => new external_value(PARAM_TEXT, 'The question'),
                'yes' => new external_multiple_structure(new external_value(PARAM_ALPHANUMEXT, 'A being that answers yes')),
            ]), 'The questions of Guess who', VALUE_DEFAULT, []),
            'secret' => new external_value(PARAM_ALPHANUMEXT, 'Guess who: the being that hides', VALUE_DEFAULT, ''),
            'seed' => new external_value(PARAM_INT, 'Guess who: plays kept when this one began', VALUE_DEFAULT, 0),
            'golden' => new external_value(PARAM_INT, 'Guess who: questions at most for a golden card', VALUE_DEFAULT, 0),
            'training' => new external_value(
                PARAM_BOOL,
                'Won already and no gems left today for winning again',
                VALUE_DEFAULT,
                false
            ),
            'misses' => new external_value(PARAM_INT, 'Slips or wrong guesses a game allows', VALUE_DEFAULT, 0),
            'goal' => new external_value(PARAM_INT, 'Animals to catch, or mistakes to find'),
            'shield' => new external_value(PARAM_INT, 'What the shield of the nest holds'),
            'speed' => new external_value(PARAM_INT, 'Speed, 1 to 4'),
            'scene' => new external_single_structure([
                'embrollo' => new external_value(PARAM_TEXT, 'What the Embrollo did, or empty'),
                'creature' => new external_value(PARAM_TEXT, 'How the creature takes it, with {{creature}}, or empty'),
            ], 'The two lines at the start'),
            'passive' => new external_value(PARAM_ALPHA, 'Role whose passive the student has, or empty'),
            'streak' => new external_value(PARAM_INT, 'Right answers in a row for the healer to get a slip back'),
            'looks' => new external_value(PARAM_INT, 'Looks of the sage in a play', VALUE_DEFAULT, 0),
            'ahead' => new external_value(PARAM_INT, 'Things the scout sees coming', VALUE_DEFAULT, 0),
            'extra' => new external_value(PARAM_INT, 'Slips more the protector holds', VALUE_DEFAULT, 0),
            'sniffs' => new external_value(PARAM_INT, 'Times the creature points out a case in a play', VALUE_DEFAULT, 0),
            'lens' => new external_value(PARAM_INT, 'Looks more, by the magnifier in the hand', VALUE_DEFAULT, 0),
            'packnet' => new external_value(PARAM_INT, 'Animals that slip the net in the pack catches', VALUE_DEFAULT, 0),
        ]);
    }
}
