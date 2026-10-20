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
 * Playing the decision scenes of a mission: each play is kept for the teacher, and the first good ending of a scene
 * gives what it gives (once). Completing the scenes of a chapter lights its lantern; when half of the class has it,
 * the lantern of the class lights too.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scenes {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var bool A play is being kept again, after it ran into the one of a teammate. */
    private static $again = false;

    /** @var float Share of the class that lights the lantern of a chapter for everybody. */
    public const CLASS_LANTERN_SHARE = 0.5;

    /** @var int Last stage of the creature. */
    public const CREATURE_MAX_STAGE = 5;

    /** @var int The lanterns of a student with which the creature grows in the middle of the mission. */
    public const GROW_LANTERNS = 3;

    /** @var int Step whose scenes everybody sees where there are none for their own step. */
    public const CORE_STEP = 2;

    /**
     * A scene of the mission of a course.
     *
     * @param int $courseid
     * @param int $sceneid
     * @return \stdClass With the definition decoded in data.
     */
    public static function get(int $courseid, int $sceneid): \stdClass {
        global $DB;
        $scene = $DB->get_record_sql(
            'SELECT s.*, m.code AS missioncode
               FROM {' . self::T . 'scene} s
               JOIN {' . self::T . 'mission} m ON m.id = s.missionid
              WHERE s.id = ? AND m.courseid = ?',
            [$sceneid, $courseid],
            MUST_EXIST
        );
        // With the changes of the teacher on it.
        $scene->data = edits::apply(
            json_decode($scene->definition, true) ?: [],
            $courseid,
            (string) $scene->missioncode,
            (string) $scene->code
        );
        return $scene;
    }

    /**
     * A scene of the mission in course, to play it; with the free expedition, also one of a past expedition of the
     * course, played for pleasure (see free()).
     *
     * @param int $courseid
     * @param int $sceneid
     * @param bool $past Also a scene of a past expedition.
     * @return \stdClass With the definition decoded in data.
     */
    public static function playable(int $courseid, int $sceneid, bool $past = false): \stdClass {
        $scene = self::get($courseid, $sceneid);
        $mission = missions::get($courseid);
        $off = edits::off($courseid, (string) $scene->missioncode);
        $current = $mission && (int) $scene->missionid === (int) $mission->id;
        if (!$mission || (!$current && !$past) || in_array((string) $scene->code, $off, true)) {
            throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
        }
        return $scene;
    }

    /**
     * Whether a scene is played in the free expedition: it is of a past expedition of the course. As a quest, it gives
     * gems, pages and memory of its own expedition, never saber, lanterns, items or anything of the creature.
     *
     * @param int $courseid
     * @param \stdClass $scene
     * @return bool
     */
    public static function free(int $courseid, \stdClass $scene): bool {
        $mission = missions::get($courseid);
        return $mission && (int) $scene->missionid !== (int) $mission->id;
    }

    /**
     * The scenes still in the game: without those the teachers of the course took out in the workshop.
     *
     * @param \stdClass[] $scenes Of one mission.
     * @return \stdClass[]
     */
    public static function switched_on(array $scenes): array {
        global $DB;
        $first = reset($scenes);
        if (!$first || empty($first->missionid)) {
            return $scenes;
        }
        $mission = $DB->get_record(self::T . 'mission', ['id' => (int) $first->missionid], 'id, courseid, code');
        if (!$mission) {
            return $scenes;
        }
        $off = edits::off((int) $mission->courseid, (string) $mission->code);
        return $off ? array_filter($scenes, fn($s) => !in_array((string) $s->code, $off, true)) : $scenes;
    }

    /**
     * The scenes of a chapter a student plays: those of their step, or the core ones where there are none for it. Not
     * the quests.
     *
     * @param \stdClass[] $scenes Scenes of the chapter.
     * @param int $step
     * @return \stdClass[]
     */
    public static function for_step(array $scenes, int $step): array {
        // Quests are apart: open to everybody, they are not anybody's route. Nor what the teacher took out of the game.
        $scenes = array_filter(self::switched_on($scenes), fn($s) => empty($s->quest));
        $mine = array_filter($scenes, fn($s) => (int) $s->step === $step || (int) $s->step === 0);
        if (!$mine) {
            $mine = array_filter($scenes, fn($s) => (int) $s->step === self::CORE_STEP);
        }
        return array_values($mine);
    }

    /**
     * The quests of a chapter: scenes of extension and expert, open to everybody, that give gems and pages, not saber.
     *
     * @param \stdClass[] $scenes Scenes of the chapter.
     * @return \stdClass[]
     */
    public static function quests(array $scenes): array {
        $quests = array_values(array_filter(self::switched_on($scenes), fn($s) => !empty($s->quest)));
        usort($quests, fn($a, $b) => [(int) $a->step, $a->code] <=> [(int) $b->step, $b->code]);
        return $quests;
    }

    /**
     * What the map shows of each scene of a mission, with the changes of the teacher: its title, minutes, how it can be
     * played and its good endings. Read from the definitions once and kept: the map asks for it in every request.
     *
     * @param \stdClass $mission
     * @return array sceneid => [title, minutes, mode, best (the endings with a good outcome)].
     */
    public static function summaries(\stdClass $mission): array {
        global $DB;
        $edits = edits::of((int) $mission->courseid, (string) $mission->code);
        $titles = array_map(fn($e) => $e['title'] ?? '', $edits);
        $key = implode('_', [$mission->id, $mission->timemodified, $mission->version, $mission->skin, $mission->variant,
            md5(json_encode($titles))]);
        $cache = \cache::make('local_oksigeniatesape', 'scenes');
        $list = $cache->get($key);
        if ($list === false) {
            $list = [];
            foreach ($DB->get_records(self::T . 'scene', ['missionid' => $mission->id]) as $scene) {
                $def = edits::scene($scene, $mission);
                $best = array_keys(array_filter($def['nodes'] ?? [], fn($n) => ($n['type'] ?? '') === 'ending'
                    && ($n['outcome'] ?? 'success') === 'success'));
                $list[(int) $scene->id] = [
                    'title' => (string) ($def['title'] ?? $scene->code),
                    'minutes' => (int) ($def['minutes'] ?? 0),
                    'mode' => in_array($def['mode'] ?? '', ['solo', 'team'], true) ? $def['mode'] : 'both',
                    'best' => array_map('strval', $best),
                    'pages' => self::pages_given($def),
                    // A scene only while the Embrollo is out after a battle lost (lost), or always ('').
                    'after' => (string) ($def['after'] ?? ''),
                ];
            }
            $cache->set($key, $list);
        }
        return $list;
    }

    /**
     * The pages of the Gran Libro a scene gives, and who can get each: anybody (''), or only one role, when the
     * only way to it is an option of that role.
     *
     * @param array $def The scene.
     * @return array Page => roles that can get it ('' for anybody).
     */
    private static function pages_given(array $def): array {
        $pages = [];
        $add = function (array $effects, string $role) use (&$pages) {
            foreach ($effects as $effect) {
                if (isset($effect['page'])) {
                    $pages[(string) $effect['page']][$role] = true;
                }
            }
        };
        foreach ($def['nodes'] ?? [] as $node) {
            $add($node['effects'] ?? [], '');
            foreach ($node['options'] ?? [] as $option) {
                $add($option['effects'] ?? [], (string) ($option['requires']['role'] ?? ''));
            }
            foreach ($node['items'] ?? [] as $item) {
                $add($item['effects'] ?? [], '');
            }
        }
        return array_map('array_keys', $pages);
    }

    /**
     * The first try of a student at each scene they played: what they knew before the scene told them. A team play that
     * a teammate made on their device does not count as theirs.
     *
     * @param int $courseid
     * @param int $charid
     * @return \stdClass[] Attempts, with id, sceneid and objectives.
     */
    public static function first_tries(int $courseid, int $charid): array {
        global $DB;
        $own = '(%1$s.teamplay = 0 OR %1$s.teamplay = %1$s.id)';
        return $DB->get_records_sql(
            'SELECT a.id, a.sceneid, a.objectives
               FROM {' . self::T . 'sceneattempt} a
              WHERE a.charid = ? AND a.courseid = ? AND ' . sprintf($own, 'a') . '
                AND a.attempt = (SELECT MIN(b.attempt)
                                   FROM {' . self::T . 'sceneattempt} b
                                  WHERE b.charid = a.charid AND b.sceneid = a.sceneid AND ' . sprintf($own, 'b') . ')',
            [$charid, $courseid]
        );
    }

    /**
     * What a student brings to a scene: the items they carry in the course and the roles of their team.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int|null $missionid The expedition whose memory counts (null: the one in course).
     * @return array [item codes, roles of the team, flags of the mission (what they did in other scenes), own role]
     */
    public static function context(\stdClass $char, int $courseid, ?int $missionid = null): array {
        global $DB;
        $items = $DB->get_fieldset_sql(
            'SELECT i.code
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ?',
            [$char->id, $courseid]
        );
        $roles = [$char->role];
        $region = regions::get($courseid);
        $groups = groups_get_all_groups($courseid, $char->userid, (int) ($region->groupingid ?? 0));
        if ($groups) {
            $group = reset($groups);
            $mates = array_keys(groups_get_members($group->id, 'u.id'));
            if ($mates) {
                [$insql, $params] = $DB->get_in_or_equal($mates);
                $roles = array_merge($roles, $DB->get_fieldset_select(self::T . 'char', 'role', "userid $insql", $params));
            }
            // With the trade of the day, what their team lent goes, and what it was lent comes.
            $items = events::traded($courseid, (int) $group->id, $items);
        }
        $missionid = $missionid ?? (int) (missions::get($courseid)->id ?? 0);
        $flags = [];
        if ($missionid) {
            $where = "charid = ? AND missionid = ? AND kind = 'flag'";
            $flags = $DB->get_fieldset_select(self::T . 'found', 'code', $where, [$char->id, $missionid]);
        }
        // The creature, once hatched, goes with the hero as one more thing they carry: it can sniff in the scenes.
        if ((int) ($char->creaturestage ?? 0) >= 1) {
            $items[] = 'creature';
        }
        return [$items, array_values(array_unique($roles)), array_values($flags), (string) $char->role];
    }

    /**
     * Whether a student plays a scene with the aids of the first steps: they are on the first step and the scene was not
     * written for it (a core one, or one for everybody). The lens is free and the hard items of a sorting stay out.
     *
     * @param \stdClass $member
     * @param \stdClass $scene
     * @return bool
     */
    public static function aided(\stdClass $member, \stdClass $scene): bool {
        return (int) $member->step < self::CORE_STEP && (int) $scene->step !== (int) $member->step;
    }

    /**
     * The shield of the protector in a play: two mistakes with «Long shield».
     *
     * @param \stdClass $char Who plays.
     * @param int $courseid
     * @return int
     */
    public static function shields(\stdClass $char, int $courseid): int {
        if ($char->role !== 'protector') {
            return 0;
        }
        return talents::has((int) $char->id, 'longshield', $courseid) ? 2 : 1;
    }

    /**
     * Whether someone of those who play has a talent for the team, which acts in the course.
     *
     * @param \stdClass[] $chars
     * @param string $code
     * @param int $courseid
     * @return bool
     */
    public static function gift(array $chars, string $code, int $courseid): bool {
        foreach ($chars as $char) {
            if (talents::has((int) $char->id, $code, $courseid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The hints opened in a play cost breath: one each; the sage gets the first free and pays half for the rest. The day
     * the messengers of another team bring one, the first is free too; and in a play as a team, from the emblem of its
     * banner, one more.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $sceneid
     * @param int $hints Hints opened.
     * @param int $now
     * @param bool $free Played with the aids of the first steps.
     * @param bool $together Played as a team: from the emblem of its banner, the first hint is free.
     * @return int Breath paid.
     */
    private static function pay_hints(
        \stdClass $char,
        \stdClass $member,
        int $sceneid,
        int $hints,
        int $now,
        bool $free = false,
        bool $together = false
    ): int {
        // With «more help» or the aids of the first steps, the hints are free.
        $free = $free || adapt::has($member, 'hints');
        // The sage: the first free (the first two with «Hints of the house») and half for the rest.
        $gift = talents::has((int) $char->id, 'househints', (int) $member->courseid) ? 2 : 1;
        $team = teams::of((int) $member->courseid, (int) $char->userid);
        $brought = $team && events::brought((int) $member->courseid, (int) $team->id, $now) ? 1 : 0;
        $brought += $team && $together ? banners::free_hints((int) $member->courseid, (int) $team->id) : 0;
        $cost = $free ? 0 : ($char->role === 'sage' ? (int) ceil(max(0, $hints - $gift - $brought) / 2) :
            max(0, $hints - $brought));
        $fresh = engine::member($char, (int) $member->courseid, $now);
        $cost = min($cost, (int) $fresh->breath);
        if ($cost > 0) {
            engine::breath($fresh, -$cost, 'hint', 0, $sceneid, $now);
        }
        return $cost;
    }

    /**
     * The healer, when they finish a scene well: one breath for the teammate who has least.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $sceneid
     * @param int $now
     * @return string Name of the teammate, as classmates see it (empty: nobody needed it).
     */
    private static function care(\stdClass $char, \stdClass $member, int $sceneid, int $now): string {
        $group = teams::of((int) $member->courseid, (int) $char->userid);
        $least = null;
        foreach ($group ? teams::members((int) $member->courseid, (int) $group->id) : [] as $mate) {
            $other = (int) $mate->charid !== (int) $char->id;
            if ($other && $mate->breath < engine::BREATH_MAX && (!$least || $mate->breath < $least->breath)) {
                $least = $mate;
            }
        }
        if (!$least) {
            return '';
        }
        $userid = (int) $least->userid;
        unset($least->userid);
        // «Double care»: two.
        $care = talents::has((int) $char->id, 'doublecare', (int) $member->courseid) ? 2 : 1;
        engine::breath($least, $care, 'care', (int) $char->userid, $sceneid, $now);
        $user = \core_user::get_user($userid);
        $region = regions::get((int) $member->courseid);
        return $user && $region ? view::display_name($user, characters::get($userid), $region) : '';
    }

    /**
     * The teammates who play a scene with a student on the same device: only those of their team who play in the course,
     * with their character, their place in the course and the name their classmates see.
     *
     * @param int $courseid
     * @param int $userid The student who plays.
     * @param int[] $userids The teammates the student marked as there.
     * @param int $now
     * @param int[] $joined Those whose own tablet was in the team session of the student: there, also from far.
     * @return array Of ['char', 'member', 'name'].
     */
    private static function mates(int $courseid, int $userid, array $userids, int $now, array $joined = []): array {
        global $DB;
        $team = teams::of($courseid, $userid);
        if (!$team) {
            return [];
        }
        $region = regions::get($courseid);
        $members = teams::members($courseid, (int) $team->id);
        $mates = [];
        foreach (array_unique(array_map('intval', $userids)) as $id) {
            if ($id === $userid || !isset($members[$id])) {
                continue;
            }
            $char = characters::get($id);
            $user = $DB->get_record('user', ['id' => $id], '*', IGNORE_MISSING);
            $member = $char && $user ? engine::member($char, $courseid, $now) : null;
            // Who follows the expedition from far is not at this device, unless their own tablet was in the team session;
            // who rests does not play.
            $here = empty($member->remote) || in_array($id, $joined, true);
            if ($member && $here && $member->status !== 'paused') {
                $mates[] = ['char' => $char, 'member' => $member, 'name' => view::display_name($user, $char, $region)];
            }
        }
        return $mates;
    }

    /**
     * Plays a scene: plays it again with the events of the student, keeps the play and gives what it gives. Played as a
     * team on one device, it counts for the teammates who were there too: the team has what any of them carries, and each
     * one gets the play, its evidence and what it gives.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $sceneid
     * @param array $events
     * @param string|null $from Where the play started (null: the start of the scene).
     * @param int|null $now
     * @param int[] $mates The teammates who played it too (none: the student alone).
     * @param string $playid The mark the device made for this play (empty: none): sent again, it counts once.
     * @return array ending, outcome, points, objectives, rewards, attempt, teamwith (the names of the teammates).
     */
    public static function play(
        int $courseid,
        int $userid,
        int $sceneid,
        array $events,
        ?string $from,
        ?int $now = null,
        array $mates = [],
        string $playid = ''
    ): array {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $courseid, $now);
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $scene = self::playable($courseid, $sceneid, true);
        if ($playid !== '') {
            $done = $DB->get_record(self::T . 'sceneattempt', ['charid' => $char->id, 'sceneid' => $scene->id,
                'playid' => $playid]);
            if ($done) {
                // The same play, sent again because the answer was lost on the way: it was counted already.
                $node = $scene->data['nodes'][$done->ending] ?? [];
                return ['ending' => (string) $done->ending, 'outcome' => $node['outcome'] ?? 'success',
                    'retry' => (string) ($node['retry'] ?? ''), 'points' => (int) $done->score,
                    'objectives' => json_decode((string) $done->objectives, true) ?: [], 'rewards' => [],
                    'attempt' => (int) $done->attempt, 'teamwith' => []];
            }
        }
        // A play starts where the scene starts, or where a retry ending of the last play of this student sent back.
        if ($from !== null && $from !== (string) ($scene->data['start'] ?? '')) {
            $mine = ['sceneid' => $scene->id, 'charid' => $char->id];
            $last = $DB->get_records(self::T . 'sceneattempt', $mine, 'id DESC', 'id, ending', 0, 1);
            $last = reset($last);
            $ending = $last ? ($scene->data['nodes'][$last->ending] ?? []) : [];
            if ((string) ($ending['retry'] ?? '') !== $from) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
        }
        // The memory of the expedition of the scene: a past one, in the free expedition.
        [$items, $roles, $flags] = self::context($char, $courseid, (int) $scene->missionid);
        // A scene only for one cannot be played as a team.
        $team = $mates && ($scene->data['mode'] ?? 'both') !== 'solo' ? self::mates(
            $courseid,
            (int) $userid,
            $mates,
            $now,
            huddles::players($courseid, $char, $now)
        ) : [];
        foreach ($team as $mate) {
            $items = array_merge($items, self::context($mate['char'], $courseid)[0]);
        }
        $items = array_values(array_unique($items));
        $runner = new scene_runner($scene->data, $items, $roles, $flags, $char->role);
        $runner->aided(self::aided($member, $scene))->shields(self::shields($char, $courseid));
        // What teammates with a talent for the team bring to a play as a team: their guard, and their trail.
        $players = array_merge([$char], array_map(fn($p) => $p['char'], $team));
        if ($team && self::gift($players, 'teamguard', $courseid)) {
            $runner->team_guard(array_map(fn($c) => (int) $c->userid, $players));
        }
        if ($team && self::gift(array_map(fn($p) => $p['char'], $team), 'sharedtrail', $courseid)) {
            $runner->lend(['scout']);
        }
        $result = $runner->run($events, $from);

        $transaction = $DB->start_delegated_transaction();
        try {
            [$attempt, $rewards] = self::keep($courseid, $char, $member, $team, $scene, $result, $events, $from, $playid, $now);
            $transaction->allow_commit();
        } catch (\dml_write_exception $e) {
            // Two teammates who played it together, each on their device, finishing at the same moment: what one wrote for
            // the other was already there. Once more, now that it is.
            try {
                $transaction->rollback($e);
            } catch (\dml_write_exception $e) {
                if (self::$again) {
                    throw $e;
                }
            }
            self::$again = true;
            try {
                return self::play($courseid, $userid, $sceneid, $events, $from, $now, $mates, $playid);
            } finally {
                self::$again = false;
            }
        }
        return [
            'ending' => $result['ending'],
            'outcome' => $result['outcome'],
            'retry' => (string) $result['retry'],
            'points' => $result['points'],
            'objectives' => $result['objectives'],
            'rewards' => $rewards,
            'attempt' => (int) $attempt->attempt,
            'teamwith' => array_column($team, 'name'),
        ];
    }

    /**
     * Keeps a play, for the student and the teammates who played it too, and gives what it gives.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param \stdClass $member
     * @param array $team The teammates, with char and member.
     * @param \stdClass $scene
     * @param array $result What the runner said.
     * @param array $events
     * @param string|null $from
     * @param string $playid
     * @param int $now
     * @return array [the attempt of the student, what they got]
     */
    private static function keep(
        int $courseid,
        \stdClass $char,
        \stdClass $member,
        array $team,
        \stdClass $scene,
        array $result,
        array $events,
        ?string $from,
        string $playid,
        int $now
    ): array {
        global $DB;
        $rewards = [];
        $attempt = null;
        // Who of the team took each decision stays with the play, only if they were in it.
        $players = array_map('intval', array_merge([$char->userid], array_map(fn($p) => $p['char']->userid, $team)));
        foreach ($events as $i => $event) {
            if (isset($event['by']) && (!$team || !in_array((int) $event['by'], $players, true))) {
                unset($events[$i]['by']);
            }
        }
        // A team game: the same number in the play of each one, the one of the play of who played it.
        $game = 0;
        foreach (array_merge([['char' => $char, 'member' => $member]], $team) as $i => $player) {
            $one = (object) [
                'sceneid' => $scene->id,
                'charid' => $player['char']->id,
                'courseid' => $courseid,
                'teamplay' => $game,
                'playid' => !$i && $playid !== '' ? $playid : null,
                'path' => json_encode(['from' => $from, 'events' => $events, 'path' => $result['path']]),
                'score' => $result['points'],
                'objectives' => json_encode($result['objectives']),
                'ending' => $result['ending'],
                'attempt' => $DB->count_records(self::T . 'sceneattempt', ['sceneid' => $scene->id,
                    'charid' => $player['char']->id]) + 1,
                'timestarted' => $now,
                'timefinished' => $now,
            ];
            $one->id = $DB->insert_record(self::T . 'sceneattempt', $one);
            if ($team && !$game) {
                $game = (int) $one->id;
                $DB->set_field(self::T . 'sceneattempt', 'teamplay', $game, ['id' => $one->id]);
            }
            // The competencies of the objectives get the evidence of the play, marked if it was a team one; the teacher
            // rates.
            $userid = (int) $player['char']->userid;
            competencies::evidence($courseid, $userid, $scene, $result['objectives'], (int) $one->attempt, (bool) $team);
            $got = [];
            if (in_array($result['outcome'], ['success', 'partial'], true)) {
                $got = self::reward($player['char'], $player['member'], $scene, $result, $now);
            }
            if (!$i) {
                // What is said back is for the one who played: the teammates see theirs in their own world.
                $attempt = $one;
                $rewards = $got;
            }
        }
        if (!empty($rewards['saber']) && $char->role === 'healer') {
            $rewards['cared'] = self::care($char, $member, (int) $scene->id, $now);
        }
        // The rescue for two: the healer who plays a scene with a worn-out teammate pays no hints and, if it goes well,
        // brings them back.
        $fallen = $char->role === 'healer' ? array_values(array_filter($team, fn($p) => $p['member']->status === 'fallen')) : [];
        if ($fallen && in_array($result['outcome'], ['success', 'partial'], true)) {
            $rewards['rescued'] = self::rescue($courseid, $char, $fallen, (int) $scene->id, $now);
        }
        $free = self::aided($member, $scene) || $fallen;
        $together = (bool) $team;
        $rewards['hints'] = self::pay_hints($char, $member, (int) $scene->id, (int) $result['hints'], $now, $free, $together);
        $rewards['shielded'] = (bool) $result['shielded'];
        return [$attempt, $rewards];
    }

    /**
     * The healer brings back the worn-out teammates who played a scene well with them, and the team gets the saber of a
     * rescue, as with the power «Revive».
     *
     * @param int $courseid
     * @param \stdClass $char The healer.
     * @param array $fallen The worn-out teammates, with char, member and name.
     * @param int $sceneid
     * @param int $now
     * @return string[] The names of those brought back.
     */
    private static function rescue(int $courseid, \stdClass $char, array $fallen, int $sceneid, int $now): array {
        $names = [];
        foreach ($fallen as $p) {
            if (engine::recover($p['member'], 'rescue', (int) $char->userid, $now)) {
                $names[] = $p['name'];
            }
        }
        $group = $names ? teams::of($courseid, (int) $char->userid) : null;
        foreach ($group ? teams::members($courseid, (int) $group->id) : [] as $mate) {
            unset($mate->userid);
            engine::saber($mate, powers::RESCUE_SABER, 'rescue', (int) $char->userid, 0, $sceneid, $now);
        }
        return $names;
    }

    /**
     * What the whole class brings to a scene played together: the items any of its players carries, and their roles.
     *
     * @param int $courseid
     * @return array [item codes, roles]
     */
    public static function class_context(int $courseid): array {
        global $DB;
        $items = $DB->get_fieldset_sql(
            'SELECT DISTINCT i.code
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.courseid = ?',
            [$courseid]
        );
        $roles = $DB->get_fieldset_sql(
            'SELECT DISTINCT c.role
               FROM {' . self::T . 'char} c
               JOIN {' . self::T . 'member} m ON m.charid = c.id
              WHERE m.courseid = ? AND m.status <> ?',
            [$courseid, 'paused']
        );
        return [array_values($items), array_values($roles)];
    }

    /**
     * The marks of a mission the class carries: those that at least half of the students who play left (on the class
     * screen, the memory of a scene is that of the class; at the end, the Great Book tells it).
     *
     * @param int $courseid
     * @param int $missionid
     * @return string[]
     */
    public static function class_flags(int $courseid, int $missionid): array {
        global $DB;
        $where = 'courseid = ? AND leftat = 0 AND status <> ?';
        $players = $DB->count_records_select(self::T . 'member', $where, [$courseid, 'paused']);
        if (!$players) {
            return [];
        }
        $marks = $DB->get_records_sql_menu(
            "SELECT f.code, COUNT(DISTINCT f.charid)
               FROM {" . self::T . "found} f
               JOIN {" . self::T . "member} m ON m.charid = f.charid AND m.courseid = ?
              WHERE f.missionid = ? AND f.kind = 'flag' AND m.leftat = 0 AND m.status <> ?
           GROUP BY f.code",
            [$courseid, $missionid, 'paused']
        );
        return array_values(array_map('strval', array_keys(array_filter($marks, fn($n) => 2 * (int) $n >= $players))));
    }

    /**
     * A scene played by the whole class on the class screen: the server plays it again with the decisions of the class
     * (which has what any of its players carries, and their roles), and each player gets what the scene gives that they
     * did not have yet. It is not kept as anybody's own try, so that what the teacher sees of each student stays theirs.
     *
     * @param int $courseid
     * @param int $sceneid
     * @param array $events
     * @param string|null $from Where the play started (null: the start of the scene).
     * @param int[] $charids The players; none: everybody who plays in the course.
     * @param int|null $now
     * @param array|null $votes What the phones voted (live::votes()); null: played without phones.
     * @return array ending, outcome, retry, points, objectives, rewarded (players who got something).
     */
    public static function play_class(
        int $courseid,
        int $sceneid,
        array $events,
        ?string $from,
        array $charids,
        ?int $now = null,
        ?array $votes = null
    ): array {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $scene = self::playable($courseid, $sceneid);
        [$items, $roles] = self::class_context($courseid);
        $members = array_filter(
            $DB->get_records(self::T . 'member', ['courseid' => $courseid]),
            fn($m) => $m->status !== 'paused' && empty($m->leftat) && (!$charids || in_array((int) $m->charid, $charids, true))
        );
        $chars = [];
        foreach ($members as $member) {
            $chars[$member->id] = $DB->get_record(self::T . 'char', ['id' => $member->charid], '*', MUST_EXIST);
        }
        $flags = self::class_flags($courseid, (int) $scene->missionid);
        $runner = new scene_runner($scene->data, $items, $roles, $flags, '', true);
        $result = $runner->run($events, $from);
        // What the class chose wrong goes to what confuses the class, as nobody's try: with phones, how many voted each
        // wrong option; without them, once, the choice of the class.
        $counts = $votes !== null ? results::voted($scene->data, $votes) : results::mixups($scene->data, $events);
        if ($counts) {
            $DB->insert_record(self::T . 'classplay', (object) ['courseid' => $courseid, 'sceneid' => $scene->id,
                'counts' => json_encode($counts), 'timecreated' => $now]);
        }
        $rewarded = 0;
        if (in_array($result['outcome'], ['success', 'partial'], true)) {
            $transaction = $DB->start_delegated_transaction();
            foreach ($members as $member) {
                $got = self::reward($chars[$member->id], $member, $scene, $result, $now);
                if ($got['saber'] || $got['gems'] || $got['items'] || $got['lanterns'] || $got['pages'] || $got['hatched']) {
                    $rewarded++;
                }
            }
            $transaction->allow_commit();
        }
        return [
            'ending' => $result['ending'],
            'outcome' => $result['outcome'],
            'retry' => (string) $result['retry'],
            'points' => $result['points'],
            'objectives' => $result['objectives'],
            'rewarded' => $rewarded,
        ];
    }

    /**
     * Gives what a good ending gives, only what was not given yet for this scene.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass $scene
     * @param array $result Of the runner.
     * @param int $now
     * @return array What was given.
     */
    private static function reward(\stdClass $char, \stdClass $member, \stdClass $scene, array $result, int $now): array {
        global $DB;
        $mission = $DB->get_record(self::T . 'mission', ['id' => $scene->missionid], '*', MUST_EXIST);
        $rewards = ['saber' => 0, 'capped' => 0, 'gems' => 0, 'items' => [], 'lanterns' => [], 'pages' => [],
            'hatched' => false, 'grew' => false, 'chapter' => '', 'classlantern' => ''];
        $target = ['saber' => 0, 'gems' => 0];
        $effects = array_merge($result['effects'], $result['inline']);
        // A scene of a past expedition is played freely: as a quest.
        $quest = !empty($scene->quest) || self::free((int) $member->courseid, $scene);
        if ($quest) {
            // A quest is for whoever wants more: gems, pages and memory, never saber, lanterns, items or the creature.
            $effects = array_map(fn($e) => array_intersect_key($e, ['gems' => 1, 'page' => 1, 'flag' => 1]), $effects);
        }
        foreach ($effects as $effect) {
            foreach (['saber', 'gems'] as $resource) {
                if (isset($effect[$resource])) {
                    $target[$resource] += (int) $effect[$resource];
                }
            }
        }
        // Saber and gems: what is missing up to what this ending gives (what a cap left out counts as given).
        foreach (['saber', 'gems'] as $resource) {
            $given = (int) $DB->get_field_sql(
                'SELECT COALESCE(SUM(amount + capped), 0) FROM {' . self::T . "ledger}
                  WHERE charid = ? AND courseid = ? AND resource = ? AND reason = 'scene' AND refid = ? AND undoneby = 0",
                [$char->id, $member->courseid, $resource, $scene->id]
            );
            $missing = $target[$resource] - $given;
            if ($missing > 0) {
                $entry = engine::$resource($member, $missing, 'scene', 0, 0, (int) $scene->id, $now);
                if ($entry) {
                    $rewards[$resource] = (int) $entry->amount;
                    if ($resource === 'saber') {
                        $rewards['capped'] = (int) $entry->capped;
                    }
                }
            }
        }
        foreach ($effects as $effect) {
            if (isset($effect['item'])) {
                $itemid = missions::item_id((string) $effect['item']);
                if (engine::item($member, $itemid, 'scene', 0, 0, $now)) {
                    $rewards['items'][] = (string) $effect['item'];
                }
            }
            if (isset($effect['lantern']) && self::found($char, $member, $mission, 'lantern', (string) $effect['lantern'], $now)) {
                $rewards['lanterns'][] = (string) $effect['lantern'];
            }
            if (isset($effect['page']) && self::found($char, $member, $mission, 'page', (string) $effect['page'], $now)) {
                $rewards['pages'][] = (string) $effect['page'];
            }
            if (isset($effect['flag'])) {
                // Memory of the mission: what they did here changes other scenes.
                self::found($char, $member, $mission, 'flag', (string) $effect['flag'], $now);
            }
            if (isset($effect['unflag'])) {
                // And what is undone: the creature born asleep wakes up when the nest is made well.
                $DB->delete_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'flag',
                    'code' => (string) $effect['unflag']]);
            }
            if (isset($effect['breath']) && self::found($char, $member, $mission, 'breath', $scene->code, $now)) {
                engine::breath($member, (int) $effect['breath'], 'scene', 0, (int) $scene->id, $now);
            }
            if (($effect['creature'] ?? '') === 'hatch' && empty($char->creature)) {
                $char->creature = json_encode(self::creature($char));
                $char->creaturestage = 1;
                $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => $char->creature,
                    'creaturestage' => 1, 'timemodified' => $now]);
                $rewards['hatched'] = true;
            }
            if (($effect['creature'] ?? '') === 'grow' && self::found($char, $member, $mission, 'grow', $scene->code, $now)) {
                $stage = (int) $DB->get_field(self::T . 'char', 'creaturestage', ['id' => $char->id]);
                $DB->set_field(
                    self::T . 'char',
                    'creaturestage',
                    min(self::CREATURE_MAX_STAGE, max(1, $stage + 1)),
                    ['id' => $char->id]
                );
                $rewards['grew'] = true;
            }
        }
        self::found($char, $member, $mission, 'scene', $scene->code, $now);
        // The chapter: done when its scenes for the student's step are done (quests do not count).
        $chapter = $quest ? null : $DB->get_record(self::T . 'chapter', ['id' => $scene->chapterid]);
        if ($chapter && self::chapter_done($char, (int) $member->step, $chapter)) {
            if (self::found($char, $member, $mission, 'chapter', $chapter->code, $now)) {
                $rewards['chapter'] = $chapter->code;
                $data = json_decode((string) $mission->definition, true) ?: [];
                // The lantern of the place of the final battle is lit by the battle, not by its scenes.
                $battle = $chapter->code === ($data['battle']['final']['lantern'] ?? null);
                if (!$battle && self::found($char, $member, $mission, 'lantern', $chapter->code, $now)) {
                    $rewards['lanterns'][] = $chapter->code;
                }
                foreach ($data['chapters'] ?? [] as $info) {
                    if ($info['code'] === $chapter->code && !empty($info['reward'])) {
                        $itemid = missions::item_id((string) $info['reward']);
                        if (engine::item($member, $itemid, 'scene', 0, 0, $now)) {
                            $rewards['items'][] = (string) $info['reward'];
                        }
                    }
                }
            }
        }
        foreach (array_unique($rewards['lanterns']) as $code) {
            if (self::class_lantern($mission, $code, $now)) {
                $rewards['classlantern'] = $code;
            }
        }
        $rewards['lanterns'] = array_values(array_unique($rewards['lanterns']));
        // With their third lantern, the creature grows in the middle of the mission (unless a minigame did it first).
        $lanterns = $rewards['lanterns'] ? $DB->count_records(self::T . 'found', ['charid' => $char->id,
            'missionid' => $mission->id, 'kind' => 'lantern']) : 0;
        if ($lanterns >= self::GROW_LANTERNS && self::grow_mid($char, $member, $mission, $now)) {
            $rewards['grew'] = true;
        }
        if ($rewards['lanterns']) {
            // The last lantern may give the badge of the mission.
            badges::check((int) $member->courseid, (int) $char->userid);
        }
        return $rewards;
    }

    /**
     * Whether a student has done the scenes of a chapter: those of their step, or those of the core. Whoever has played
     * the core (with the class on the class screen, for instance) has done the chapter, whatever their step.
     *
     * @param \stdClass $char
     * @param int $step
     * @param \stdClass $chapter
     * @return bool
     */
    public static function chapter_done(\stdClass $char, int $step, \stdClass $chapter): bool {
        global $DB;
        $all = array_values($DB->get_records(self::T . 'scene', ['chapterid' => $chapter->id]));
        $found = $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            'charid = ? AND missionid = ? AND kind = ?',
            [$char->id, $chapter->missionid, 'scene']
        );
        foreach (array_unique([$step, self::CORE_STEP]) as $route) {
            $scenes = self::for_step($all, $route);
            if ($scenes && !array_diff(array_map(fn($s) => $s->code, $scenes), $found)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Notes that a character found something in a mission.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass $mission
     * @param string $kind
     * @param string $code
     * @param int $now
     * @return bool Whether it is new.
     */
    private static function found(
        \stdClass $char,
        \stdClass $member,
        \stdClass $mission,
        string $kind,
        string $code,
        int $now
    ): bool {
        global $DB;
        $key = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => $kind, 'code' => $code];
        if ($DB->record_exists(self::T . 'found', $key)) {
            return false;
        }
        $DB->insert_record(self::T . 'found', (object) ($key + ['courseid' => $member->courseid, 'timecreated' => $now]));
        return true;
    }

    /**
     * The creature grows once in the middle of the mission: with the third lantern of the student or the first minigame
     * they win, whichever comes first. Not before it hatches, and never past its last stage.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass $mission
     * @param int $now
     * @return bool Whether it grew now.
     */
    public static function grow_mid(\stdClass $char, \stdClass $member, \stdClass $mission, int $now): bool {
        global $DB;
        $stage = (int) $DB->get_field(self::T . 'char', 'creaturestage', ['id' => $char->id]);
        if ($stage < 1 || $stage >= self::CREATURE_MAX_STAGE || !self::found($char, $member, $mission, 'grow', 'mid', $now)) {
            return false;
        }
        $DB->set_field(self::T . 'char', 'creaturestage', $stage + 1, ['id' => $char->id]);
        $char->creaturestage = $stage + 1;
        return true;
    }

    /**
     * Lights the lantern of a chapter for the class when half of those who play have it. During a session it waits
     * ready instead, for the teacher to light it with the class (sessions::light()).
     *
     * @param \stdClass $mission
     * @param string $code
     * @param int $now
     * @return bool Whether it lit now.
     */
    private static function class_lantern(\stdClass $mission, string $code, int $now): bool {
        global $DB;
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => $code]);
        if (!$chapter || $chapter->litat) {
            return false;
        }
        $where = "courseid = ? AND status <> 'paused' AND leftat = 0";
        $players = $DB->count_records_select(self::T . 'member', $where, [$mission->courseid]);
        $lit = $DB->count_records(self::T . 'found', ['missionid' => $mission->id, 'kind' => 'lantern', 'code' => $code]);
        if (!$players || $lit < $players * self::CLASS_LANTERN_SHARE) {
            return false;
        }
        if (sessions::open((int) $mission->courseid)) {
            if (!$chapter->readyat) {
                $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'readyat' => $now, 'timemodified' => $now]);
            }
            return false;
        }
        $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'litat' => $now, 'timemodified' => $now]);
        return true;
    }

    /**
     * The traits of a creature, drawn from the character so that it is always the same and different from others.
     *
     * @param \stdClass $char
     * @return array
     */
    public static function creature(\stdClass $char): array {
        $h = crc32('tesape-creature-' . $char->id . '-' . $char->userid);
        $syllables = ['Ti', 'Ru', 'Mo', 'Ka', 'Lu', 'Pa', 'Ñe', 'Yu', 'Bo', 'Sa', 'Ri', 'Na', 'Po', 'Ma', 'Ki', 'Je'];
        return [
            'hue' => $h % 360,
            'ears' => ($h >> 9) % 3,
            'eyes' => ($h >> 11) % 3,
            'round' => ($h >> 13) % 3,
            'name' => $syllables[($h >> 15) % 16] . \core_text::strtolower($syllables[($h >> 19) % 16]),
        ];
    }
}
