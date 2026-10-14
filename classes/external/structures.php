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
            'aura' => new external_value(PARAM_ALPHANUMEXT, 'Aura it wears from the market', VALUE_DEFAULT, ''),
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
     * A scene in the map of a student.
     *
     * @return external_single_structure
     */
    public static function scene_item(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Scene'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'minutes' => new external_value(PARAM_INT, 'Minutes, more or less'),
            'step' => new external_value(PARAM_INT, 'Step it is for (0: every step)', VALUE_DEFAULT, 0),
            'done' => new external_value(PARAM_BOOL, 'Done'),
            'better' => new external_value(PARAM_BOOL, 'Done with a partial ending', VALUE_DEFAULT, false),
            'attempts' => new external_value(PARAM_INT, 'Plays'),
            'mode' => new external_value(PARAM_ALPHA, 'solo, team or both: how it can be played', VALUE_DEFAULT, 'both'),
        ]);
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
            'map' => new external_value(PARAM_ALPHANUMEXT, 'Code of the painted map', VALUE_DEFAULT, ''),
            'mapworld' => new external_value(PARAM_ALPHANUMEXT, 'World of a map in layers', VALUE_DEFAULT, ''),
            'mapheight' => new external_value(PARAM_INT, 'Height of a map in layers', VALUE_DEFAULT, 0),
            'chapters' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Chapter'),
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Code'),
                'name' => new external_value(PARAM_TEXT, 'Name'),
                'intro' => new external_value(PARAM_TEXT, 'Introduction'),
                'x' => new external_value(PARAM_INT, 'Place on the map'),
                'y' => new external_value(PARAM_INT, 'Place on the map'),
                'landmark' => new external_value(PARAM_ALPHANUMEXT, 'Landmark on a map in layers', VALUE_DEFAULT, ''),
                'art' => new external_value(PARAM_ALPHANUMEXT, 'Landscape of the place', VALUE_DEFAULT, ''),
                'lit' => new external_value(PARAM_BOOL, 'Lantern of the class lit'),
                'mine' => new external_value(PARAM_BOOL, 'Lantern of the student lit'),
                'done' => new external_value(PARAM_BOOL, 'Scenes of the student done'),
                'challengeid' => new external_value(PARAM_INT, 'Classroom challenge of the chapter (0: none)'),
                'letter' => new external_value(PARAM_TEXT, 'Letter the Embrollo left once the class lit it', VALUE_DEFAULT, ''),
                'games' => new external_multiple_structure(new external_single_structure([
                    'code' => new external_value(PARAM_ALPHANUMEXT, 'Minigame'),
                    'title' => new external_value(PARAM_TEXT, 'Title'),
                    'done' => new external_value(PARAM_BOOL, 'Won'),
                ])),
                'scenes' => new external_multiple_structure(self::scene_item()),
                'quests' => new external_multiple_structure(
                    self::scene_item(),
                    'Quests: for whoever wants more',
                    VALUE_DEFAULT,
                    []
                ),
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
     * Today in the valley: what the mission proposes for the class sessions of the chapter the class is in.
     *
     * @return external_single_structure
     */
    public static function today(): external_single_structure {
        return new external_single_structure([
            'chapter' => new external_value(PARAM_TEXT, 'Chapter the class is in'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Its code'),
            'intro' => new external_value(PARAM_TEXT, 'Its introduction'),
            'offscreen' => new external_value(PARAM_TEXT, 'Its challenge away from the screen'),
            'sessions' => new external_multiple_structure(new external_single_structure([
                'n' => new external_value(PARAM_INT, 'Number of the session in the chapter'),
                'do' => new external_value(PARAM_TEXT, 'What to do'),
                'materials' => new external_value(PARAM_TEXT, 'Materials'),
                'minutes' => new external_value(PARAM_INT, 'Minutes'),
                'sceneid' => new external_value(PARAM_INT, 'Scene to play together (0: none)'),
                'scene' => new external_value(PARAM_TEXT, 'Its title'),
                'game' => new external_value(PARAM_TEXT, 'Minigame to play together'),
                'final' => new external_value(PARAM_BOOL, 'The final battle'),
            ])),
            'letter' => new external_single_structure([
                'chapter' => new external_value(PARAM_TEXT, 'Place where the Embrollo left it'),
                'text' => new external_value(PARAM_TEXT, 'The letter'),
            ], 'The last letter of the Embrollo', VALUE_OPTIONAL),
            'mixup' => new external_single_structure([
                'question' => new external_value(PARAM_TEXT, 'Question or game'),
                'answer' => new external_value(PARAM_TEXT, 'The wrong answer most given'),
            ], 'The mix-up that came up most', VALUE_OPTIONAL),
        ], 'Today in the valley', VALUE_OPTIONAL);
    }

    /**
     * The market of the valley, as a student sees it.
     *
     * @return external_multiple_structure
     */
    public static function market(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'code' => new external_value(PARAM_ALPHANUMEXT, 'What'),
            'kind' => new external_value(PARAM_ALPHA, 'paint, aura or banner'),
            'price' => new external_value(PARAM_INT, 'Gems'),
            'team' => new external_value(PARAM_BOOL, 'The team buys it together'),
            'owned' => new external_value(PARAM_BOOL, 'Owned (by the team, if it is the team\'s)'),
            'worn' => new external_value(PARAM_BOOL, 'Worn'),
            'given' => new external_value(PARAM_INT, 'Gems the team has put towards it'),
            'mine' => new external_value(PARAM_INT, 'Gems the student has put towards it'),
        ]), 'The market of the valley', VALUE_DEFAULT, []);
    }

    /**
     * The album of the field guide.
     *
     * @return external_single_structure
     */
    public static function album(): external_single_structure {
        return new external_single_structure([
            'cards' => new external_multiple_structure(new external_single_structure([
                'name' => new external_value(PARAM_TEXT, 'Species'),
                'group' => new external_value(PARAM_TEXT, 'Its group'),
                'golden' => new external_value(PARAM_BOOL, 'The entry says its place in a food chain'),
                'mine' => new external_value(PARAM_BOOL, 'Made by the student'),
            ])),
            'class' => new external_value(PARAM_INT, 'Entries of the whole class'),
            'cmid' => new external_value(PARAM_INT, 'The field guide'),
        ], 'The album of the field guide', VALUE_OPTIONAL);
    }

    /**
     * The expeditions a course made before the current one.
     *
     * @return external_multiple_structure
     */
    public static function expeditions(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Mission'),
            'code' => new external_value(PARAM_ALPHANUMEXT, 'Code of the mission'),
            'title' => new external_value(PARAM_TEXT, 'Title'),
            'subtitle' => new external_value(PARAM_TEXT, 'Subtitle'),
            'lit' => new external_value(PARAM_INT, 'Lanterns the class lit'),
            'total' => new external_value(PARAM_INT, 'Chapters'),
            'mine' => new external_value(PARAM_INT, 'Lanterns the student lit'),
            'pages' => new external_value(PARAM_INT, 'Pages the student found'),
        ]), 'Expeditions made before the current one', VALUE_DEFAULT, []);
    }

    /**
     * The competencies of the objectives of a mission and the grid of the class to rate them.
     *
     * @return external_single_structure
     */
    public static function competencies(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'The site uses competencies and the course has a mission'),
            'objectives' => new external_multiple_structure(new external_single_structure([
                'code' => new external_value(PARAM_ALPHANUMEXT, 'Objective'),
                'name' => new external_value(PARAM_TEXT, 'Its name'),
                'competencyid' => new external_value(PARAM_INT, 'Competency it leaves evidence in (0: none)'),
                'competency' => new external_value(PARAM_TEXT, 'Its name'),
                'levels' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Level of its scale')),
            ])),
            'options' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency'),
                'name' => new external_value(PARAM_TEXT, 'Its name'),
                'framework' => new external_value(PARAM_TEXT, 'Its framework'),
            ])),
            'students' => new external_multiple_structure(new external_single_structure([
                'userid' => new external_value(PARAM_INT, 'Student'),
                'fullname' => new external_value(PARAM_TEXT, 'Name'),
                'cells' => new external_multiple_structure(new external_single_structure([
                    'objective' => new external_value(PARAM_ALPHANUMEXT, 'Objective'),
                    'percent' => new external_value(PARAM_INT, 'Right at the first try (-1: no evidence yet)'),
                    'rating' => new external_value(PARAM_INT, 'Rating in the course (0: none)'),
                ])),
            ])),
        ]);
    }

    /**
     * Own names waiting for a teacher.
     *
     * @return external_multiple_structure
     */
    public static function names_pending(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'Student'),
            'what' => new external_value(PARAM_ALPHA, 'hero or creature'),
            'name' => new external_value(PARAM_TEXT, 'The name proposed'),
        ]));
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
            'portal' => new external_value(PARAM_ALPHA, 'Cover: door, card, direct or menu', VALUE_DEFAULT, 'door'),
            'hideacts' => new external_value(PARAM_BOOL, 'Mission activities only inside the valley', VALUE_DEFAULT, false),
            'allowstealth' => new external_value(PARAM_BOOL, 'Site allows available but hidden activities', VALUE_DEFAULT, false),
            'badge' => new external_value(PARAM_TEXT, 'Name of the badge of the mission (empty: none)', VALUE_DEFAULT, ''),
            'badgeid' => new external_value(PARAM_INT, 'The badge of the mission in the course (0: none)', VALUE_DEFAULT, 0),
            'badgeon' => new external_value(PARAM_BOOL, 'The teacher turned the badge on', VALUE_DEFAULT, false),
            'gradebook' => new external_value(PARAM_BOOL, 'The ratings go to the gradebook', VALUE_DEFAULT, false),
            'quiet' => new external_value(PARAM_BOOL, 'No sound on the devices of the students', VALUE_DEFAULT, false),
            'look' => new external_value(PARAM_ALPHA, 'Theme of the class (empty: the one of the world)', VALUE_DEFAULT, ''),
            'worldlook' => new external_value(PARAM_ALPHA, 'Theme of the world', VALUE_DEFAULT, 'night'),
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
