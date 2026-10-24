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
 * What a student needs to play as everybody else: the teacher marks the adaptations, never a diagnosis, and the game
 * adapts itself when they play. The student can turn on those of comfort for themselves; those that change the game (no
 * hurry, more help) only the teacher. Only the student and their teachers see them.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class adapt {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * @var string[] The adaptations: big text, reading aloud, no hurry, no movement, high contrast, colours that do not
     *     depend on red and green, no sounds, and more help (hints that cost no breath).
     */
    public const ALL = ['bigtext', 'readaloud', 'easyread', 'notime', 'still', 'contrast', 'colours', 'quiet', 'hints'];

    /** @var string[] Those of comfort, which the student can turn on: they do not change the game. */
    public const COMFORT = ['bigtext', 'readaloud', 'easyread', 'still', 'contrast', 'colours', 'quiet'];

    /** @var array Profiles of one touch for the teacher, and what each one marks (added to what was marked). */
    public const PROFILES = [
        'reading' => ['readaloud', 'easyread', 'bigtext', 'hints'],
        'calm' => ['notime', 'quiet', 'still'],
        'sight' => ['bigtext', 'contrast'],
    ];

    /**
     * Only the adaptations that exist, in their order, once each.
     *
     * @param array $list
     * @return string[]
     */
    public static function clean(array $list): array {
        return array_values(array_intersect(self::ALL, array_map('strval', $list)));
    }

    /**
     * The adaptations of a student in a course when they play: those of their teacher and those they turned on.
     *
     * @param \stdClass $member
     * @return string[]
     */
    public static function of(\stdClass $member): array {
        return self::clean(array_merge(self::teacher($member), self::own($member)));
    }

    /**
     * The adaptations the teacher marked.
     *
     * @param \stdClass $member
     * @return string[]
     */
    public static function teacher(\stdClass $member): array {
        return self::clean(explode(',', (string) ($member->adapt ?? '')));
    }

    /**
     * The adaptations of comfort the student turned on for themselves.
     *
     * @param \stdClass $member
     * @return string[]
     */
    public static function own(\stdClass $member): array {
        return array_values(array_intersect(self::COMFORT, self::clean(explode(',', (string) ($member->adaptself ?? '')))));
    }

    /**
     * Whether a student has an adaptation.
     *
     * @param \stdClass $member
     * @param string $code
     * @return bool
     */
    public static function has(\stdClass $member, string $code): bool {
        return in_array($code, self::of($member), true);
    }

    /**
     * Keeps the adaptations of comfort a student turns on for themselves; any other is left out.
     *
     * @param \stdClass $member
     * @param array $list
     * @return string[] What was kept.
     */
    public static function save_own(\stdClass $member, array $list): array {
        global $DB;
        $clean = array_values(array_intersect(self::COMFORT, self::clean($list)));
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'adaptself' => $clean ? implode(',', $clean) : null,
            'timemodified' => time()]);
        return $clean;
    }

    /**
     * Keeps the adaptations the teacher marks for a student in a course.
     *
     * @param \stdClass $member
     * @param array $list
     * @return string[] What was kept.
     */
    public static function save(\stdClass $member, array $list): array {
        global $DB;
        $clean = self::clean($list);
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'adapt' => $clean ? implode(',', $clean) : null,
            'timemodified' => time()]);
        return $clean;
    }
}
