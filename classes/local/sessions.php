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
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class sessions {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** @var string The code of the entry, the session before the first place. */
    public const ENTRY = 'entrada';

    /** @var string[] What a step launches. */
    public const ACTIONS = ['enter', 'card', 'scene', 'game', 'battle', 'timer', 'pacts', 'light', 'challenge', 'council',
        'firstday', 'burst', 'none'];

    /**
     * @var string[] What the class screen shows for a session: the place entered, the card, a timer, the commitments, a
     * scene, the lantern lit, the teams in council, the phases of a session, the first day (the heroes being made) or the
     * burst of the Embrollo, to start a class.
     */
    public const SHOWS = ['enter', 'card', 'timer', 'pacts', 'scene', 'light', 'council', 'campfire', 'dawn', 'expedition',
        'encounter', 'loot', 'firstday', 'burst'];

    /** @var int Minutes of the soft hourglass of the expedition, when the teacher does not say. */
    public const EXPEDITION = 20;

    /** @var int Minutes of the council of the teams. */
    public const COUNCIL = 2;

    /** @var int Seconds a lantern waits ready before it lights by itself, if nobody lights it. */
    public const READY_WAIT = DAYSECS;

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
        $table = ['scene' => 'scene', 'light' => 'chapter'][$show] ?? '';
        if ($table && !$DB->record_exists(self::T . $table, ['id' => $target, 'missionid' => $open->missionid])) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $open->shown = $show !== '' ? $show : null;
        $open->shownat = $now ?? time();
        $open->minutes = match ($show) {
            'timer' => max(1, min(90, $minutes)),
            'council' => self::COUNCIL,
            'expedition' => max(1, min(90, $minutes ?: self::EXPEDITION)),
            default => 0,
        };
        // The encounter of the class: 0 while the class guesses, 1 once the teacher reveals it.
        $open->target = $table ? $target : ($show === 'encounter' ? min(1, max(0, $target)) : 0);
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
        // What waited ready, lit: no lantern stays waiting once the session is over.
        self::light_ready($open->missionid, $open->closedat);
        $DB->update_record(self::T . 'session', (object) ['id' => $open->id, 'closedat' => $open->closedat,
            'review' => $open->review, 'shown' => null]);
        return $open;
    }

    /**
     * The teacher lights with the class the lantern that waits ready: on the class screen, its rite.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function light(int $courseid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $open = self::open($courseid);
        $ready = $open ? self::ready((int) $open->missionid) : [];
        if (!$ready) {
            throw new \moodle_exception('nosession', 'local_oksigeniatesape');
        }
        $chapter = reset($ready);
        $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'litat' => $now, 'readyat' => 0,
            'timemodified' => $now]);
        return self::show($courseid, 'light', 0, (int) $chapter->id, $now);
    }

    /**
     * The lanterns of a mission that wait ready for the teacher, oldest first.
     *
     * @param int $missionid
     * @return \stdClass[]
     */
    public static function ready(int $missionid): array {
        global $DB;
        return array_values($DB->get_records_select(
            self::T . 'chapter',
            'missionid = ? AND readyat > 0 AND litat = 0',
            [$missionid],
            'readyat, sortorder',
            'id, code, readyat'
        ));
    }

    /**
     * Lights the lanterns that wait ready: all of them, or those that waited longer than READY_WAIT.
     *
     * @param int $missionid
     * @param int $now
     * @param bool $late Only those that waited too long.
     */
    public static function light_ready(int $missionid, int $now, bool $late = false): void {
        global $DB;
        foreach (self::ready($missionid) as $chapter) {
            if (!$late || $chapter->readyat <= $now - self::READY_WAIT) {
                $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'litat' => $now, 'readyat' => 0,
                    'timemodified' => $now]);
            }
        }
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
        self::light_ready((int) $mission->id, $now ?? time(), true);
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
            // The lanterns the class has, waiting for the teacher to light them with the class.
            $out['open']['ready'] = array_map(fn($c) => self::place($mission, (string) $c->code), self::ready((int) $mission->id));
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
        if ($open->shown === 'light') {
            $code = (string) $DB->get_field(self::T . 'chapter', 'code', ['id' => $open->target]);
            $out['lit'] = self::place($mission, $code);
            $out['litcode'] = $code;
        }
        if ($open->shown === 'pacts') {
            $out['pacts'] = self::pacts($courseid, $mission);
        }
        if ($open->shown === 'council') {
            $out['council'] = self::council($courseid, $mission, $now ?? time());
        }
        if ($open->shown === 'dawn') {
            $out['dawn'] = self::dawn($courseid, $mission, $now ?? time());
        }
        if ($open->shown === 'expedition') {
            $out['expedition'] = self::expedition($courseid, $open);
        }
        if ($open->shown === 'encounter') {
            $out['encounter'] = self::encounter($mission, $open, $now ?? time());
        }
        if ($open->shown === 'loot') {
            $out['loot'] = self::loot($courseid, $mission, $open, $now ?? time());
        }
        if ($open->shown === 'firstday') {
            $out['firstday'] = self::firstday($courseid);
        }
        if ($open->shown === 'burst') {
            // Another burst each time it is put on the screen.
            $out['burst'] = minigames::class_burst($mission, (int) $open->shownat);
        }
        return $out;
    }

    /**
     * The first day, for the class screen: how many of the class have made their hero, and the role of each one in the
     * order they were made, for a spark of its colour. No names.
     *
     * @param int $courseid
     * @return array made, total, sparks (the role of each hero made).
     */
    private static function firstday(int $courseid): array {
        global $DB;
        $students = view::students($courseid);
        $sparks = [];
        if ($students) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
            $chars = $DB->get_records_select(self::T . 'char', "userid $insql", $params, 'timecreated, id', 'id, role');
            $sparks = array_values(array_column($chars, 'role'));
        }
        return ['made' => count($sparks), 'total' => count($students), 'sparks' => array_map('strval', $sparks)];
    }

    /**
     * Dawn in the valley, for the class screen: what the Embrollo did in the night (the stains and the pages it still
     * keeps, the steps it took towards the next place), told by itself. Not the knots of a team, which only the team
     * sees, nor where it hid a page.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param int $now
     * @return array did (kind, place, title, hidden), prints, towards (the next place).
     */
    private static function dawn(int $courseid, \stdClass $mission, int $now): array {
        $did = [];
        foreach (stains::open($courseid, $now) as $stain) {
            $view = (int) ($stain->groupid ?? 0) ? null : stains::view($stain, $mission);
            if ($view) {
                $hidden = !empty($view['hidden']);
                $did[] = ['kind' => $view['kind'], 'place' => $hidden ? '' : (string) $view['place'],
                    'chapter' => $hidden ? '' : (string) $view['chapter'], 'title' => (string) $view['title'],
                    'hidden' => $hidden];
            }
        }
        $codes = array_column($mission->data['chapters'] ?? [], 'code');
        $today = pacts::chapter($mission);
        $at = $today ? array_search((string) $today->code, $codes, true) : false;
        $next = $at !== false && isset($codes[$at + 1]) ? self::place($mission, $codes[$at + 1]) : '';
        return ['did' => $did, 'prints' => $next ? stains::prints($mission, $now) : 0, 'towards' => $next];
    }

    /**
     * The expedition, for the class screen: the place of the session and, by team, whether any of its members has come
     * into the game since the session started (a firefly for each one that has). No names of students.
     *
     * @param int $courseid
     * @param \stdClass $open The session.
     * @return array teams (name, in).
     */
    private static function expedition(int $courseid, \stdClass $open): array {
        $teams = [];
        $grouping = (int) (regions::get($courseid)->groupingid ?? 0);
        foreach (groups_get_all_groups($courseid, 0, $grouping) as $group) {
            $in = false;
            foreach (teams::members($courseid, (int) $group->id) as $member) {
                $in = $in || (int) $member->lastseen >= (int) $open->startedat;
            }
            $teams[] = ['name' => format_string($group->name), 'in' => $in];
        }
        return ['teams' => $teams];
    }

    /**
     * The encounter of the day for the whole class, on the class screen: the being that peeks out in the place of the
     * session, the same all day, with its clues and four names to guess aloud; its name, only once the teacher reveals
     * it. Nothing is kept: the cards are each student's, in their tablet.
     *
     * @param \stdClass $mission
     * @param \stdClass $open The session.
     * @param int $now
     * @return array|null place, clues, group, options (id, name), picture, revealed, answer (once revealed).
     */
    private static function encounter(\stdClass $mission, \stdClass $open, int $now): ?array {
        $place = (string) $open->chapter;
        if ($place === self::ENTRY) {
            $place = (string) ($mission->data['chapters'][0]['code'] ?? '');
        }
        $being = encounters::for_class($mission, $place, $now);
        if (!$being) {
            return null;
        }
        $view = encounters::view($being, $mission);
        unset($view['crossed']);
        $view['place'] = self::place($mission, $place);
        $view['revealed'] = (int) $open->target === 1;
        $view['answer'] = $view['revealed'] ? (string) $being['id'] : '';
        return $view;
    }

    /**
     * The loot of the session, for the class screen: by team, what its members earned since the session started (saber
     * and gems, and the beings they registered in the field guide). Nothing by chance: the chest holds what was earned,
     * and the class screen opens it.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param \stdClass $open The session.
     * @param int $now Up to when.
     * @return array teams (name, saber, gems, beings (names), more (beings not named)), lit (places lit in the session).
     */
    private static function loot(int $courseid, \stdClass $mission, \stdClass $open, int $now): array {
        global $DB;
        $since = (int) $open->startedat;
        $names = [];
        foreach ($mission->data['encounters'] ?? [] as $being) {
            $names[(string) ($being['id'] ?? '')] = missions::text($being['name'] ?? '', $mission->skin, $mission->variant);
        }
        $teams = [];
        $grouping = (int) (regions::get($courseid)->groupingid ?? 0);
        foreach (groups_get_all_groups($courseid, 0, $grouping) as $group) {
            $charids = array_map(fn($m) => (int) $m->charid, teams::members($courseid, (int) $group->id, true));
            $chest = ['name' => format_string($group->name), 'saber' => 0, 'gems' => 0, 'beings' => [], 'more' => 0];
            if ($charids) {
                [$insql, $params] = $DB->get_in_or_equal($charids);
                $sums = $DB->get_records_sql_menu(
                    'SELECT resource, SUM(amount)
                       FROM {' . self::T . "ledger}
                      WHERE charid $insql AND courseid = ? AND timecreated BETWEEN ? AND ? AND amount > 0
                            AND undoneby = 0 AND resource IN ('saber', 'gems')
                   GROUP BY resource",
                    array_merge($params, [$courseid, $since, $now])
                );
                $chest['saber'] = (int) ($sums['saber'] ?? 0);
                $chest['gems'] = (int) ($sums['gems'] ?? 0);
                $codes = array_unique($DB->get_fieldset_select(
                    self::T . 'found',
                    'code',
                    "charid $insql AND missionid = ? AND kind = 'being' AND timecreated BETWEEN ? AND ?",
                    array_merge($params, [$mission->id, $since, $now])
                ));
                $found = array_values(array_filter(array_map(fn($c) => $names[$c] ?? '', $codes)));
                $chest['beings'] = array_slice($found, 0, 3);
                $chest['more'] = max(0, count($found) - 3);
            }
            $teams[] = $chest;
        }
        $lit = $DB->get_fieldset_select(
            self::T . 'chapter',
            'code',
            'missionid = ? AND litat BETWEEN ? AND ?',
            [$mission->id, $since, $now]
        );
        return ['teams' => $teams, 'lit' => array_map(fn($c) => self::place($mission, (string) $c), $lit)];
    }

    /**
     * The council of the teams, for the class screen: the place of today and, by team, the role its council chose to
     * start the hand (empty: still in council). No names.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param int $now
     * @return array place, teams (name, first).
     */
    private static function council(int $courseid, \stdClass $mission, int $now): array {
        $chapter = pacts::chapter($mission);
        $firsts = teams::firsts($courseid, $now);
        $teams = [];
        $grouping = (int) (regions::get($courseid)->groupingid ?? 0);
        foreach (groups_get_all_groups($courseid, 0, $grouping) as $group) {
            $teams[] = ['name' => format_string($group->name), 'first' => $firsts[(int) $group->id] ?? ''];
        }
        return ['place' => $chapter ? self::place($mission, (string) $chapter->code) : '', 'teams' => $teams];
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
     * @return array place, options (texts), teams (name, chose, total).
     */
    private static function pacts(int $courseid, \stdClass $mission): array {
        global $DB;
        $chapter = pacts::chapter($mission);
        if (!$chapter) {
            return ['place' => '', 'options' => [], 'teams' => []];
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
        // The commitments are those of the place the class is in, which may not be the one of the session.
        return ['place' => self::place($mission, (string) $chapter->code),
            'options' => array_values(pacts::options($mission, (string) $chapter->code)), 'teams' => $teams];
    }
}
