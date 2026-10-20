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
 * What the Embrollo does every day: it stains the place of today with a sheet of its mistakes, from the hunt for
 * mistakes of that place. Decided on the first visit of the day, from Monday to Friday, without a scheduled task.
 * Whoever cleans it (touches the wrong words) gets a gem and its complaint, and it is clean for the whole class; the
 * teacher can take it as cleaned by the class with a touch. Calm (the default) leaves one stain at most, which never
 * grows; naughty leaves another one every day it is not cleaned, up to three. It never takes anything from anybody.
 *
 * Or it steals a page of the Great Book: when the class left a knot hanging in the battles of the last week and that
 * knot has its page, the action of the day is taking it (one at a time, never the same knot twice in a week). It comes
 * back to the whole class with the first right answer to a question of the knot, one try a day for each student.
 *
 * A card of the day can tangle a sheet for a team alone («A knot for a team»): only its members see it and clean it,
 * until the day ends; cleaned, it counts for the expedition as any other stain.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stains {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] How much the Embrollo does in the valley, a setting of the course. */
    public const MODES = ['off', 'calm', 'naughty'];

    /** @var int Stains at the same time, at most, when it is naughty. */
    public const MAX = 3;

    /** @var int Gems for cleaning a stain, or for bringing back a page. */
    public const GEMS = 1;

    /**
     * The stains of a class not cleaned yet, after deciding the one of today if it was not decided.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass[] Oldest first.
     */
    public static function open(int $courseid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $region = regions::get($courseid);
        $mode = in_array($region->mischief ?? 'calm', self::MODES, true) ? $region->mischief : 'calm';
        $mission = missions::get($courseid);
        if (!$region || !$region->enabled || $mode === 'off' || !$mission) {
            return [];
        }
        $date = (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid));
        $day = (int) $date->format('Ymd');
        $open = self::unclean($courseid, $day);
        $decided = $DB->record_exists(self::T . 'stain', ['courseid' => $courseid, 'day' => $day, 'groupid' => 0]);
        if ((int) $date->format('N') > 5 || $decided) {
            return $open;
        }
        // Today's: a page stolen, if the class left a knot hanging; if not, a new stain if there is room for it; if not,
        // the day is noted as decided all the same.
        $theft = self::to_steal($courseid, $mission, $open, $now);
        $stains = array_filter($open, fn($s) => ($s->kind ?? 'stain') === 'stain' && empty($s->groupid));
        $chapter = pacts::chapter($mission);
        $room = $mode === 'naughty' ? count($stains) < self::MAX : !$stains;
        $sheet = !$theft && $chapter && $room ? self::next_sheet($mission, (string) $chapter->code, $courseid) : null;
        $record = (object) ['courseid' => $courseid, 'day' => $day, 'chapter' => $sheet ? (string) $chapter->code : '',
            'game' => $sheet['game'] ?? '', 'sheet' => $sheet['sheet'] ?? '', 'kind' => 'stain', 'cleanedat' => $sheet ? 0 : $now,
            'erased' => 0, 'groupid' => 0, 'timecreated' => $now];
        if ($theft) {
            $record->chapter = $theft['chapter'];
            $record->game = $theft['code'];
            $record->sheet = $theft['page'];
            $record->kind = 'page';
            $record->cleanedat = 0;
        }
        try {
            $record->id = $DB->insert_record(self::T . 'stain', $record);
        } catch (\dml_write_exception $e) {
            // Another visit decided it at the same time.
            return self::unclean($courseid, $day);
        }
        if ($sheet || $theft) {
            $open[] = $record;
        }
        return $open;
    }

    /**
     * The knot whose page the Embrollo steals today: the first one the class left hanging in the battles of the last
     * week that has its page, if no page is stolen now and that one was not stolen in the last week.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @param \stdClass[] $open What is stolen or stained now.
     * @param int $now
     * @return array|null code (of the knot), page, chapter (code of its place).
     */
    private static function to_steal(int $courseid, \stdClass $mission, array $open, int $now): ?array {
        global $DB;
        if (array_filter($open, fn($s) => ($s->kind ?? 'stain') === 'page')) {
            return null;
        }
        $since = $now - results::KNOT_DAYS * DAYSECS;
        $chapters = array_values($mission->data['chapters'] ?? []);
        foreach (results::knots($courseid, $mission, $now) as $hanging) {
            $knot = self::knot($mission, (string) $hanging['code']);
            $page = (string) ($knot['page'] ?? '');
            if ($page === '' || !isset($mission->data['pages'][$page])) {
                continue;
            }
            $where = "courseid = ? AND kind = 'page' AND game = ? AND timecreated > ?";
            if ($DB->record_exists_select(self::T . 'stain', $where, [$courseid, $hanging['code'], $since])) {
                continue;
            }
            return ['code' => (string) $hanging['code'], 'page' => $page,
                'chapter' => (string) ($chapters[(int) ($knot['chapter'] ?? 1) - 1]['code'] ?? '')];
        }
        return null;
    }

    /**
     * A knot of the battle of a mission, as in the package.
     *
     * @param \stdClass $mission
     * @param string $code
     * @return array|null
     */
    private static function knot(\stdClass $mission, string $code): ?array {
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if (($knot['code'] ?? '') === $code) {
                return $knot;
            }
        }
        return null;
    }

    /**
     * The stains of a class not cleaned yet, oldest first: those of a team, only on their day.
     *
     * @param int $courseid
     * @param int $day Today, as yyyymmdd.
     * @return \stdClass[]
     */
    private static function unclean(int $courseid, int $day): array {
        global $DB;
        $where = "courseid = ? AND cleanedat = 0 AND chapter <> '' AND (groupid = 0 OR day = ?)";
        return array_values($DB->get_records_select(self::T . 'stain', $where, [$courseid, $day], 'id'));
    }

    /**
     * A knot for a team, from a card of the day: the next sheet of the hunt for mistakes of the place of today, for that
     * team alone, until the day ends. It takes the place of a knot of today not cleaned yet.
     *
     * @param int $courseid
     * @param int $groupid
     * @param int|null $now
     * @return \stdClass|null The stain; null if the place of today has no hunt for mistakes.
     */
    public static function for_team(int $courseid, int $groupid, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        $sheet = $chapter ? self::next_sheet($mission, (string) $chapter->code, $courseid) : null;
        if (!$sheet || $groupid <= 0) {
            return null;
        }
        $day = self::day($courseid, $now);
        $where = 'courseid = ? AND day = ? AND groupid <> 0 AND cleanedat = 0';
        $DB->delete_records_select(self::T . 'stain', $where, [$courseid, $day]);
        $id = $DB->insert_record(self::T . 'stain', (object) ['courseid' => $courseid, 'day' => $day,
            'chapter' => (string) $chapter->code, 'game' => $sheet['game'], 'sheet' => $sheet['sheet'], 'kind' => 'stain',
            'cleanedat' => 0, 'erased' => 0, 'groupid' => $groupid, 'timecreated' => $now]);
        return $DB->get_record(self::T . 'stain', ['id' => $id], '*', MUST_EXIST);
    }

    /** @var int Lanterns the class must have lit for the Embrollo to come back. */
    public const COMEBACK = 3;

    /**
     * The places the class lit that the Embrollo can come back to: with their hunt for mistakes, and not stained now.
     *
     * @param int $courseid
     * @return array [mission, codes]
     */
    private static function lit_places(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return [null, []];
        }
        $lit = $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ? AND litat > 0', [$mission->id]);
        if (count($lit) < self::COMEBACK) {
            return [$mission, []];
        }
        $stained = $DB->get_fieldset_select(
            self::T . 'stain',
            'chapter',
            'courseid = ? AND cleanedat = 0 AND groupid = 0',
            [$courseid]
        );
        $out = array_values(array_filter($lit, fn($code) => !in_array($code, $stained, true)
            && self::next_sheet($mission, (string) $code, $courseid) !== null));
        return [$mission, $out];
    }

    /**
     * Whether the Embrollo can come back today: the class lit three places at least, one of them can be stained, and
     * the Embrollo has not stained nor stolen anything for the class today (coming back is what it does today).
     *
     * @param int $courseid
     * @param int|null $now
     * @return bool
     */
    public static function can_come_back(int $courseid, ?int $now = null): bool {
        global $DB;
        $today = $DB->get_record(self::T . 'stain', ['courseid' => $courseid, 'day' => self::day($courseid, $now ?? time()),
            'groupid' => 0]);
        return (!$today || (string) $today->chapter === '') && self::lit_places($courseid)[1];
    }

    /**
     * The Embrollo comes back to a place the class already lit, from a card of the day: the next sheet of its hunt for
     * mistakes stains it for the whole class, and its lantern flickers until it is cleaned.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass|null The stain.
     */
    public static function come_back(int $courseid, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        if (!self::can_come_back($courseid, $now)) {
            return null;
        }
        [$mission, $places] = self::lit_places($courseid);
        $code = $places[random_int(0, count($places) - 1)];
        $sheet = self::next_sheet($mission, $code, $courseid);
        $record = (object) ['courseid' => $courseid, 'day' => self::day($courseid, $now), 'chapter' => $code,
            'game' => $sheet['game'], 'sheet' => $sheet['sheet'], 'kind' => 'stain', 'cleanedat' => 0, 'erased' => 0,
            'groupid' => 0, 'timecreated' => $now];
        // The day was decided already without a stain: coming back takes its place.
        $today = $DB->get_record(self::T . 'stain', ['courseid' => $courseid, 'day' => $record->day, 'groupid' => 0]);
        if ($today) {
            $record->id = $today->id;
            $DB->update_record(self::T . 'stain', $record);
        } else {
            $record->id = $DB->insert_record(self::T . 'stain', $record);
        }
        return $DB->get_record(self::T . 'stain', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * The stain of a card of the Embrollo coming back goes away with the card, if nobody cleaned it: the day stays
     * decided, with nothing stained.
     *
     * @param int $courseid
     * @param int $stainid
     */
    public static function go_back(int $courseid, int $stainid): void {
        global $DB;
        $where = 'id = ? AND courseid = ? AND groupid = 0 AND cleanedat = 0';
        if ($DB->record_exists_select(self::T . 'stain', $where, [$stainid, $courseid])) {
            $DB->update_record(self::T . 'stain', (object) ['id' => $stainid, 'chapter' => '', 'game' => '', 'sheet' => '',
                'cleanedat' => time()]);
        }
    }

    /**
     * Whether a card can tangle a sheet for a team today: the place of today has a hunt for mistakes.
     *
     * @param int $courseid
     * @return bool
     */
    public static function can_tangle(int $courseid): bool {
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        return $chapter && self::next_sheet($mission, (string) $chapter->code, $courseid) !== null;
    }

    /**
     * The knot of a team goes to another team, or away, if it was not cleaned yet.
     *
     * @param int $courseid
     * @param int $stainid
     * @param int $groupid The team it goes to (0: it goes away).
     */
    public static function move_team(int $courseid, int $stainid, int $groupid): void {
        global $DB;
        $where = 'id = ? AND courseid = ? AND groupid <> 0 AND cleanedat = 0';
        if ($groupid > 0) {
            $DB->set_field_select(self::T . 'stain', 'groupid', $groupid, $where, [$stainid, $courseid]);
        } else {
            $DB->delete_records_select(self::T . 'stain', $where, [$stainid, $courseid]);
        }
    }

    /**
     * The sheet the Embrollo stains a place with: the next one of the hunt for mistakes of that place, in turn.
     *
     * @param \stdClass $mission
     * @param string $chapter
     * @param int $courseid
     * @return array|null game, sheet; null if the place has no hunt for mistakes.
     */
    private static function next_sheet(\stdClass $mission, string $chapter, int $courseid): ?array {
        global $DB;
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') !== $chapter) {
                continue;
            }
            foreach ($info['games'] ?? [] as $game) {
                $sheets = $mission->data['games'][$game]['sheets'] ?? [];
                if (($mission->data['games'][$game]['type'] ?? '') === 'errors' && $sheets) {
                    $done = $DB->count_records(self::T . 'stain', ['courseid' => $courseid, 'game' => $game]);
                    return ['game' => (string) $game, 'sheet' => (string) $sheets[$done % count($sheets)]['id']];
                }
            }
        }
        return null;
    }

    /**
     * A stain as the students see it: the place, the title of the sheet and its words, without what is right.
     *
     * @param \stdClass $stain
     * @param \stdClass $mission
     * @return array|null id, chapter, place, title, parts (text, mark, wrong), picture (of the being it talks about); null
     *   if its sheet is not there any more.
     */
    public static function view(\stdClass $stain, \stdClass $mission): ?array {
        if (($stain->kind ?? 'stain') === 'page') {
            return self::page_view($stain, $mission);
        }
        $sheet = self::sheet($stain, $mission);
        if (!$sheet) {
            return null;
        }
        $place = '';
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $stain->chapter) {
                $place = missions::text($info['name'] ?? '', $mission->skin);
            }
        }
        $parts = minigames::parts(missions::text($sheet['text'] ?? '', $mission->skin, $mission->variant));
        return ['id' => (int) $stain->id, 'chapter' => (string) $stain->chapter, 'place' => $place,
            'title' => missions::text($sheet['title'] ?? '', $mission->skin, $mission->variant),
            'parts' => array_map(fn($p) => ['text' => $p['text'], 'mark' => $p['mark'], 'wrong' => $p['wrong']], $parts),
            'kind' => 'stain', 'groupid' => (int) ($stain->groupid ?? 0), 'picture' => minigames::being_picture($mission, $sheet)];
    }

    /**
     * A page stolen as the students see it: the place of its knot, the title of the page, what the Embrollo claims and
     * the question of today, without which option is right. While a rematch is pending (the class lost a battle), the
     * Embrollo has hidden it: it is not on the map, and whoever opens its place finds it.
     *
     * @param \stdClass $stain
     * @param \stdClass $mission
     * @param int|null $now
     * @return array|null
     */
    private static function page_view(\stdClass $stain, \stdClass $mission, ?int $now = null): ?array {
        $knot = self::knot($mission, (string) $stain->game);
        $page = $mission->data['pages'][$stain->sheet] ?? null;
        $question = $knot && $page ? self::question($stain, $mission, $now) : null;
        if (!$question) {
            return null;
        }
        $place = '';
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $stain->chapter) {
                $place = missions::text($info['name'] ?? '', $mission->skin);
            }
        }
        return ['id' => (int) $stain->id, 'chapter' => (string) $stain->chapter, 'place' => $place,
            'title' => missions::text($page['title'] ?? '', $mission->skin, $mission->variant), 'parts' => [], 'kind' => 'page',
            'claim' => missions::text($knot['claim'] ?? '', $mission->skin, $mission->variant),
            'question' => $question['text'], 'options' => $question['options'],
            'hidden' => !empty(regions::get((int) $stain->courseid)->battlestate)];
    }

    /**
     * The question of today to bring a page back: one of its knot, another one each day.
     *
     * @param \stdClass $stain
     * @param \stdClass $mission
     * @param int|null $now
     * @return array|null As questions::get().
     */
    private static function question(\stdClass $stain, \stdClass $mission, ?int $now = null): ?array {
        $ids = array_values((array) (self::knot($mission, (string) $stain->game)['questions'] ?? []));
        if (!$ids) {
            return null;
        }
        $day = self::day((int) $mission->courseid, $now ?? time());
        return questions::get((int) $mission->courseid, (string) $ids[$day % count($ids)], (int) $stain->id);
    }

    /**
     * Whether a student tried today to bring back a page.
     *
     * @param \stdClass $char
     * @param \stdClass $stain
     * @param \stdClass $mission
     * @param int|null $now
     * @return bool
     */
    public static function tried(\stdClass $char, \stdClass $stain, \stdClass $mission, ?int $now = null): bool {
        global $DB;
        return $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'pagetry',
            'code' => $stain->id . '-' . self::day((int) $mission->courseid, $now ?? time())]);
    }

    /**
     * A student answers the question of a page stolen, once a day: right, the page is back for the whole class, and who
     * brought it gets a gem and reads it with the why of its knot.
     *
     * @param int $courseid
     * @param int $stainid
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $answer Index of the option chosen.
     * @param int|null $now
     * @return array right, gems, title, text, why (the last three, once back).
     */
    public static function recover(
        int $courseid,
        int $stainid,
        \stdClass $char,
        \stdClass $member,
        int $answer,
        ?int $now = null
    ): array {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $stain = $DB->get_record(self::T . 'stain', ['id' => $stainid, 'courseid' => $courseid]);
        if (!$mission || !$stain || $stain->kind !== 'page' || (int) $stain->cleanedat) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        $question = self::question($stain, $mission, $now);
        if (!$question || self::tried($char, $stain, $mission, $now)) {
            throw new \moodle_exception('pagetried', 'local_oksigeniatesape');
        }
        $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
            'missionid' => $mission->id, 'kind' => 'pagetry', 'code' => $stain->id . '-' . self::day($courseid, $now),
            'timecreated' => $now]);
        if ($answer !== (int) $question['correct']) {
            return ['right' => false, 'gems' => 0, 'title' => '', 'text' => '', 'why' => ''];
        }
        // One brings it back: two at the same time do not both get the gem.
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniatesape')->get_lock('stain-' . $stainid, 5);
        if (!$lock) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        try {
            if ((int) $DB->get_field(self::T . 'stain', 'cleanedat', ['id' => $stainid])) {
                throw new \moodle_exception('nostain', 'local_oksigeniatesape');
            }
            $DB->set_field(self::T . 'stain', 'cleanedat', $now, ['id' => $stainid]);
        } finally {
            $lock->release();
        }
        $entry = engine::gems($member, self::GEMS, 'pageback', 0, 0, $stainid, $now);
        $page = $mission->data['pages'][$stain->sheet] ?? [];
        return ['right' => true, 'gems' => $entry ? (int) $entry->amount : 0,
            'title' => missions::text($page['title'] ?? '', $mission->skin, $mission->variant),
            'text' => missions::text($page['text'] ?? '', $mission->skin, $mission->variant),
            'why' => missions::text(self::knot($mission, (string) $stain->game)['why'] ?? '', $mission->skin, $mission->variant)];
    }

    /**
     * The day, as yyyymmdd in the time zone of the course.
     *
     * @param int $courseid
     * @param int $now
     * @return int
     */
    private static function day(int $courseid, int $now): int {
        return (int) (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid))->format('Ymd');
    }

    /**
     * The sheet of a stain, from the hunt for mistakes of the mission.
     *
     * @param \stdClass $stain
     * @param \stdClass $mission
     * @return array|null
     */
    private static function sheet(\stdClass $stain, \stdClass $mission): ?array {
        foreach ($mission->data['games'][$stain->game]['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') === (string) $stain->sheet) {
                return $sheet;
            }
        }
        return null;
    }

    /**
     * A student cleans a stain: every mistake touched and at most one word that was right. Clean, it is clean for the
     * whole class, and who cleaned it gets a gem and the complaint of the Embrollo.
     *
     * @param int $courseid
     * @param int $stainid
     * @param \stdClass $member
     * @param array $payload marks (the mistakes touched), misses (words right touched).
     * @param int|null $now
     * @return array clean, gems, complaint, solution (mark, right, why) once clean.
     */
    public static function clean(int $courseid, int $stainid, \stdClass $member, array $payload, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $stain = $DB->get_record(self::T . 'stain', ['id' => $stainid, 'courseid' => $courseid]);
        $sheet = $stain && $mission ? self::sheet($stain, $mission) : null;
        if (!$sheet || (int) $stain->cleanedat) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        // The knot of a team, only for its members.
        if ((int) $stain->groupid) {
            $userid = (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
            if ((int) (teams::of($courseid, $userid)->id ?? 0) !== (int) $stain->groupid) {
                throw new \moodle_exception('nostain', 'local_oksigeniatesape');
            }
        }
        $parts = minigames::parts(missions::text($sheet['text'] ?? '', $mission->skin, $mission->variant));
        $marks = array_values(array_filter($parts, fn($p) => $p['mark'] >= 0));
        $lane = ['engine' => 'errors', 'solution' => array_map(fn($p) => ['mark' => $p['mark'], 'right' => $p['right'],
            'why' => $p['why']], $marks)];
        if (!live::lane_right($lane, $payload)) {
            return ['clean' => false, 'gems' => 0, 'complaint' => '', 'solution' => []];
        }
        // One cleans it: two at the same time do not both get the gem.
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniatesape')->get_lock('stain-' . $stainid, 5);
        if (!$lock) {
            throw new \moodle_exception('nostain', 'local_oksigeniatesape');
        }
        try {
            if ((int) $DB->get_field(self::T . 'stain', 'cleanedat', ['id' => $stainid])) {
                throw new \moodle_exception('nostain', 'local_oksigeniatesape');
            }
            $DB->set_field(self::T . 'stain', 'cleanedat', $now, ['id' => $stainid]);
        } finally {
            $lock->release();
        }
        $entry = engine::gems($member, self::GEMS, 'stain', 0, 0, $stainid, $now);
        $complaints = (array) ($mission->data['stains']['complaints'] ?? []);
        $complaint = $complaints ? missions::text($complaints[$stainid % count($complaints)], $mission->skin, $mission->variant) :
            get_string('app_stain_complaint', 'local_oksigeniatesape');
        return ['clean' => true, 'gems' => $entry ? (int) $entry->amount : 0, 'complaint' => $complaint,
            'solution' => $lane['solution']];
    }

    /**
     * The footprints of the Embrollo on the way to the next place: one for each school day (Monday to Friday) since the
     * class lit its last lantern (or since the mission came, before the first), up to five. They go when the lantern of
     * the place of today is lit.
     *
     * @param \stdClass $mission
     * @param int|null $now
     * @return int
     */
    public static function prints(\stdClass $mission, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $region = regions::get((int) $mission->courseid);
        if (($region->mischief ?? 'calm') === 'off') {
            return 0;
        }
        $since = (int) $DB->get_field_select(self::T . 'chapter', 'MAX(litat)', 'missionid = ?', [$mission->id]);
        $since = $since ?: (int) $mission->timecreated;
        $zone = engine::timezone((int) $mission->courseid);
        $day = (new \DateTimeImmutable('@' . $since))->setTimezone($zone)->setTime(0, 0);
        $today = (new \DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
        $prints = 0;
        while ($day < $today && $prints < 5) {
            $day = $day->modify('+1 day');
            $prints += (int) $day->format('N') <= 5 ? 1 : 0;
        }
        return $prints;
    }

    /**
     * The teacher takes a stain as cleaned by the class (aloud, on the class screen) or rubs it out: nobody gets a gem.
     *
     * @param int $courseid
     * @param int $stainid
     * @param int|null $now
     */
    public static function erase(int $courseid, int $stainid, ?int $now = null): void {
        global $DB;
        $where = 'id = ? AND courseid = ? AND cleanedat = 0';
        $DB->set_field_select(self::T . 'stain', 'erased', 1, $where, [$stainid, $courseid]);
        $DB->set_field_select(self::T . 'stain', 'cleanedat', $now ?? time(), $where, [$stainid, $courseid]);
    }
}
