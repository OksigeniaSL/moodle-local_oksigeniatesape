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
 * Prints the page of the game client or the teacher panel: a document of its own, full screen, without the
 * interface of Moodle, with its configuration and texts inside so that it starts at once.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page {
    /** @var array Scripts of each app, after the core. */
    private const SCRIPTS = [
        'game' => ['fx', 'critters', 'scene', 'minigame', 'nest', 'errors', 'whack', 'venn', 'whoami', 'guesswho', 'terrarium', 'leaf', 'chain',
            'burst', 'stage', 'puppet', 'cast', 'walk', 'sceneworld', 'nestworld', 'game', 'exercises', 'battle', 'huddle'],
        'teach' => ['fx', 'scene', 'workshop', 'author', 'teach'],
        'board' => ['fx', 'critters', 'scene', 'stage', 'puppet', 'cast', 'sceneworld', 'battleworld', 'exercises', 'board'],
    ];

    /** @var string[] Prefixes of the language strings the client uses. */
    private const PREFIXES = ['app_', 'teach_', 'board_', 'event_', 'role_', 'roledesc_', 'lineage_'];

    /**
     * Prints the page.
     *
     * @param string $app game, teach or board.
     * @param \stdClass $course
     * @param array $config More configuration for the client.
     * @param int $game The game, if it is one more of the course.
     */
    public static function output(string $app, \stdClass $course, array $config = [], int $game = 0): void {
        global $CFG, $USER;
        // The game: the course, or a game more in it.
        $game = $game ?: (int) $course->id;
        $context = \context_course::instance($course->id);
        $strings = [];
        $lang = current_language();
        foreach (get_string_manager()->load_component_strings('local_oksigeniatesape', $lang) as $key => $text) {
            foreach (self::PREFIXES as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $strings[$key] = $text;
                    break;
                }
            }
        }
        // The story of the mission may tell some texts in its own way, when the game speaks its language.
        $mission = missions::get($game);
        $told = $mission ? ($mission->data['texts'][(string) $mission->skin] ?? []) : [];
        if (is_array($told) && strpos($lang, (string) ($mission->data['lang'] ?? '')) === 0) {
            foreach ($told as $key => $text) {
                if (isset($strings[$key]) && is_string($text) && trim($text) !== '') {
                    $strings[$key] = $text;
                }
            }
        }
        $config += [
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => sesskey(),
            'courseid' => $game,
            'coursename' => games::name($game),
            // Students who go straight into the valley leave to their dashboard: the course would send them back.
            'courseurl' => (portal::goes_straight_in($game) ? new \moodle_url('/my/') :
                new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'userid' => (int) $USER->id,
            'firstname' => $USER->firstname,
            'lang' => str_replace('_', '-', $lang),
            'strings' => $strings,
            'art' => art::client(),
        ];
        // The theme of the class, chosen by the teacher or else the one of the world; the board may switch it.
        $region = regions::get($game);
        $look = (string) ($region->look ?? '');
        $theme = themes::current($look, $config['art']);
        $config['theme'] = ['current' => $theme, 'world' => themes::current('', $config['art'])];
        // The sky of the valley: the hour and the season where the course is (in the south, the seasons the other way).
        $zone = engine::timezone($game);
        $where = $zone->getLocation();
        $config['sky'] = ['zone' => $zone->getName(), 'south' => $where && $where['latitude'] < 0 && $where['latitude'] > -89];
        // Which page, and whether the tablets of the class take the small copy of the big images.
        $config['app'] = $app;
        $config['lightart'] = $app === 'game' && $region && regions::rule($region, 'lightart') > 0;
        $worldcolours = themes::style($config['art']);
        $version = (int) get_config('local_oksigeniatesape', 'version');
        // The version, for what the client loads later (the engine of the moments that are played).
        $config['version'] = $version;
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $base = $CFG->wwwroot . '/local/oksigeniatesape/app/';
        $title = s(get_string('pluginname', 'local_oksigeniatesape'));
        $scripts = '';
        foreach (self::SCRIPTS[$app] ?? [$app] as $js) {
            $scripts .= '<script src="' . $base . $js . '.js?v=' . $version . '"></script>' . "\n";
        }
        $access = $app === 'board' ? '' : self::access();
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: SAMEORIGIN');
        echo '<!DOCTYPE html>
<html lang="' . s($config['lang']) . '" data-theme="' . $theme . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="' . themes::bar($theme, $config['art']) . '">
<meta name="robots" content="noindex">
<title>' . s($config['coursename']) . ' · Tesapé</title>
<link rel="icon" href="' . $base . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="' . $base . 'favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $base . 'apple-touch-icon.png">
<link rel="preload" href="' . $base . 'fonts/lexend-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="' . $base . 'tesape.css?v=' . $version . '">
' . ($worldcolours ? '<style>' . $worldcolours . '</style>' . "\n" : '') . '</head>
<body class="tesape">
<div id="app" aria-live="polite"><noscript>' . $title . '</noscript></div>
<script>window.TESAPE_CONFIG = ' . $json . ';</script>
<script src="' . $base . 'core.js?v=' . $version . '"></script>
' . $scripts . $access . '
</body>
</html>';
    }

    /**
     * The panel of Oksigenia Access, if the site has it: the pages of the game are documents of their own, without the
     * footer of Moodle where it usually comes in. Not on the class screen, which is projected.
     *
     * @return string
     */
    private static function access(): string {
        $panel = '\\local_oksigeniaaccess\\local\\hook_callbacks';
        if (!class_exists($panel) || !method_exists($panel, 'get_footer_html')) {
            return '';
        }
        try {
            $html = (string) $panel::get_footer_html();
            // In the game the left edge carries the text of the answers: its button goes to the right edge, out of the way.
            $html = preg_replace('/ position(-mobile)?="[^"]*"/', '', $html);
            return str_replace('<oksigenia-access-panel', '<oksigenia-access-panel position="mid-right"', $html);
        } catch (\Throwable $e) {
            // Accessibility never stops the game.
            debugging('Tesapé could not add the panel of Oksigenia Access: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }
}
