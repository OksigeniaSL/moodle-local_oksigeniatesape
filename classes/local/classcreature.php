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
 * In infant school there are no characters: the class has one creature, on the class screen, that grows with what the
 * class does together. A lantern lit, a battle won, a scene played with the whole class: each one makes it grow, and at
 * some points it changes, as the creature of a student does.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class classcreature {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int[] What it has to have grown to be in each stage, from the first to the fifth. */
    public const STAGES = [0, 10, 25, 45, 70];

    /** @var int[] What each thing the class does makes it grow. */
    public const GROWTH = ['lantern' => 5, 'battle' => 3, 'scene' => 2];

    /**
     * The creature of the class of a course, while the class is in infant school; null in the other stages.
     *
     * @param int $courseid
     * @return array|null name, hue, grown, stage (1 to 5), from (what the stage starts at), next (what the next one
     *     needs; 0 in the last), grewat (when it last changed).
     */
    public static function view(int $courseid): ?array {
        $region = regions::get($courseid);
        if (!$region || regions::stage($region) !== 'early') {
            return null;
        }
        $data = self::data($region);
        $stage = 1;
        foreach (self::STAGES as $i => $from) {
            if ($data['grown'] >= $from) {
                $stage = $i + 1;
            }
        }
        $name = $data['name'] !== '' ? $data['name'] : get_string('classcreature_name', 'local_oksigeniatesape');
        return ['name' => $name, 'hue' => $data['hue'], 'grown' => $data['grown'], 'stage' => $stage,
            'from' => self::STAGES[$stage - 1], 'next' => self::STAGES[$stage] ?? 0, 'grewat' => $data['grewat']];
    }

    /**
     * The class did something together: its creature grows, in infant school.
     *
     * @param int $courseid
     * @param string $what lantern, battle or scene.
     * @param int|null $now
     */
    public static function grow(int $courseid, string $what, ?int $now = null): void {
        global $DB;
        $region = regions::get($courseid);
        if (!$region || regions::stage($region) !== 'early' || !isset(self::GROWTH[$what])) {
            return;
        }
        $before = self::view($courseid)['stage'];
        $data = self::data($region);
        $data['grown'] += self::GROWTH[$what];
        $DB->set_field(self::T . 'region', 'creature', json_encode($data), ['id' => $region->id]);
        regions::forget();
        if (self::view($courseid)['stage'] > $before) {
            $data['grewat'] = $now ?? time();
            $DB->set_field(self::T . 'region', 'creature', json_encode($data), ['id' => $region->id]);
            regions::forget();
        }
    }

    /**
     * The teacher names the creature of the class.
     *
     * @param int $courseid
     * @param string $name Empty: the name of the plugin.
     */
    public static function name(int $courseid, string $name): void {
        global $DB;
        $region = regions::get($courseid);
        if (!$region) {
            return;
        }
        $data = self::data($region);
        $data['name'] = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 40);
        $DB->set_field(self::T . 'region', 'creature', json_encode($data), ['id' => $region->id]);
        regions::forget();
    }

    /**
     * The name the teacher gave the creature of the class, in any stage (empty: none).
     *
     * @param \stdClass|null $region
     * @return string
     */
    public static function given_name(?\stdClass $region): string {
        return $region ? self::data($region)['name'] : '';
    }

    /**
     * What the region keeps of its creature, with its colour from the course when it has none.
     *
     * @param \stdClass $region
     * @return array name, hue, grown, grewat.
     */
    private static function data(\stdClass $region): array {
        $data = json_decode((string) ($region->creature ?? ''), true) ?: [];
        return ['name' => (string) ($data['name'] ?? ''), 'hue' => (int) ($data['hue'] ?? ((int) $region->courseid * 47) % 360),
            'grown' => (int) ($data['grown'] ?? 0), 'grewat' => (int) ($data['grewat'] ?? 0)];
    }
}
