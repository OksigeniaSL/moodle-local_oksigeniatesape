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
 * The touchable secrets of the places: things found in the painting of a place without anybody asking for them (a
 * print of ink that is no animal's, an empty jar, a page under a leaf). Each one goes to the album; it can leave a mark
 * the scenes read, or give a page of the Great Book; and whoever finds every secret of a set gets its mark too. No saber
 * nor gems: the world, not a reward.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class secrets {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * The secrets still hidden in a place for a student.
     *
     * @param \stdClass $char
     * @param \stdClass $mission With data.
     * @param string $place Code of the place.
     * @param string[] $found Codes of the secrets the student found.
     * @return array id, kind, x, y.
     */
    public static function in_place(\stdClass $char, \stdClass $mission, string $place, array $found): array {
        $out = [];
        foreach ($mission->data['secrets'] ?? [] as $secret) {
            if (($secret['place'] ?? '') === $place && !in_array((string) $secret['id'], $found, true)) {
                $out[] = ['id' => (string) $secret['id'], 'kind' => (string) ($secret['kind'] ?? 'thing'),
                    'x' => (int) ($secret['x'] ?? 50), 'y' => (int) ($secret['y'] ?? 50)];
            }
        }
        return $out;
    }

    /**
     * The secrets a student found, for the album, and how many there are.
     *
     * @param \stdClass $mission
     * @param string[] $found Codes of the secrets the student found.
     * @return array found (id, kind, text), total.
     */
    public static function album(\stdClass $mission, array $found): array {
        $list = [];
        foreach ($mission->data['secrets'] ?? [] as $secret) {
            if (in_array((string) $secret['id'], $found, true)) {
                $list[] = ['id' => (string) $secret['id'], 'kind' => (string) ($secret['kind'] ?? 'thing'),
                    'text' => missions::text($secret['text'] ?? '', $mission->skin, $mission->variant)];
            }
        }
        return ['found' => $list, 'total' => count($mission->data['secrets'] ?? [])];
    }

    /**
     * A student finds a secret: it goes to their album, with its mark or its page, and the mark of its set if it was the
     * last of it. Found again, it only says what it says.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param string $id
     * @param int|null $now
     * @return array text, page (title, text; empty if none or had it), set (what the whole set says; empty if not).
     */
    public static function find(\stdClass $char, \stdClass $member, string $id, ?int $now = null): array {
        $now = $now ?? time();
        $mission = missions::get((int) $member->courseid);
        $secret = null;
        foreach ($mission ? $mission->data['secrets'] ?? [] : [] as $one) {
            if ((string) ($one['id'] ?? '') === $id) {
                $secret = $one;
            }
        }
        if (!$secret || $member->status === 'paused') {
            throw new \moodle_exception('nosecret', 'local_oksigeniatesape');
        }
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $out = ['text' => $text($secret['text'] ?? ''), 'page' => ['title' => '', 'text' => ''], 'set' => ''];
        if (!self::note($char, $member, $mission, 'secret', $id, $now)) {
            return $out;
        }
        if (!empty($secret['mark'])) {
            self::note($char, $member, $mission, 'flag', (string) $secret['mark'], $now);
        }
        $page = $mission->data['pages'][$secret['page'] ?? ''] ?? null;
        if ($page && self::note($char, $member, $mission, 'page', (string) $secret['page'], $now)) {
            $out['page'] = ['title' => $text($page['title'] ?? ''), 'text' => $text($page['text'] ?? '')];
        }
        $set = $mission->data['secretsets'][$secret['set'] ?? ''] ?? null;
        if ($set) {
            $found = self::found($char, $mission);
            $whole = array_filter($mission->data['secrets'], fn($s) => ($s['set'] ?? '') === $secret['set']);
            if (!array_diff(array_map(fn($s) => (string) $s['id'], $whole), $found)) {
                self::note($char, $member, $mission, 'flag', (string) $set['mark'], $now);
                $out['set'] = $text($set['text'] ?? '');
            }
        }
        return $out;
    }

    /**
     * The codes of the secrets a student found in a mission.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @return string[]
     */
    public static function found(\stdClass $char, \stdClass $mission): array {
        global $DB;
        return $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            "charid = ? AND missionid = ? AND kind = 'secret'",
            [$char->id, $mission->id]
        );
    }

    /**
     * Notes something found, once.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass $mission
     * @param string $kind
     * @param string $code
     * @param int $now
     * @return bool It was new.
     */
    private static function note(
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
}
