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

namespace local_oksigeniatesape\local;

/**
 * The sessions of a class, the unit of the game for the teacher: each place of the mission has its sessions (and the
 * mission an entry before the first place), each one with its sheet as data of the mission (steps with their minutes,
 * what is read aloud and what each one launches, the three key questions and the two mistakes to watch). The teacher
 * starts one, goes step by step, launches on the class screen what a step launches, and closes it, marking what
 * confused the class to review it at the start of the next one. Without a session everything goes on as before.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sessions {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** @var string The code of the entry, the session before the first place. */
    public const ENTRY = 'entrada';

    /** @var string[] What a step launches. */
    public const ACTIONS = ['enter', 'card', 'scene', 'game', 'battle', 'timer', 'pacts', 'light', 'challenge', 'none'];

    /** @var string[] What the class screen shows for a session: the place entered, the card, a timer, the commitments, a scene. */
    public const SHOWS = ['enter', 'card', 'timer', 'pacts', 'scene'];

    /** @var int Things to review the next session, at most. */
    public const REVIEW = 5;

    /**
     * The sessions of a mission, in their order: the entry, then those of each place.
     *
     * @param \stdClass $mission
     * @return array Each: chapter (code), number, sheet (as in the mission).
     */
    public static function all(\stdClass $mission): array {
        $list = [];
        if (!empty($mission->data['entry'])) {
            $list[] = ['chapter' => self::ENTRY, 'number' => 1, 'sheet' => $mission->data['entry']];
        }
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            foreach (array_values($chapter['sessions'] ?? []) as $i => $sheet) {
                $list[] = ['chapter' => (string) $chapter['code'], 'number' => $i + 1, 'sheet' => $sheet];
            }
        }
        return $list;
    }

    /**
     * The session going on in a course, if any.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function open(int $courseid): ?\stdClass {
        global $DB;
        $open = $DB->get_records(self::T . 'session', ['courseid' => $courseid, 'closedat' => 0], 'id DESC', '*', 0, 1);
        return $open ? reset($open) : null;
    }

    /**
     * The session that comes next: the one after the last closed one of the mission, or the first.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @return array|null chapter, number; null when all are done.
     */
    public static function next(int $courseid, \stdClass $mission): ?array {
        global $DB;
        $all = self::all($mission);
        $done = $DB->get_records_select(
            self::T . 'session',
            'courseid = ? AND missionid = ? AND closedat > 0',
            [$courseid, $mission->id],
            'closedat DESC, id DESC',
            'id, chapter, number',
            0,
            1
        );
        $last = $done ? reset($done) : null;
        $at = 0;
        foreach ($all as $i => $session) {
            if ($last && $session['chapter'] === $last->chapter && $session['number'] === (int) $last->number) {
                $at = $i + 1;
            }
        }
        return isset($all[$at]) ? ['chapter' => $all[$at]['chapter'], 'number' => $all[$at]['number']] : null;
    }

    /**
     * The sheet of a session as the teacher reads it: in the story and the place of the course, with the scene, the
     * game and the minutes of each step, and what the last session marked to review.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param string $chapter
     * @param int $number
     * @return array|null chapter, number, place, title, minutes, optional, before, steps, questions, watch, spare, review.
     */
    public static function view(int $courseid, \stdClass $mission, string $chapter, int $number): ?array {
        global $DB;
        $sheet = null;
        foreach (self::all($mission) as $session) {
            if ($session['chapter'] === $chapter && $session['number'] === $number) {
                $sheet = $session['sheet'];
            }
        }
        if ($sheet === null) {
            return null;
        }
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $place = $chapter === self::ENTRY ? '' : self::place($mission, $chapter);
        $scenes = $DB->get_records_menu(self::T . 'scene', ['missionid' => $mission->id], '', 'code, id');
        $summaries = scenes::summaries($mission);
        $steps = [];
        foreach ((array) ($sheet['steps'] ?? []) as $step) {
            $action = in_array($step['action'] ?? 'none', self::ACTIONS, true) ? (string) $step['action'] : 'none';
            $sceneid = (int) ($scenes[$step['scene'] ?? ''] ?? 0);
            $game = (string) ($step['game'] ?? '');
            $steps[] = ['from' => (int) ($step['from'] ?? 0), 'to' => (int) ($step['to'] ?? 0), 'do' => $text($step['do'] ?? ''),
                'say' => $text($step['say'] ?? ''), 'action' => $action,
                'also' => in_array($step['also'] ?? '', self::ACTIONS, true) ? (string) $step['also'] : '',
                'sceneid' => $sceneid, 'scene' => (string) ($summaries[$sceneid]['title'] ?? ''),
                'game' => $game, 'gametitle' => $game !== '' ? missions::text(
                    $mission->data['games'][$game]['title'] ?? $game,
                    $mission->skin
                ) : '', 'timer' => (int) ($step['timer'] ?? 0)];
        }
        // What the last closed session marked to review, at the start of this one.
        $last = $DB->get_records_select(
            self::T . 'session',
            'courseid = ? AND missionid = ? AND closedat > 0',
            [$courseid, $mission->id],
            'closedat DESC, id DESC',
            'id, review',
            0,
            1
        );
        $last = $last ? reset($last) : null;
        return ['chapter' => $chapter, 'number' => $number, 'place' => $place, 'title' => $text($sheet['title'] ?? ''),
            'minutes' => (int) ($sheet['minutes'] ?? 50), 'optional' => !empty($sheet['optional']),
            'final' => ($sheet['battle'] ?? '') === 'final',
            'before' => array_map($text, array_values((array) ($sheet['before'] ?? []))), 'steps' => $steps,
            'questions' => array_map(
                fn($q) => ['ask' => $text($q['ask'] ?? ''), 'expect' => $text($q['expect'] ?? '')],
                array_values((array) ($sheet['questions'] ?? []))
            ),
            'watch' => array_map(
                fn($w) => ['mistake' => $text($w['mistake'] ?? ''), 'redirect' => $text($w['redirect'] ?? '')],
                array_values((array) ($sheet['watch'] ?? []))
            ),
            'spare' => $text($sheet['spare'] ?? ''),
            'review' => $last ? array_values((array) (json_decode((string) $last->review, true) ?: [])) : []];
    }

    /**
     * The name of a place of a mission.
     *
     * @param \stdClass $mission
     * @param string $chapter
     * @return string
     */
    private static function place(\stdClass $mission, string $chapter): string {
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $chapter) {
                return missions::text($info['name'] ?? $chapter, $mission->skin);
            }
        }
        return $chapter;
    }

    /**
     * The teacher starts a session (the one going on, if there is one, is closed first, without anything to review).
     *
     * @param int $courseid
     * @param string $chapter
     * @param int $number
     * @param int|null $now
     * @return \stdClass
     */
    public static function start(int $courseid, string $chapter, int $number, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        if (!$mission || !self::view($courseid, $mission, $chapter, $number)) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $open = self::open($courseid);
        if ($open) {
            $DB->set_field(self::T . 'session', 'closedat', $now, ['id' => $open->id]);
        }
        $record = (object) ['courseid' => $courseid, 'missionid' => $mission->id, 'chapter' => $chapter, 'number' => $number,
            'step' => 0, 'shown' => null, 'shownat' => 0, 'minutes' => 0, 'target' => 0, 'review' => null,
            'startedat' => $now, 'closedat' => 0];
        $record->id = $DB->insert_record(self::T . 'session', $record);
        return $record;
    }

    /**
     * The session goes to a step.
     *
     * @param int $courseid
     * @param int $step
     * @return \stdClass
     */
    public static function step(int $courseid, int $step): \stdClass {
        global $DB;
        $open = self::open($courseid);
        if (!$open) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $open->step = max(0, $step);
        $DB->set_field(self::T . 'session', 'step', $open->step, ['id' => $open->id]);
        return $open;
    }

    /**
     * What the class screen shows for the session: the place entered, the card of the day, a timer, the commitments or a
     * scene to play with the class; empty, the usual again.
     *
     * @param int $courseid
     * @param string $show One of SHOWS, or empty.
     * @param int $minutes Of the timer.
     * @param int $target The scene, to play one.
     * @param int|null $now
     * @return \stdClass
     */
    public static function show(int $courseid, string $show, int $minutes = 0, int $target = 0, ?int $now = null): \stdClass {
        global $DB;
        $open = self::open($courseid);
        if (!$open || ($show !== '' && !in_array($show, self::SHOWS, true))) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        if ($show === 'scene' && !$DB->record_exists(self::T . 'scene', ['id' => $target, 'missionid' => $open->missionid])) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $open->shown = $show !== '' ? $show : null;
        $open->shownat = $now ?? time();
        $open->minutes = $show === 'timer' ? max(1, min(90, $minutes)) : 0;
        $open->target = $show === 'scene' ? $target : 0;
        $DB->update_record(self::T . 'session', (object) ['id' => $open->id, 'shown' => $open->shown,
            'shownat' => $open->shownat, 'minutes' => $open->minutes, 'target' => $open->target]);
        return $open;
    }

    /**
     * The teacher closes the session, with what confused the class today to review at the start of the next one.
     *
     * @param int $courseid
     * @param string[] $review
     * @param int|null $now
     * @return \stdClass
     */
    public static function close(int $courseid, array $review, ?int $now = null): \stdClass {
        global $DB;
        $open = self::open($courseid);
        if (!$open) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $kept = [];
        foreach ($review as $line) {
            $line = \core_text::substr(trim(clean_param((string) $line, PARAM_TEXT)), 0, 300);
            if ($line !== '' && count($kept) < self::REVIEW) {
                $kept[] = $line;
            }
        }
        $review = $kept;
        $open->closedat = $now ?? time();
        $open->review = $review ? json_encode($review) : null;
        $DB->update_record(self::T . 'session', (object) ['id' => $open->id, 'closedat' => $open->closedat,
            'review' => $open->review, 'shown' => null]);
        return $open;
    }

    /**
     * What the teacher sees of the sessions of a course: the one going on (with its step) or the one that comes next,
     * each with its sheet, and the list of all, to choose another.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array|null open (sheet, step, startedat, now), next (sheet), list (chapter, number, place, title, done,
     *     optional); null without a mission.
     */
    public static function teacher(int $courseid, ?int $now = null): ?array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $open = self::open($courseid);
        $closed = [];
        foreach (
            $DB->get_records_select(
                self::T . 'session',
                'courseid = ? AND missionid = ? AND closedat > 0',
                [$courseid, $mission->id],
                '',
                'id, chapter, number'
            ) as $row
        ) {
            $closed[$row->chapter . '#' . $row->number] = true;
        }
        $list = [];
        foreach (self::all($mission) as $session) {
            $list[] = ['chapter' => $session['chapter'], 'number' => $session['number'],
                'place' => $session['chapter'] === self::ENTRY ? '' : self::place($mission, $session['chapter']),
                'title' => missions::text($session['sheet']['title'] ?? '', $mission->skin, $mission->variant),
                'done' => !empty($closed[$session['chapter'] . '#' . $session['number']]),
                'optional' => !empty($session['sheet']['optional'])];
        }
        $out = ['open' => null, 'next' => null, 'list' => $list];
        if ($open) {
            $out['open'] = (self::view($courseid, $mission, $open->chapter, (int) $open->number) ?? []) + ['id' => (int) $open->id,
                'step' => (int) $open->step, 'shown' => (string) $open->shown, 'startedat' => (int) $open->startedat,
                'now' => $now ?? time()];
            // What confused the class most, to mark it for the next session when this one is closed.
            $context = nextclass::context($courseid);
            $out['open']['confusions'] = array_values(array_map(fn($m) => ['question' => (string) $m['question'],
                'answer' => (string) $m['answer']], array_slice((array) ($context['mixups'] ?? []), 0, self::REVIEW)));
        } else {
            $next = self::next($courseid, $mission);
            $out['next'] = $next ? self::view($courseid, $mission, $next['chapter'], $next['number']) : null;
        }
        return $out;
    }

    /**
     * What the class screen shows of the session going on: its place and title, and what the teacher launched (the place
     * entered, with its letter; the card; a timer; the commitments of the place and how many have chosen, by team).
     *
     * @param int $courseid
     * @param int|null $now
     * @return array|null
     */
    public static function board(int $courseid, ?int $now = null): ?array {
        global $DB;
        $open = self::open($courseid);
        $mission = $open ? missions::get($courseid) : null;
        if (!$open || !$mission) {
            return null;
        }
        $sheet = self::view($courseid, $mission, $open->chapter, (int) $open->number);
        $out = ['id' => (int) $open->id, 'chapter' => (string) $open->chapter, 'number' => (int) $open->number,
            'place' => $sheet['place'] ?? '', 'title' => $sheet['title'] ?? '', 'shown' => (string) $open->shown,
            'shownat' => (int) $open->shownat, 'minutes' => (int) $open->minutes, 'target' => (int) $open->target,
            'now' => $now ?? time()];
        if ($open->shown === 'enter' && $open->chapter !== self::ENTRY) {
            $out['letter'] = today::letter($mission, (string) $open->chapter);
            $out['intro'] = missions::text(
                self::chapter($mission, (string) $open->chapter)['intro'] ?? '',
                $mission->skin,
                $mission->variant
            );
        }
        if ($open->shown === 'pacts') {
            $out['pacts'] = self::pacts($courseid, $mission);
        }
        return $out;
    }

    /**
     * A place of a mission, as in the package.
     *
     * @param \stdClass $mission
     * @param string $code
     * @return array
     */
    private static function chapter(\stdClass $mission, string $code): array {
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $code) {
                return $info;
            }
        }
        return [];
    }

    /**
     * The commitments of the place of today and, by team, how many have chosen one, for the class screen (no names).
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @return array options (texts), teams (name, chose, total).
     */
    private static function pacts(int $courseid, \stdClass $mission): array {
        global $DB;
        $chapter = pacts::chapter($mission);
        if (!$chapter) {
            return ['options' => [], 'teams' => []];
        }
        $chose = $DB->get_fieldset_select(
            self::T . 'pact',
            'groupid',
            'courseid = ? AND missionid = ? AND chapter = ?',
            [$courseid, $mission->id, $chapter->code]
        );
        $count = array_count_values(array_map('intval', $chose));
        $teams = [];
        $grouping = (int) (regions::get($courseid)->groupingid ?? 0);
        foreach (groups_get_all_groups($courseid, 0, $grouping) as $group) {
            $teams[] = ['name' => format_string($group->name), 'chose' => (int) ($count[(int) $group->id] ?? 0),
                'total' => count(groups_get_members($group->id, 'u.id'))];
        }
        return ['options' => array_values(pacts::options($mission, (string) $chapter->code)), 'teams' => $teams];
    }
}
