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
 * The characters: one per student for the whole site.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class characters {
    /** @var string Table of the characters. */
    private const TABLE = 'local_oksigeniatesape_char';

    /** @var string[] The four roles. */
    public const ROLES = ['protector', 'healer', 'sage', 'scout'];

    /** @var string[] Lineages, only how the character looks in the fantasy families. */
    public const LINEAGES = ['human', 'elf', 'dwarf', 'fairy'];

    /** @var string[] The words a student may choose to describe their hero: flavour that the scenes read. */
    public const NATURES = ['curious', 'brave', 'calm', 'cheerful', 'tenacious', 'dreamy'];

    /** @var int Words a hero is described with. */
    public const NATURES_MOST = 2;

    /** @var int Days before a student can change role again. */
    public const ROLE_CHANGE_DAYS = 90;

    /** @var int Longest appearance kept, in bytes of JSON. */
    public const APPEARANCE_MAX = 4000;

    /**
     * The character of a user.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['userid' => $userid]) ?: null;
    }

    /**
     * Makes the character of a user.
     *
     * @param int $userid
     * @param string $role One of ROLES.
     * @param string $nameform m or f.
     * @param string|null $lineage One of LINEAGES, or null.
     * @param array $appearance Parts and colours.
     * @param int|null $now
     * @param string[] $nature Words that describe the hero (NATURES), two at most.
     * @return \stdClass
     */
    public static function create(
        int $userid,
        string $role,
        string $nameform,
        ?string $lineage = null,
        array $appearance = [],
        ?int $now = null,
        array $nature = []
    ): \stdClass {
        global $DB;
        if (self::get($userid)) {
            throw new \moodle_exception('characterexists', 'local_oksigeniatesape');
        }
        self::check($role, $nameform, $lineage);
        $now = $now ?? time();
        $char = (object) [
            'userid' => $userid,
            'role' => $role,
            'nameform' => $nameform,
            'lineage' => $lineage,
            'appearance' => self::encode_appearance($appearance),
            'nature' => implode(',', self::clean_nature($nature)),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $char->id = $DB->insert_record(self::TABLE, $char);
        return $DB->get_record(self::TABLE, ['id' => $char->id], '*', MUST_EXIST);
    }

    /**
     * The words that describe a hero, as chosen.
     *
     * @param \stdClass $char
     * @return string[]
     */
    public static function nature(\stdClass $char): array {
        return self::clean_nature(explode(',', (string) ($char->nature ?? '')));
    }

    /**
     * Words chosen to describe a hero: those that exist, each once, two at most.
     *
     * @param array $nature
     * @return string[]
     */
    public static function clean_nature(array $nature): array {
        $clean = array_values(array_unique(array_filter(
            array_map(fn($w) => trim((string) $w), $nature),
            fn($w) => in_array($w, self::NATURES, true)
        )));
        return array_slice($clean, 0, self::NATURES_MOST);
    }

    /**
     * Changes the words that describe a hero: they are flavour, so they can change at any time.
     *
     * @param \stdClass $char
     * @param array $nature
     * @return \stdClass
     */
    public static function set_nature(\stdClass $char, array $nature): \stdClass {
        global $DB;
        $char->nature = implode(',', self::clean_nature($nature));
        $char->timemodified = time();
        $DB->update_record(self::TABLE, (object) ['id' => $char->id, 'nature' => $char->nature,
            'timemodified' => $char->timemodified]);
        return $char;
    }

    /**
     * Changes how a character looks.
     *
     * @param \stdClass $char
     * @param array $appearance
     * @param string|null $lineage
     * @param string|null $nameform
     * @return \stdClass
     */
    public static function update_look(\stdClass $char, array $appearance, ?string $lineage, ?string $nameform = null): \stdClass {
        global $DB;
        $nameform = $nameform ?? $char->nameform;
        self::check($char->role, $nameform, $lineage);
        $char->appearance = self::encode_appearance($appearance);
        $char->lineage = $lineage;
        $char->nameform = $nameform;
        $char->timemodified = time();
        $DB->update_record(self::TABLE, $char);
        return $char;
    }

    /**
     * Whether a character can change role now: once every ROLE_CHANGE_DAYS, unless a teacher allows it.
     *
     * @param \stdClass $char
     * @param int|null $now
     * @return bool
     */
    public static function can_change_role(\stdClass $char, ?int $now = null): bool {
        return !$char->rolechangedat || ($now ?? time()) - $char->rolechangedat >= self::ROLE_CHANGE_DAYS * DAYSECS;
    }

    /**
     * Changes a character's role, keeping the level.
     *
     * @param \stdClass $char
     * @param string $role
     * @param bool $byteacher A teacher allows it before its time.
     * @param int|null $now
     * @return \stdClass
     */
    public static function change_role(\stdClass $char, string $role, bool $byteacher = false, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        self::check($role, $char->nameform, $char->lineage);
        if (!$byteacher && !self::can_change_role($char, $now)) {
            throw new \moodle_exception('rolechangetoosoon', 'local_oksigeniatesape');
        }
        $char->role = $role;
        $char->rolechangedat = $now;
        $char->timemodified = $now;
        $DB->update_record(self::TABLE, $char);
        return $char;
    }

    /**
     * Checks the choices of a character.
     *
     * @param string $role
     * @param string $nameform
     * @param string|null $lineage
     */
    private static function check(string $role, string $nameform, ?string $lineage): void {
        if (!in_array($role, self::ROLES, true)) {
            throw new \invalid_parameter_exception('Unknown role: ' . $role);
        }
        if (!in_array($nameform, ['m', 'f'], true)) {
            throw new \invalid_parameter_exception('Unknown name form: ' . $nameform);
        }
        if ($lineage !== null && !in_array($lineage, self::LINEAGES, true)) {
            throw new \invalid_parameter_exception('Unknown lineage: ' . $lineage);
        }
    }

    /**
     * Keeps only plain values of the appearance (parts and colours), so nothing else can be stored in it.
     *
     * @param array $appearance
     * @return string|null JSON.
     */
    private static function encode_appearance(array $appearance): ?string {
        $clean = [];
        foreach ($appearance as $key => $value) {
            if (!preg_match('/^[a-z][a-z0-9]{0,19}$/', (string) $key)) {
                continue;
            }
            if (is_string($value) && preg_match('/^(#[0-9a-f]{6}|[a-z0-9-]{1,30})$/', $value)) {
                $clean[$key] = $value;
            } else if (is_int($value) && $value >= 0 && $value < 1000) {
                $clean[$key] = $value;
            }
        }
        if (!$clean) {
            return null;
        }
        $json = json_encode($clean);
        if (strlen($json) > self::APPEARANCE_MAX) {
            throw new \invalid_parameter_exception('Appearance too long');
        }
        return $json;
    }
}
