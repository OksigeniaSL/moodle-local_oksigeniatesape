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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scenes {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var float Share of the class that lights the lantern of a chapter for everybody. */
    public const CLASS_LANTERN_SHARE = 0.5;

    /** @var int Last stage of the creature. */
    public const CREATURE_MAX_STAGE = 4;

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
            'SELECT s.*
               FROM {' . self::T . 'scene} s
               JOIN {' . self::T . 'mission} m ON m.id = s.missionid
              WHERE s.id = ? AND m.courseid = ?',
            [$sceneid, $courseid],
            MUST_EXIST
        );
        $scene->data = json_decode($scene->definition, true);
        return $scene;
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
        // Quests are apart: open to everybody, they are not anybody's route.
        $scenes = array_filter($scenes, fn($s) => empty($s->quest));
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
        $quests = array_values(array_filter($scenes, fn($s) => !empty($s->quest)));
        usort($quests, fn($a, $b) => [(int) $a->step, $a->code] <=> [(int) $b->step, $b->code]);
        return $quests;
    }

    /**
     * What a student brings to a scene: the items they carry in the course and the roles of their team.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return array [item codes, roles of the team, flags of the mission (what they did in other scenes), own role]
     */
    public static function context(\stdClass $char, int $courseid): array {
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
        }
        $mission = missions::get($courseid);
        $flags = [];
        if ($mission) {
            $where = "charid = ? AND missionid = ? AND kind = 'flag'";
            $flags = $DB->get_fieldset_select(self::T . 'found', 'code', $where, [$char->id, $mission->id]);
        }
        return [$items, array_values(array_unique($roles)), array_values($flags), (string) $char->role];
    }

    /**
     * The hints opened in a play cost breath: one each; the sage gets the first free and pays half for the rest.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $sceneid
     * @param int $hints Hints opened.
     * @param int $now
     * @return int Breath paid.
     */
    private static function pay_hints(\stdClass $char, \stdClass $member, int $sceneid, int $hints, int $now): int {
        $cost = $char->role === 'sage' ? (int) ceil(max(0, $hints - 1) / 2) : $hints;
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
        engine::breath($least, 1, 'care', (int) $char->userid, $sceneid, $now);
        $user = \core_user::get_user($userid);
        $region = regions::get((int) $member->courseid);
        return $user && $region ? view::display_name($user, characters::get($userid), $region) : '';
    }

    /**
     * Plays a scene: plays it again with the events of the student, keeps the play and gives what it gives.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $sceneid
     * @param array $events
     * @param string|null $from Where the play started (null: the start of the scene).
     * @param int|null $now
     * @return array ending, outcome, points, objectives, rewards, attempt.
     */
    public static function play(int $courseid, int $userid, int $sceneid, array $events, ?string $from, ?int $now = null): array {
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
        $scene = self::get($courseid, $sceneid);
        [$items, $roles, $flags] = self::context($char, $courseid);
        $result = (new scene_runner($scene->data, $items, $roles, $flags, $char->role))->run($events, $from);

        $transaction = $DB->start_delegated_transaction();
        $attempt = (object) [
            'sceneid' => $scene->id,
            'charid' => $char->id,
            'courseid' => $courseid,
            'teamplay' => 0,
            'path' => json_encode(['from' => $from, 'events' => $events, 'path' => $result['path']]),
            'score' => $result['points'],
            'objectives' => json_encode($result['objectives']),
            'ending' => $result['ending'],
            'attempt' => $DB->count_records(self::T . 'sceneattempt', ['sceneid' => $scene->id, 'charid' => $char->id]) + 1,
            'timestarted' => $now,
            'timefinished' => $now,
        ];
        $attempt->id = $DB->insert_record(self::T . 'sceneattempt', $attempt);
        // The competencies of the objectives get the evidence of the play; the teacher rates.
        competencies::evidence($courseid, (int) $userid, $scene, $result['objectives'], (int) $attempt->attempt);
        $rewards = [];
        if (in_array($result['outcome'], ['success', 'partial'], true)) {
            $rewards = self::reward($char, $member, $scene, $result, $now);
            if ($rewards['saber'] > 0 && $char->role === 'healer') {
                $rewards['cared'] = self::care($char, $member, (int) $scene->id, $now);
            }
        }
        $rewards['hints'] = self::pay_hints($char, $member, (int) $scene->id, (int) $result['hints'], $now);
        $rewards['shielded'] = (bool) $result['shielded'];
        $transaction->allow_commit();
        return [
            'ending' => $result['ending'],
            'outcome' => $result['outcome'],
            'retry' => (string) $result['retry'],
            'points' => $result['points'],
            'objectives' => $result['objectives'],
            'rewards' => $rewards,
            'attempt' => (int) $attempt->attempt,
        ];
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
     * @return array ending, outcome, retry, points, objectives, rewarded (players who got something).
     */
    public static function play_class(
        int $courseid,
        int $sceneid,
        array $events,
        ?string $from,
        array $charids,
        ?int $now = null
    ): array {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $scene = self::get($courseid, $sceneid);
        [$items, $roles] = self::class_context($courseid);
        $members = array_filter(
            $DB->get_records(self::T . 'member', ['courseid' => $courseid]),
            fn($m) => $m->status !== 'paused' && (!$charids || in_array((int) $m->charid, $charids, true))
        );
        $chars = [];
        foreach ($members as $member) {
            $chars[$member->id] = $DB->get_record(self::T . 'char', ['id' => $member->charid], '*', MUST_EXIST);
        }
        $runner = new scene_runner($scene->data, $items, $roles);
        $result = $runner->run($events, $from);
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
        $quest = !empty($scene->quest);
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
        if ($rewards['lanterns']) {
            // The last lantern may give the badge of the mission.
            badges::check((int) $member->courseid, (int) $char->userid);
        }
        return $rewards;
    }

    /**
     * Whether a student has done the scenes of a chapter for their step.
     *
     * @param \stdClass $char
     * @param int $step
     * @param \stdClass $chapter
     * @return bool
     */
    public static function chapter_done(\stdClass $char, int $step, \stdClass $chapter): bool {
        global $DB;
        $scenes = self::for_step(array_values($DB->get_records(self::T . 'scene', ['chapterid' => $chapter->id])), $step);
        if (!$scenes) {
            return false;
        }
        foreach ($scenes as $scene) {
            if (
                !$DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $chapter->missionid,
                    'kind' => 'scene', 'code' => $scene->code])
            ) {
                return false;
            }
        }
        return true;
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
     * Lights the lantern of a chapter for the class when half of those who play have it.
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
        $players = $DB->count_records_select(self::T . 'member', "courseid = ? AND status <> 'paused'", [$mission->courseid]);
        $lit = $DB->count_records(self::T . 'found', ['missionid' => $mission->id, 'kind' => 'lantern', 'code' => $code]);
        if (!$players || $lit < $players * self::CLASS_LANTERN_SHARE) {
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
