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
 * The friends of the expedition: each character of the mission who speaks (`friends`) remembers the pages of the
 * Great Book a student gave back from what is theirs. With so many pages (`at`), they tell the student something new
 * and give some gems, once, when the student listens.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class friends {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * The friends of a mission as a student sees them.
     *
     * @param \stdClass $mission With data.
     * @param array $found What the student found in the mission: kind => code => true.
     * @return array List of code, name, pages (theirs given back), of (theirs in all), talks (at, open, read, text, gems).
     */
    public static function view(\stdClass $mission, array $found): array {
        $list = [];
        foreach ($mission->data['friends'] ?? [] as $code => $friend) {
            $pages = (array) ($friend['pages'] ?? []);
            $have = self::counted($pages, fn($p) => !empty($found['page'][$p]));
            $talks = [];
            foreach ($friend['talks'] ?? [] as $talk) {
                $at = (int) ($talk['at'] ?? 0);
                $open = $have >= $at;
                $talks[] = ['at' => $at, 'open' => $open, 'read' => !empty($found['friend'][$code . ':' . $at]),
                    'text' => $open ? missions::text($talk['text'] ?? '', $mission->skin, $mission->variant) : '',
                    'gems' => (int) ($talk['gems'] ?? 0)];
            }
            $list[] = ['code' => (string) $code, 'name' => missions::text(
                $mission->data['speakers'][$code] ?? $code,
                $mission->skin
            ), 'pages' => $have, 'of' => count($pages), 'talks' => $talks];
        }
        return $list;
    }

    /**
     * How many pages of a friend a student gave back: a list of pages counts as one, given back if any of them is (the
     * animal of the trail of the owl, which comes at random).
     *
     * @param array $pages Codes, or lists of codes.
     * @param callable $given Whether a page was given back.
     * @return int
     */
    private static function counted(array $pages, callable $given): int {
        return count(array_filter($pages, fn($p) => is_array($p) ? (bool) array_filter($p, $given) : $given($p)));
    }

    /**
     * A student listens to what a friend tells them: the first time, the gems of the talk.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param string $code The friend.
     * @param int $at The talk, by the pages it takes.
     * @param int|null $now
     * @return array text, gems (given now).
     */
    public static function listen(int $courseid, \stdClass $char, string $code, int $at, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $friend = $mission->data['friends'][$code] ?? null;
        $talk = $friend ? (array_values(array_filter($friend['talks'] ?? [], fn($t) => (int) ($t['at'] ?? 0) === $at))[0] ??
            null) : null;
        if (!$talk) {
            throw new \invalid_parameter_exception('Not a talk of a friend of the mission');
        }
        $given = $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            "charid = ? AND missionid = ? AND kind = 'page'",
            [$char->id, $mission->id]
        );
        if (self::counted((array) ($friend['pages'] ?? []), fn($p) => in_array($p, $given, true)) < $at) {
            throw new \moodle_exception('nofriendtalk', 'local_oksigeniatesape');
        }
        $gems = 0;
        $key = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'friend', 'code' => $code . ':' . $at];
        if (!$DB->record_exists(self::T . 'found', $key)) {
            try {
                $DB->insert_record(self::T . 'found', (object) ($key + ['courseid' => $courseid, 'timecreated' => $now]));
                $entry = engine::gems(
                    engine::member($char, $courseid, $now),
                    (int) ($talk['gems'] ?? 0),
                    'friend',
                    0,
                    0,
                    0,
                    $now
                );
                $gems = $entry ? (int) $entry->amount : 0;
            } catch (\dml_write_exception $e) {
                // Listened to at the same time on another device: given there.
                $gems = 0;
            }
        }
        return ['text' => missions::text($talk['text'] ?? '', $mission->skin, $mission->variant), 'gems' => $gems];
    }
}
