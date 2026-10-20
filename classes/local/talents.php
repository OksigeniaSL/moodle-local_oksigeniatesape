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
 * Talents: at some levels a character chooses one of two talents of their role, and keeps it. They touch help, breath,
 * the shield, powers, minigames and battles; never what is right at the first try, the evidence nor the saber of the
 * scenes, and nothing of them shows on the class screen.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class talents {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var array The talents of each role: at each level that brings a choice, the two to choose from. */
    public const CATALOG = [
        'protector' => [3 => ['longshield', 'teamguard'], 6 => ['quickshield', 'candleguard'], 9 => ['endurance', 'sharedtime']],
        'healer' => [3 => ['doublecare', 'lightbreather'], 6 => ['sparecandle', 'strongcheer'], 9 => ['secondwind', 'lightcheer']],
        'sage' => [3 => ['househints', 'wideglance'], 6 => ['quickglance', 'knotreading'], 9 => ['keeneye', 'sharednotes']],
        'scout' => [3 => ['freshtrack', 'sharedtrail'], 6 => ['quicktrail', 'widetrail'], 9 => ['farsight', 'lightshortcut']],
    ];

    /**
     * The talents of a character in their role: level => code. Those of another role they had wait for them.
     *
     * @param \stdClass $char
     * @return string[]
     */
    public static function of(\stdClass $char): array {
        global $DB;
        return array_map('strval', $DB->get_records_menu(
            self::T . 'talent',
            ['charid' => $char->id, 'role' => $char->role],
            'level',
            'level, code'
        ));
    }

    /**
     * Whether a talent of a character acts: they have it in their role, and the course does not turn the talents off.
     *
     * @param int $charid
     * @param string $code
     * @param int $courseid The course where it would act (0: any).
     * @return bool
     */
    public static function has(int $charid, string $code, int $courseid = 0): bool {
        global $DB;
        if ($courseid && !self::on_in($courseid)) {
            return false;
        }
        return $DB->record_exists_sql(
            'SELECT 1
               FROM {' . self::T . 'talent} t
               JOIN {' . self::T . 'char} c ON c.id = t.charid AND c.role = t.role
              WHERE t.charid = ? AND t.code = ?',
            [$charid, $code]
        );
    }

    /**
     * The choice a character has waiting: the lowest level they reached with a pair of their role still to choose.
     *
     * @param \stdClass $char
     * @return array|null level and options (the two codes).
     */
    public static function pending(\stdClass $char): ?array {
        $chosen = self::of($char);
        foreach (self::CATALOG[$char->role] ?? [] as $level => $pair) {
            if ((int) $char->level >= $level && !isset($chosen[$level])) {
                return ['level' => (int) $level, 'options' => $pair];
            }
        }
        return null;
    }

    /**
     * The talents of a character that act in a course: those of their role, unless the course turns them off.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return string[] Codes.
     */
    public static function acting(\stdClass $char, int $courseid): array {
        return self::on_in($courseid) ? array_values(self::of($char)) : [];
    }

    /**
     * A character chooses the talent of a level: one of the pair of their role, once.
     *
     * @param \stdClass $char
     * @param int $level
     * @param string $code
     * @param int|null $now
     */
    public static function choose(\stdClass $char, int $level, string $code, ?int $now = null): void {
        global $DB;
        $pair = self::CATALOG[$char->role][$level] ?? [];
        if (!in_array($code, $pair, true) || (int) $char->level < $level) {
            throw new \moodle_exception('talentnotyours', 'local_oksigeniatesape');
        }
        if ($DB->record_exists(self::T . 'talent', ['charid' => $char->id, 'role' => $char->role, 'level' => $level])) {
            throw new \moodle_exception('talentchosen', 'local_oksigeniatesape');
        }
        $DB->insert_record(self::T . 'talent', (object) ['charid' => $char->id, 'role' => $char->role, 'code' => $code,
            'level' => $level, 'timecreated' => $now ?? time()]);
    }

    /**
     * The talents of a character in their role go, to be chosen again: a teacher lets them choose again.
     *
     * @param \stdClass $char
     */
    public static function reset(\stdClass $char): void {
        global $DB;
        $DB->delete_records(self::T . 'talent', ['charid' => $char->id, 'role' => $char->role]);
    }

    /**
     * Whether the talents act in a course: on unless its teacher turns them off.
     *
     * @param int $courseid
     * @return bool
     */
    public static function on_in(int $courseid): bool {
        $region = regions::get($courseid);
        return !$region || regions::rule($region, 'talents') > 0;
    }

    /**
     * The talents of a character as the game shows them: level, code, name and what it does.
     *
     * @param \stdClass $char
     * @return array
     */
    public static function view(\stdClass $char): array {
        $list = [];
        foreach (self::of($char) as $level => $code) {
            $list[] = ['level' => (int) $level] + self::card($code);
        }
        return $list;
    }

    /**
     * What a talent is, for its card: code, name and what it does.
     *
     * @param string $code
     * @return array
     */
    public static function card(string $code): array {
        return ['code' => $code, 'name' => get_string('talent_' . $code, 'local_oksigeniatesape'),
            'description' => get_string('talent_' . $code . '_desc', 'local_oksigeniatesape')];
    }
}
