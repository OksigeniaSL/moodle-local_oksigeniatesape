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
 * The remote of a scene played by the whole class: the class screen says which buttons it shows (the options of a
 * decision, «Go on», closing a vote…), and the phone of the teacher presses one from anywhere in the classroom; the
 * class screen presses it. Nothing is kept in the database: it lives in a cache for a while, and goes when the scene
 * closes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class remote {
    /** @var int Seconds the buttons of a class screen count without news from it. */
    public const FRESH = 30;

    /** @var int Buttons a class screen says at most. */
    public const MAX = 12;

    /**
     * The cache.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('local_oksigeniatesape', 'remote');
    }

    /**
     * The class screen says what it shows now: the title of the scene, what it asks and its buttons (key and label).
     * What the phone pressed and was not done yet stays.
     *
     * @param int $courseid
     * @param string $title
     * @param string $text
     * @param array $buttons Of key and label.
     * @param int $now
     * @return array The remote.
     */
    public static function publish(int $courseid, string $title, string $text, array $buttons, int $now): array {
        $old = self::cache()->get(games::cache_key($courseid)) ?: [];
        $clean = [];
        foreach (array_slice($buttons, 0, self::MAX) as $button) {
            $key = clean_param((string) ($button['key'] ?? ''), PARAM_ALPHANUMEXT);
            $label = \core_text::substr(trim(clean_param((string) ($button['label'] ?? ''), PARAM_TEXT)), 0, 120);
            if ($key !== '' && $label !== '') {
                $clean[] = ['key' => $key, 'label' => $label];
            }
        }
        $state = ['title' => \core_text::substr(trim($title), 0, 120), 'text' => \core_text::substr(trim($text), 0, 400),
            'buttons' => $clean, 'at' => $now, 'press' => $old['press'] ?? ['seq' => 0, 'key' => '']];
        self::cache()->set(games::cache_key($courseid), $state);
        return $state;
    }

    /**
     * The class screen is still there: its buttons stay fresh. It gets what the phone pressed.
     *
     * @param int $courseid
     * @param int $now
     * @return array|null The remote, or null if there is none.
     */
    public static function alive(int $courseid, int $now): ?array {
        $state = self::cache()->get(games::cache_key($courseid));
        if (!$state) {
            return null;
        }
        $state['at'] = $now;
        self::cache()->set(games::cache_key($courseid), $state);
        return $state;
    }

    /**
     * What the class screen shows, for the phone: nothing if it said nothing for a while.
     *
     * @param int $courseid
     * @param int $now
     * @return array|null
     */
    public static function read(int $courseid, int $now): ?array {
        $state = self::cache()->get(games::cache_key($courseid));
        return $state && $now - (int) $state['at'] <= self::FRESH ? $state : null;
    }

    /**
     * The phone presses a button that the class screen shows.
     *
     * @param int $courseid
     * @param string $key
     * @param int $now
     * @return array The remote.
     */
    public static function press(int $courseid, string $key, int $now): array {
        $state = self::read($courseid, $now);
        if (!$state || !in_array($key, array_column($state['buttons'], 'key'), true)) {
            throw new \moodle_exception('noremote', 'local_oksigeniatesape');
        }
        $state['press'] = ['seq' => (int) $state['press']['seq'] + 1, 'key' => $key];
        self::cache()->set(games::cache_key($courseid), $state);
        return $state;
    }

    /**
     * The scene closed on the class screen: the remote goes.
     *
     * @param int $courseid
     */
    public static function clear(int $courseid): void {
        self::cache()->delete(games::cache_key($courseid));
    }
}
