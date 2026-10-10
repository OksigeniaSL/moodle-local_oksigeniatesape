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

use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Structures shared by the web services of the game.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class structures {
    /**
     * A character and, if it plays in the course, its state there.
     *
     * @param int $required VALUE_REQUIRED or VALUE_OPTIONAL.
     * @return external_single_structure
     */
    public static function character(int $required = VALUE_REQUIRED): external_single_structure {
        return new external_single_structure([
            'role' => new external_value(PARAM_ALPHA, 'protector, healer, sage or scout'),
            'nameform' => new external_value(PARAM_ALPHA, 'm or f'),
            'lineage' => new external_value(PARAM_ALPHA, 'Lineage, empty if none'),
            'nickname' => new external_value(PARAM_TEXT, 'Hero name, empty if none'),
            'appearance' => new external_value(PARAM_RAW, 'Look of the character (JSON), empty if none'),
            'saber' => new external_value(PARAM_INT, 'Knowledge'),
            'level' => new external_value(PARAM_INT, 'Level'),
            'levelinto' => new external_value(PARAM_INT, 'Knowledge gained in this level'),
            'levelneeded' => new external_value(PARAM_INT, 'Knowledge this level asks for (0 at the top)'),
            'stage' => new external_value(PARAM_INT, 'Stage of the look, 1 to 5'),
            'gems' => new external_value(PARAM_INT, 'Gems'),
            'creaturestage' => new external_value(PARAM_INT, 'Stage of the creature (0 before it hatches)'),
            'member' => new external_single_structure([
                'renown' => new external_value(PARAM_INT, 'Knowledge earned in the course'),
                'energy' => new external_value(PARAM_INT, 'Energy in the course'),
                'breath' => new external_value(PARAM_INT, 'Breath in the course'),
                'status' => new external_value(PARAM_ALPHA, 'active, fallen or paused'),
                'statusreason' => new external_value(PARAM_TEXT, 'Why, only for the student and the teachers'),
                'step' => new external_value(PARAM_INT, 'Step of the mission, 1 to 4'),
            ], 'State in the course, if it plays there', VALUE_OPTIONAL),
        ], 'Character', $required);
    }

    /**
     * An entry of the log.
     *
     * @param array $extra More fields.
     * @return external_single_structure
     */
    public static function entry(array $extra = []): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Entry'),
            'resource' => new external_value(PARAM_ALPHA, 'saber, energy, breath, gems or item'),
            'amount' => new external_value(PARAM_INT, 'Amount'),
            'capped' => new external_value(PARAM_INT, 'Left out by a cap'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Code of the reason'),
            'undone' => new external_value(PARAM_BOOL, 'Undone'),
            'time' => new external_value(PARAM_INT, 'When'),
        ] + $extra);
    }

    /**
     * The settings of a region.
     *
     * @return external_single_structure
     */
    public static function region(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'The game is on'),
            'family' => new external_value(PARAM_ALPHA, 'Family of role names'),
            'groupingid' => new external_value(PARAM_INT, 'Grouping of the teams (0: every group)'),
            'weeklycap' => new external_value(PARAM_INT, 'Weekly cap of knowledge (0: none)'),
            'dailymanualcap' => new external_value(PARAM_INT, 'Daily cap of knowledge given by hand (0: none)'),
            'dailyenergylosscap' => new external_value(PARAM_INT, 'Daily cap of energy lost (0: none)'),
            'leaderboard' => new external_value(PARAM_BOOL, 'Team ranking shown'),
            'nicknames' => new external_value(PARAM_INT, '0 Moodle names, 1 hero names from a list, 2 also their own'),
            'completionrule' => new external_value(PARAM_INT, 'Knowledge for an activity that is not a challenge'),
        ]);
    }

    /**
     * What happened to each user of a teacher's action.
     *
     * @return external_multiple_structure
     */
    public static function results(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Student'),
            'status' => new external_value(PARAM_ALPHA, 'What happened: done, merit, notyet, given, capped, paused or nocharacter'),
            'amount' => new external_value(PARAM_INT, 'What was given or taken', VALUE_DEFAULT, 0),
        ]));
    }
}
