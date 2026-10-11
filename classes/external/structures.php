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
            'creature' => new external_value(PARAM_RAW, 'Traits of the creature (JSON), empty before it hatches'),
            'member' => new external_single_structure([
                'renown' => new external_value(PARAM_INT, 'Knowledge earned in the course'),
                'energy' => new external_value(PARAM_INT, 'Energy in the course (-1: not shown to this classmate)'),
                'breath' => new external_value(PARAM_INT, 'Breath in the course'),
                'status' => new external_value(PARAM_ALPHA, 'active, fallen or paused'),
                'statusreason' => new external_value(PARAM_TEXT, 'Why, only for the student and the teachers'),
                'step' => new external_value(PARAM_INT, 'Step of the mission, 1 to 4'),
            ], 'State in the course, if it plays there', VALUE_OPTIONAL),
        ], 'Character', $required);
    }

    /**
     * The mission of a course as a student sees it.
     *
     * @return external_single_structure
     */
    public static function mission(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Mission'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'subtitle' => new external_value(PARAM_TEXT, 'Subtitle'),
            'prologue' => new external_value(PARAM_TEXT, 'Prologue'),
            'book' => new external_value(PARAM_TEXT, 'Name of the book of the mission'),
            'skin' => new external_value(PARAM_ALPHANUMEXT, 'Skin'),
            'variant' => new external_value(PARAM_ALPHANUMEXT, 'Local examples'),
            'pagecount' => new external_value(PARAM_INT, 'Pages of the book in the mission'),
            'chapters' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Chapter'),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'intro' => new external_value(PARAM_TEXT, 'Introduction'),
                'x' => new external_value(PARAM_INT, 'Place on the map'),
                'y' => new external_value(PARAM_INT, 'Place on the map'),
                'lit' => new external_value(PARAM_BOOL, 'Lantern of the class lit'),
                'mine' => new external_value(PARAM_BOOL, 'Lantern of the student lit'),
                'done' => new external_value(PARAM_BOOL, 'Scenes of the student done'),
                'challengeid' => new external_value(PARAM_INT, 'Classroom challenge of the chapter (0: none)'),
                'games' => new external_multiple_structure(new external_single_structure([
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'done' => new external_value(PARAM_BOOL, 'Won'),
                ])),
                'scenes' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Scene'),
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'minutes' => new external_value(PARAM_INT, 'Minutes, more or less'),
                    'done' => new external_value(PARAM_BOOL, 'Done'),
                    'better' => new external_value(PARAM_BOOL, 'Done with a partial ending', VALUE_DEFAULT, false),
                    'attempts' => new external_value(PARAM_INT, 'Plays'),
                ])),
            ])),
            'pages' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'title' => new external_value(PARAM_TEXT, 'Title'),
                'text' => new external_value(PARAM_TEXT, 'Text'),
            ])),
        ], 'Mission of the course', VALUE_OPTIONAL);
    }

    /**
     * How a team is doing this week.
     *
     * @return external_single_structure
     */
    public static function week(): external_single_structure {
        return new external_single_structure([
            'saber' => new external_value(PARAM_INT, 'Knowledge of the team this week'),
            'part' => new external_value(PARAM_INT, 'Part of each member: knowledge this week'),
            'done' => new external_value(PARAM_INT, 'Members who have reached their part'),
            'needed' => new external_value(PARAM_INT, 'Members who have to reach it'),
            'goal' => new external_value(PARAM_INT, 'Knowledge of the parts needed'),
            'reached' => new external_value(PARAM_BOOL, 'Goal reached'),
            'players' => new external_value(PARAM_INT, 'Members who play'),
        ]);
    }

    /**
     * The commitments of a team in the chapter the class is in, for one of its members.
     *
     * @param int $required
     * @return external_single_structure
     */
    public static function team_pacts(int $required = VALUE_OPTIONAL): external_single_structure {
        return new external_single_structure([
            'chapter' => new external_value(PARAM_TEXT, 'Chapter the class is in'),
            'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Commitment of the mission')),
            'mine' => new external_value(PARAM_INT, 'The one the student chose (-1: none yet)'),
            'status' => new external_value(PARAM_ALPHA, 'open, kept, broken or empty'),
            'done' => new external_value(PARAM_BOOL, 'The team kept them all and got the breath'),
            'breath' => new external_value(PARAM_INT, 'Breath for each member when the team keeps them all'),
            'loss' => new external_value(PARAM_INT, 'Energy a commitment not kept costs'),
            'list' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Name shown to classmates'),
                'text' => new external_value(PARAM_TEXT, 'Commitment'),
                'status' => new external_value(PARAM_ALPHA, 'open, kept or broken'),
                'me' => new external_value(PARAM_BOOL, 'The current student'),
            ])),
        ], 'Commitments of the team', $required);
    }

    /**
     * The commitments to mark, for the teacher.
     *
     * @return external_single_structure
     */
    public static function teacher_pacts(): external_single_structure {
        return new external_single_structure([
            'chapter' => new external_value(PARAM_TEXT, 'Chapter the class is in'),
            'options' => new external_value(PARAM_INT, 'Commitments the mission brings for it'),
            'breath' => new external_value(PARAM_INT, 'Breath for each member when the team keeps them all'),
            'loss' => new external_value(PARAM_INT, 'Energy a commitment not kept costs'),
            'list' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Commitment'),
                'userid' => new external_value(PARAM_INT, 'Student'),
                'teamid' => new external_value(PARAM_INT, 'Group of the team'),
                'chapter' => new external_value(PARAM_TEXT, 'Earlier chapter it belongs to (empty: the current one)'),
                'text' => new external_value(PARAM_TEXT, 'Commitment'),
                'status' => new external_value(PARAM_ALPHA, 'open, kept or broken'),
            ])),
        ], 'Commitments of the teams', VALUE_OPTIONAL);
    }

    /**
     * The powers of a course, and the requests, for its teachers.
     *
     * @return external_single_structure
     */
    public static function powers(): external_single_structure {
        $request = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Request'),
            'userid' => new external_value(PARAM_INT, 'Student'),
            'fullname' => new external_value(PARAM_TEXT, 'Student'),
            'power' => new external_value(PARAM_TEXT, 'Power'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Code of the power'),
            'cost' => new external_value(PARAM_INT, 'Breath'),
            'target' => new external_value(PARAM_TEXT, 'Teammate it is for'),
            'status' => new external_value(PARAM_ALPHA, 'pending, approved, rejected or used'),
            'time' => new external_value(PARAM_INT, 'When it was asked'),
        ]);
        return new external_single_structure([
            'powers' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Power'),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'role' => new external_value(PARAM_ALPHA, 'Role, or all'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'description' => new external_value(PARAM_TEXT, 'What it does'),
                'cost' => new external_value(PARAM_INT, 'Breath'),
                'minlevel' => new external_value(PARAM_INT, 'Level needed'),
                'target' => new external_value(PARAM_ALPHA, 'mate, fallen, team or none'),
                'enabled' => new external_value(PARAM_BOOL, 'On'),
                'autoapprove' => new external_value(PARAM_BOOL, 'Approved by itself'),
                'own' => new external_value(PARAM_BOOL, 'Made by a teacher'),
            ])),
            'pending' => new external_multiple_structure($request),
            'decided' => new external_multiple_structure($request),
        ]);
    }

    /**
     * The ideas of a course, for its teachers.
     *
     * @return external_multiple_structure
     */
    public static function ideas(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Idea'),
            'message' => new external_value(PARAM_TEXT, 'The idea'),
            'fullname' => new external_value(PARAM_TEXT, 'Who sent it (empty: they chose not to say)'),
            'userid' => new external_value(PARAM_INT, 'Who sent it (0: they chose not to say)'),
            'status' => new external_value(PARAM_ALPHA, 'new or read'),
            'time' => new external_value(PARAM_INT, 'When'),
        ]));
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
            'timezone' => new external_value(PARAM_TEXT, 'Time zone of the days and weeks (99: the one of the site)'),
            'completionrule' => new external_value(PARAM_INT, 'Knowledge for an activity that is not a challenge'),
        ]);
    }

    /**
     * The event of the day on the class screen.
     *
     * @param int $required VALUE_OPTIONAL where there may be none yet.
     * @return external_single_structure
     */
    public static function event(int $required = VALUE_OPTIONAL): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Event'),
            'code' => new external_value(PARAM_ALPHA, 'Card: calm, breeze, footprint or exchange'),
            'status' => new external_value(PARAM_ALPHA, 'drawn or skipped'),
            'question' => new external_single_structure([
                'id' => new external_value(PARAM_RAW, 'Question'),
                'chapter' => new external_value(PARAM_RAW, 'Chapter'),
                'objective' => new external_value(PARAM_RAW, 'Objective'),
                'text' => new external_value(PARAM_TEXT, 'Question'),
                'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option')),
                'correct' => new external_value(PARAM_INT, 'Index of the right option'),
                'why' => new external_value(PARAM_TEXT, 'Why'),
            ], 'Question of a footprint', VALUE_OPTIONAL),
            'solved' => new external_value(PARAM_ALPHA, 'Footprint: right, wrong or empty'),
            'gifts' => new external_multiple_structure(new external_single_structure([
                'from' => new external_value(PARAM_INT, 'Group that gives'),
                'to' => new external_value(PARAM_INT, 'Group that receives'),
            ])),
        ], 'Event of the day', $required);
    }

    /**
     * What both the class screen and the phones see of a battle.
     *
     * @return array
     */
    private static function live_common(): array {
        return [
            'id' => new external_value(PARAM_INT, 'Session'),
            'kind' => new external_value(PARAM_ALPHA, 'battle or vote'),
            'mode' => new external_value(PARAM_ALPHA, 'phones or aloud'),
            'round' => new external_value(PARAM_INT, 'Question being played (0: players coming in)'),
            'total' => new external_value(PARAM_INT, 'Questions of the battle'),
            'open' => new external_value(PARAM_BOOL, 'The question takes answers'),
            'left' => new external_value(PARAM_INT, 'Seconds left (-1: no limit)'),
            'revealed' => new external_value(PARAM_BOOL, 'The answer of the question is shown'),
            'hp' => new external_value(PARAM_INT, 'Life of the Embrollo'),
            'maxhp' => new external_value(PARAM_INT, 'Life of the Embrollo at the start'),
            'outcome' => new external_value(PARAM_ALPHA, 'won, lost, done (a vote) or empty while playing'),
            'phase' => new external_value(PARAM_INT, '1, or 2 once the Embrollo is down to half its life', VALUE_DEFAULT, 1),
            'final' => new external_value(PARAM_BOOL, 'The final battle of the mission', VALUE_DEFAULT, false),
            'counter' => new external_value(PARAM_INT, 'Life the Embrollo got back after the round', VALUE_DEFAULT, 0),
            'shielded' => new external_value(PARAM_BOOL, 'A protector\'s shield stopped that', VALUE_DEFAULT, false),
            'tally' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Votes'),
                'Votes for each option, once a vote is closed',
                VALUE_OPTIONAL
            ),
            'question' => new external_single_structure([
                'id' => new external_value(PARAM_RAW, 'Question'),
                'text' => new external_value(PARAM_TEXT, 'Question'),
                'options' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Option')),
                'correct' => new external_value(PARAM_INT, 'Right option, once revealed', VALUE_OPTIONAL),
                'why' => new external_value(PARAM_TEXT, 'Why, once revealed', VALUE_OPTIONAL),
            ], 'Question of the round', VALUE_OPTIONAL),
        ];
    }

    /**
     * A battle on the class screen.
     *
     * @return external_single_structure
     */
    public static function live_board(): external_single_structure {
        return new external_single_structure(self::live_common() + [
            'joined' => new external_value(PARAM_INT, 'Players who came in with their phone'),
            'answered' => new external_value(PARAM_INT, 'Answers to the question of the round'),
            'right' => new external_value(PARAM_INT, 'Right answers in the round, once revealed', VALUE_OPTIONAL),
            'rewarded' => new external_value(PARAM_INT, 'Players who got the prize'),
            'teams' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Group'),
                'name' => new external_value(PARAM_TEXT, 'Team'),
                'joined' => new external_value(PARAM_INT, 'Players of the team who came in'),
                'right' => new external_value(PARAM_INT, 'Right answers of the team so far'),
                'mark' => new external_value(PARAM_INT, 'Aloud: marked right (1), not (0) or not yet (-1) in this round'),
            ])),
            'tokens' => new external_multiple_structure(new external_single_structure([
                'kind' => new external_value(PARAM_ALPHA, 'glance, trail, shield or cheer'),
                'team' => new external_value(PARAM_TEXT, 'Team of who used it'),
            ]), 'Battle tokens used in the round', VALUE_DEFAULT, []),
        ], 'Battle of the course', VALUE_OPTIONAL);
    }

    /**
     * A battle on the phone of a student.
     *
     * @return external_single_structure
     */
    public static function live_device(): external_single_structure {
        return new external_single_structure(self::live_common() + [
            'joined' => new external_value(PARAM_BOOL, 'The student came in'),
            'mine' => new external_value(PARAM_INT, 'Their answer in the round (-1: none)'),
            'prize' => new external_single_structure([
                'saber' => new external_value(PARAM_INT, 'Saber'),
                'capped' => new external_value(PARAM_INT, 'Saber left out by the weekly cap', VALUE_DEFAULT, 0),
                'gems' => new external_value(PARAM_INT, 'Gems'),
                'best' => new external_value(PARAM_BOOL, 'Their team helped most'),
                'item' => new external_value(PARAM_TEXT, 'Object they got for it (empty: none)', VALUE_DEFAULT, ''),
                'final' => new external_value(PARAM_TEXT, 'Object of the final battle (empty: none)', VALUE_DEFAULT, ''),
                'grew' => new external_value(PARAM_BOOL, 'Their creature grew', VALUE_DEFAULT, false),
            ], 'What they got for the win', VALUE_OPTIONAL),
            'token' => new external_value(PARAM_ALPHA, 'Battle token of their role (empty: none)', VALUE_DEFAULT, ''),
            'tokenused' => new external_value(PARAM_BOOL, 'They used it in this battle', VALUE_DEFAULT, false),
            'struck' => new external_value(PARAM_INT, 'A wrong option struck out by the glance of their team', VALUE_OPTIONAL),
            'trail' => new external_value(PARAM_TEXT, 'Chapter of the answer, by the trail of their team', VALUE_OPTIONAL),
        ], 'Battle of the course', VALUE_OPTIONAL);
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
