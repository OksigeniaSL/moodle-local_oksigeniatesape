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
 * The climb to the tower: a mission that brings its own path (`climb` in its package) instead of the map. Where the
 * student is on it (the hero, the scenes of the place, its lantern, the gems, the creature) and what they do outside
 * the scenes: choose a hero, light the lantern, name the creature.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class climb {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Gems of a lantern lit, the same for everybody. */
    private const LANTERN_GEMS = 5;

    /** @var int Longest name of a creature. */
    private const NAME_LENGTH = 24;

    /** @var string[] What is noted of the student on the path, once each (the script's measures). */
    private const MARKS = ['first', 'heard', 'surprise', 'saved', 'next', 'mute'];

    /**
     * The mission of the game, if it brings a climb.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function mission(int $courseid): ?\stdClass {
        $mission = missions::get($courseid);
        return $mission && !empty($mission->data['climb']) && is_array($mission->data['climb']) ? $mission : null;
    }

    /**
     * Where the student is on the climb.
     *
     * @param int $courseid
     * @param int $userid
     * @return array climb (what the package says), chapter, scenes (code, id, done, ending, gems), lantern, sniff, hero,
     *     gems and creature (name, paint).
     */
    public static function state(int $courseid, int $userid): array {
        global $DB;
        $mission = self::mission($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $chapter = self::chapter($mission);
        $char = characters::get($userid);
        $found = [];
        if ($char) {
            $records = $DB->get_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id], '', 'id, kind, code');
            foreach ($records as $f) {
                $found[$f->kind][$f->code] = true;
            }
        }
        $scenes = [];
        $where = ['missionid' => $mission->id, 'chapterid' => $chapter->id, 'version' => $mission->version];
        $all = $DB->get_records(self::T . 'scene', $where, 'code', 'id, code, definition');
        $given = 'SELECT COALESCE(SUM(amount), 0) FROM {' . self::T . "ledger}
                   WHERE charid = ? AND courseid = ? AND resource = 'gems' AND reason = 'scene' AND refid = ? AND undoneby = 0";
        foreach ($all as $scene) {
            $last = [];
            $gems = 0;
            if ($char) {
                $last = $DB->get_records(self::T . 'sceneattempt', ['charid' => $char->id, 'sceneid' => $scene->id], 'id DESC', 'id, ending', 0, 1);
                $gems = (int) $DB->get_field_sql($given, [$char->id, $courseid, $scene->id]);
            }
            $definition = json_decode((string) $scene->definition, true) ?: [];
            $scenes[] = ['code' => $scene->code, 'id' => (int) $scene->id, 'title' => (string) ($definition['title'] ?? ''),
                'done' => !empty($found['scene'][$scene->code]), 'ending' => $last ? (string) reset($last)->ending : '',
                'gems' => $gems];
        }
        // In the order of the steps of the place on the path (the scenes go by their code).
        $order = array_flip(array_keys((array) ($mission->data['climb']['valley']['steps'] ?? [])));
        usort($scenes, fn($a, $b) => ($order[$a['code']] ?? PHP_INT_MAX) <=> ($order[$b['code']] ?? PHP_INT_MAX) ?:
            strcmp($a['code'], $b['code']));
        $traits = $char ? (json_decode((string) ($char->creature ?? ''), true) ?: []) : [];
        $look = $char ? (json_decode((string) ($char->appearance ?? ''), true) ?: []) : [];
        // The paint it wears, and the paints it has.
        $worn = $char ? market::worn($char) : [];
        $owned = 'charid = ? AND groupid = 0 AND ' . $DB->sql_like('code', '?');
        $paints = $char ? array_values($DB->get_fieldset_select(self::T . 'purchase', 'code', $owned, [$char->id, 'paint-%'])) : [];
        // Who speaks, with their name in the skin of the mission.
        $speakers = [];
        foreach ((array) ($mission->data['speakers'] ?? []) as $who => $names) {
            $speakers[$who] = (string) (((array) $names)[(string) $mission->skin] ?? reset($names) ?: $who);
        }
        return [
            'climb' => $mission->data['climb'],
            'speakers' => $speakers,
            'chapter' => ['id' => (int) $chapter->id, 'code' => $chapter->code],
            'scenes' => $scenes,
            'lantern' => !empty($found['lantern'][$chapter->code]),
            'paid' => $char ? self::paid($char, $courseid, $chapter) : false,
            'sniff' => !empty($found['verb']['sniff']),
            'hero' => $char ? ['role' => $char->role, 'nameform' => $char->nameform, 'skin' => (string) ($look['skin'] ?? '')] : null,
            'gems' => $char ? (int) $char->gems : 0,
            'creature' => ['name' => (string) ($traits['name'] ?? ''), 'paint' => (string) ($worn['paint'] ?? '')],
            'paints' => $paints,
            // What was noted already (the client does not send it again).
            'marks' => array_values(array_map(fn($code) => strstr($code, '=', true), array_keys($found['mark'] ?? []))),
        ];
    }

    /**
     * The student chooses their hero on the first minute: made if they have none, else their role and form change.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $role
     * @param string $nameform
     * @return array The state.
     */
    public static function hero(int $courseid, int $userid, string $role, string $nameform): array {
        $char = characters::get($userid);
        if (!$char) {
            $char = characters::create($userid, $role, $nameform);
        } else {
            // Chosen again: the role, where it may change now, and the figure.
            if ($char->role !== $role && characters::can_change_role($char)) {
                $char = characters::change_role($char, $role);
            }
            if ($char->nameform !== $nameform) {
                $look = json_decode((string) ($char->appearance ?? ''), true) ?: [];
                $char = characters::update_look($char, $look, $char->lineage ?? null, $nameform);
            }
        }
        engine::member($char, $courseid);
        return self::state($courseid, $userid);
    }

    /**
     * The lantern of the place lights once its scenes are done: its gems, once, and the creature learns its sense.
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return array The state.
     */
    public static function light(int $courseid, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $mission = self::mission($courseid);
        $char = characters::get($userid);
        if (!$mission || !$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $chapter = self::chapter($mission);
        $member = engine::member($char, $courseid);
        if (!scenes::chapter_done($char, (int) $member->step, $chapter)) {
            return self::state($courseid, $userid);
        }
        $transaction = $DB->start_delegated_transaction();
        if (!self::paid($char, $courseid, $chapter)) {
            engine::gems($member, self::LANTERN_GEMS, 'lantern', 0, 0, (int) $chapter->id, $now);
        }
        $verb = (string) ($mission->data['climb']['lantern']['verb'] ?? '');
        foreach (array_filter(['lantern' => $chapter->code, 'verb' => $verb]) as $kind => $code) {
            $key = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => $kind, 'code' => $code];
            if (!$DB->record_exists(self::T . 'found', $key)) {
                $DB->insert_record(self::T . 'found', (object) ($key + ['courseid' => $courseid, 'timecreated' => $now]));
            }
        }
        $transaction->allow_commit();
        return self::state($courseid, $userid);
    }

    /**
     * The student names their creature (with no name, it is «your creature»).
     *
     * @param int $courseid
     * @param int $userid
     * @param string $name
     * @return array The state.
     */
    public static function name(int $courseid, int $userid, string $name): array {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $name = \core_text::substr(trim(preg_replace('/\s+/u', ' ', clean_param($name, PARAM_TEXT))), 0, self::NAME_LENGTH);
        $traits = json_decode((string) ($char->creature ?? ''), true) ?: [];
        if ($name === '') {
            unset($traits['name']);
        } else {
            $traits['name'] = $name;
        }
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode($traits),
            'timemodified' => time()]);
        return self::state($courseid, $userid);
    }

    /**
     * Notes something of the student on the path, the first time only: the second of their first touch, the heroes heard,
     * the surprise of the postcard and whether it was saved, whether the next place was touched (and after how many
     * seconds), whether it was played with no sound.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $what One of MARKS.
     * @param string $value
     * @return array The state.
     */
    public static function mark(int $courseid, int $userid, string $what, string $value): array {
        global $DB;
        $mission = self::mission($courseid);
        $char = characters::get($userid);
        $value = substr(preg_replace('/[^a-z0-9-]/', '', strtolower($value)), 0, 20);
        if ($mission && $char && in_array($what, self::MARKS, true) && $value !== '') {
            $where = "charid = ? AND missionid = ? AND kind = 'mark' AND " . $DB->sql_like('code', '?');
            $known = $DB->record_exists_select(self::T . 'found', $where, [$char->id, $mission->id, $what . '=%']);
            if (!$known) {
                $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
                    'missionid' => $mission->id, 'kind' => 'mark', 'code' => $what . '=' . $value, 'timecreated' => time()]);
            }
        }
        return self::state($courseid, $userid);
    }

    /**
     * The student paints their creature at the camp: a paint they have, or one bought now.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code A paint of the market (paint-gold…), or empty to take it off.
     * @return array The state.
     */
    public static function paint(int $courseid, int $userid, string $code): array {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        if ($code === '') {
            market::wear($userid, 'paint');
        } else {
            if (strpos($code, 'paint-') !== 0) {
                throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
            }
            if (!$DB->record_exists(self::T . 'purchase', ['charid' => $char->id, 'code' => $code, 'groupid' => 0])) {
                market::buy($courseid, $userid, $code);
            }
            market::wear($userid, $code);
        }
        return self::state($courseid, $userid);
    }

    /**
     * The student changes the skin of their hero at the camp, for free.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $skin One of the skins of the game (a colour).
     * @return array The state.
     */
    public static function skin(int $courseid, int $userid, string $skin): array {
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $look = json_decode((string) ($char->appearance ?? ''), true) ?: [];
        $look['skin'] = $skin;
        characters::update_look($char, $look, $char->lineage ?? null);
        return self::state($courseid, $userid);
    }

    /**
     * The place of the climb: the first chapter of the mission.
     *
     * @param \stdClass $mission
     * @return \stdClass
     */
    private static function chapter(\stdClass $mission): \stdClass {
        global $DB;
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder, id', '*', 0, 1);
        if (!$chapters) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        return reset($chapters);
    }

    /**
     * Whether the gems of the lantern of a place were given.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param \stdClass $chapter
     * @return bool
     */
    private static function paid(\stdClass $char, int $courseid, \stdClass $chapter): bool {
        global $DB;
        return $DB->record_exists_select(self::T . 'ledger', "charid = ? AND courseid = ? AND resource = 'gems'
            AND reason = 'lantern' AND refid = ? AND undoneby = 0", [$char->id, $courseid, $chapter->id]);
    }
}
