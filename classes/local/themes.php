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
 * The colours of the game. The plugin brings two themes, the night of the valley and the day (light, for the board and
 * for bright classrooms); the teacher chooses one for the class, or the one of the world. A world (its art pack) says
 * which is its own and may bring its colours over either of them: only colours, checked one by one, never other CSS.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class themes {
    /** @var string[] The themes of the plugin. */
    public const BASES = ['night', 'day'];

    /** @var string[] What a teacher can choose: the theme of the world (empty), or one of the plugin. */
    public const LOOKS = ['', 'night', 'day'];

    /** @var string[] The colours a world can change: the variables of the stylesheet, without the two dashes. */
    public const COLOURS = ['night', 'night-2', 'night-mid', 'deep', 'mist', 'mist-2', 'card-a', 'card-b', 'lantern',
        'lantern-deep', 'lantern-light', 'on-lantern', 'accent', 'leaf', 'ember', 'lilac', 'sky', 'text', 'text-soft',
        'leaf-text', 'lantern-text', 'ember-text', 'lilac-text', 'danger-text', 'line'];

    /** @var array The colours also used with transparency, and the variable with their red, green and blue. */
    private const RGB = ['night' => 'night-rgb', 'deep' => 'deep-rgb', 'lantern' => 'lantern-rgb', 'leaf' => 'leaf-rgb',
        'ember' => 'ember-rgb', 'lilac' => 'lilac-rgb', 'text' => 'hi'];

    /** @var array The colour of the bar of the browser, for each theme of the plugin. */
    private const BAR = ['night' => '#0f2131', 'day' => '#eef4f8'];

    /**
     * The theme of a world, as its art pack brings it, with only what is valid: its own base and its colours.
     *
     * @param mixed $theme The theme in pack.json: {"base": "night", "night": {"lantern": "#ffc857"}, "day": {...}}.
     * @return array base, and the colours for each base that has them.
     */
    public static function clean($theme): array {
        $theme = is_array($theme) ? $theme : [];
        $clean = ['base' => in_array($theme['base'] ?? '', self::BASES, true) ? $theme['base'] : 'night'];
        foreach (self::BASES as $base) {
            $colours = [];
            foreach ((array) ($theme[$base] ?? []) as $name => $value) {
                if (in_array($name, self::COLOURS, true) && is_string($value) && self::is_colour($value)) {
                    $colours[$name] = strtolower($value);
                }
            }
            if ($colours) {
                $clean[$base] = $colours;
            }
        }
        return $clean;
    }

    /**
     * Whether a text is a colour as the packs give them: #rgb, #rrggbb or #rrggbbaa.
     *
     * @param string $value
     * @return bool
     */
    public static function is_colour(string $value): bool {
        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value);
    }

    /**
     * The theme of a course: the one the teacher chose, or else the one of the world.
     *
     * @param string $look What the teacher chose (empty: the one of the world).
     * @param array|null $art The art pack of the site, as art::manifest() gives it.
     * @return string night or day.
     */
    public static function current(string $look, ?array $art): string {
        if (in_array($look, self::BASES, true)) {
            return $look;
        }
        return self::clean($art['theme'] ?? null)['base'];
    }

    /**
     * The colours of the world over the themes of the plugin, as CSS for the page. Empty if the world brings none.
     *
     * @param array|null $art The art pack of the site.
     * @return string
     */
    public static function style(?array $art): string {
        $theme = self::clean($art['theme'] ?? null);
        $css = '';
        foreach (self::BASES as $base) {
            if (empty($theme[$base])) {
                continue;
            }
            $vars = [];
            foreach ($theme[$base] as $name => $value) {
                $vars[] = '--' . $name . ':' . $value;
                if (isset(self::RGB[$name])) {
                    $vars[] = '--' . self::RGB[$name] . ':' . implode(',', self::rgb($value));
                }
            }
            $css .= '[data-theme="' . $base . '"]{' . implode(';', $vars) . '}';
        }
        return $css;
    }

    /**
     * The colour of the bar of the browser for a theme, with the background of the world if it brings one.
     *
     * @param string $current night or day.
     * @param array|null $art
     * @return string
     */
    public static function bar(string $current, ?array $art): string {
        $theme = self::clean($art['theme'] ?? null);
        $colour = $theme[$current]['night'] ?? self::BAR[$current] ?? self::BAR['night'];
        return vsprintf('#%02x%02x%02x', self::rgb($colour));
    }

    /**
     * The red, green and blue of a colour.
     *
     * @param string $colour #rgb, #rrggbb or #rrggbbaa.
     * @return int[]
     */
    public static function rgb(string $colour): array {
        $hex = ltrim($colour, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
