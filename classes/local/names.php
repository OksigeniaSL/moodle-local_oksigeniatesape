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
 * The names a student gives: the hero name, where the course allows it, and the name of the creature. Both are chosen
 * from a list the game makes for each character, or proposed by the student where the course allows own names, and then
 * they wait for a teacher. No free name is shown before a teacher has read it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class names {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Names offered to choose from. */
    public const OPTIONS = 8;

    /** @var int Longest own name. */
    public const MAX = 20;

    /** @var string[] First half of a hero name: an animal of the valley. */
    private const ANIMALS = ['Lince', 'Búho', 'Garza', 'Puma', 'Zorro', 'Tucán', 'Colibrí', 'Jaguar', 'Ñandú', 'Carpincho',
        'Halcón', 'Ciervo', 'Nutria', 'Ardilla', 'Tortuga', 'Lechuza'];

    /** @var string[] Second half of a hero name. */
    private const PLACES = ['de Plata', 'del Alba', 'de Niebla', 'del Río', 'del Bosque', 'de la Montaña', 'del Trueno',
        'de Ámbar', 'del Viento', 'de la Luna', 'del Sol', 'de Musgo', 'de las Estrellas', 'del Lago', 'de Cristal',
        'de Bruma'];

    /** @var string[] Syllables of creature names. */
    private const SYLLABLES = ['Ti', 'Ru', 'Mo', 'Ka', 'Lu', 'Pa', 'Ñe', 'Yu', 'Bo', 'Sa', 'Ri', 'Na', 'Po', 'Ma', 'Ki',
        'Je'];

    /**
     * Hero names to choose from, always the same for a character.
     *
     * @param \stdClass $char
     * @return string[]
     */
    public static function hero_options(\stdClass $char): array {
        $names = [];
        for ($i = 0; count($names) < self::OPTIONS && $i < 64; $i++) {
            $h = md5('tesape-hero-' . $char->id . '-' . $i);
            $name = self::ANIMALS[hexdec($h[0])] . ' ' . self::PLACES[hexdec($h[1])];
            $names[$name] = $name;
        }
        return array_values($names);
    }

    /**
     * Creature names to choose from, always the same for a character; the first one is the name it was born with.
     *
     * @param \stdClass $char
     * @return string[]
     */
    public static function creature_options(\stdClass $char): array {
        $traits = json_decode((string) $char->creature, true) ?: [];
        $names = [];
        $born = (string) (scenes::creature($char)['name'] ?? '');
        if ($born !== '') {
            $names[$born] = $born;
        }
        for ($i = 0; count($names) < self::OPTIONS && $i < 64; $i++) {
            $h = md5('tesape-creature-name-' . $char->id . '-' . $i);
            if ($h[0] === $h[1]) {
                // «Jeje» sounds like a laugh: two different syllables.
                continue;
            }
            $name = self::SYLLABLES[hexdec($h[0])] . \core_text::strtolower(self::SYLLABLES[hexdec($h[1])]);
            $names[$name] = $name;
        }
        $current = (string) ($traits['name'] ?? '');
        if ($current !== '' && !isset($names[$current])) {
            // An own name approved before stays among the options.
            array_unshift($names, $current);
        }
        return array_values($names);
    }

    /**
     * A student chooses a name from the list (it counts at once) or proposes their own (it waits for a teacher).
     *
     * @param int $courseid Course where they do it.
     * @param int $userid
     * @param string $what hero or creature.
     * @param int $option Index in the options; -1 for their own name.
     * @param string $own Their own name.
     */
    public static function choose(int $courseid, int $userid, string $what, int $option, string $own = ''): void {
        global $DB;
        $region = regions::get($courseid);
        $char = characters::get($userid);
        if (!$char || !$region || !$region->enabled || !in_array($what, ['hero', 'creature'], true)) {
            throw new \moodle_exception('nonames', 'local_oksigeniatesape');
        }
        if ($what === 'hero' && !$region->nicknames) {
            throw new \moodle_exception('nonames', 'local_oksigeniatesape');
        }
        $traits = json_decode((string) $char->creature, true) ?: [];
        if ($what === 'creature' && !$traits) {
            throw new \moodle_exception('nonames', 'local_oksigeniatesape');
        }
        if ($option < 0) {
            if ((int) $region->nicknames !== 2) {
                throw new \moodle_exception('nonames', 'local_oksigeniatesape');
            }
            $name = self::clean($own);
        } else {
            $options = $what === 'hero' ? self::hero_options($char) : self::creature_options($char);
            if (!isset($options[$option])) {
                throw new \invalid_parameter_exception('Not one of the names offered');
            }
            $name = $options[$option];
        }
        if ($what === 'hero') {
            if ($option >= 0) {
                self::require_free($courseid, $char, $name);
            }
            $update = $option < 0 ? ['nicknamepending' => $name] : ['nickname' => $name, 'nicknamepending' => ''];
            $DB->update_record(self::T . 'char', (object) (['id' => $char->id, 'timemodified' => time()] + $update));
        } else {
            if ($option < 0) {
                $traits['pending'] = $name;
            } else {
                $traits['name'] = $name;
                unset($traits['pending']);
            }
            $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode($traits),
                'timemodified' => time()]);
        }
    }

    /**
     * Own names waiting for a teacher, of the students of a course.
     *
     * @param int $courseid
     * @return array userid, what (hero or creature), name.
     */
    public static function pending(int $courseid): array {
        global $DB;
        $students = view::students($courseid);
        if (!$students) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
        $fields = 'id, userid, nicknamepending, creature';
        $chars = $DB->get_records_select(self::T . 'char', "userid $insql", $params, 'userid', $fields);
        $list = [];
        foreach ($chars as $char) {
            if ((string) $char->nicknamepending !== '') {
                $list[] = ['userid' => (int) $char->userid, 'what' => 'hero', 'name' => (string) $char->nicknamepending];
            }
            $traits = json_decode((string) $char->creature, true) ?: [];
            if (!empty($traits['pending'])) {
                $list[] = ['userid' => (int) $char->userid, 'what' => 'creature', 'name' => (string) $traits['pending']];
            }
        }
        return $list;
    }

    /**
     * A teacher takes or turns down the own name a student proposed.
     *
     * @param int $courseid
     * @param int $userid The student.
     * @param string $what hero or creature.
     * @param bool $approve
     */
    public static function decide(int $courseid, int $userid, string $what, bool $approve): void {
        global $DB;
        $char = characters::get($userid);
        if (!$char || !in_array($what, ['hero', 'creature'], true)) {
            throw new \invalid_parameter_exception('Nothing to decide');
        }
        if ($what === 'hero') {
            $name = (string) $char->nicknamepending;
            if ($name === '') {
                return;
            }
            if ($approve) {
                self::require_free($courseid, $char, $name);
            }
            $update = ['id' => $char->id, 'nicknamepending' => '', 'timemodified' => time()];
            if ($approve) {
                $update['nickname'] = $name;
            }
            $DB->update_record(self::T . 'char', (object) $update);
        } else {
            $traits = json_decode((string) $char->creature, true) ?: [];
            if (empty($traits['pending'])) {
                return;
            }
            if ($approve) {
                $traits['name'] = $traits['pending'];
            }
            unset($traits['pending']);
            $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode($traits),
                'timemodified' => time()]);
        }
    }

    /**
     * An own name, tidied up: letters, spaces, apostrophes and hyphens, from 2 to 20 characters.
     *
     * @param string $name
     * @return string
     */
    public static function clean(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $length = \core_text::strlen($name);
        if ($length < 2 || $length > self::MAX || !preg_match("/^\\p{L}[\\p{L} '\\-]*$/u", $name)) {
            throw new \moodle_exception('badname', 'local_oksigeniatesape', '', self::MAX);
        }
        return $name;
    }

    /**
     * Stops a hero name that a classmate already has in the course.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param string $name
     */
    private static function require_free(int $courseid, \stdClass $char, string $name): void {
        global $DB;
        $students = array_keys(view::students($courseid));
        if (!$students) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($students);
        $where = "userid $insql AND id <> ? AND " . $DB->sql_equal('nickname', '?', false);
        if ($DB->record_exists_select(self::T . 'char', $where, array_merge($params, [$char->id, $name]))) {
            throw new \moodle_exception('nametaken', 'local_oksigeniatesape');
        }
    }
}
