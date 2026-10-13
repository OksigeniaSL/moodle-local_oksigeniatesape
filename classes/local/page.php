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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class page {
    /** @var array Scripts of each app, after the core. */
    private const SCRIPTS = [
        'game' => ['scene', 'nest', 'game', 'battle'],
        'teach' => ['workshop', 'author', 'teach'],
        'board' => ['scene', 'board'],
    ];

    /** @var string[] Prefixes of the language strings the client uses. */
    private const PREFIXES = ['app_', 'teach_', 'board_', 'event_', 'role_', 'roledesc_', 'lineage_'];

    /**
     * Prints the page.
     *
     * @param string $app game, teach or board.
     * @param \stdClass $course
     * @param array $config More configuration for the client.
     */
    public static function output(string $app, \stdClass $course, array $config = []): void {
        global $CFG, $USER;
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
        $config += [
            'wwwroot' => $CFG->wwwroot,
            'sesskey' => sesskey(),
            'courseid' => (int) $course->id,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            // Students who go straight into the valley leave to their dashboard: the course would send them back.
            'courseurl' => (portal::goes_straight_in((int) $course->id) ? new \moodle_url('/my/') :
                new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'userid' => (int) $USER->id,
            'firstname' => $USER->firstname,
            'lang' => str_replace('_', '-', $lang),
            'strings' => $strings,
            'art' => art::manifest(),
        ];
        $json = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $version = (int) get_config('local_oksigeniatesape', 'version');
        $base = $CFG->wwwroot . '/local/oksigeniatesape/app/';
        $title = s(get_string('pluginname', 'local_oksigeniatesape'));
        $scripts = '';
        foreach (self::SCRIPTS[$app] ?? [$app] as $js) {
            $scripts .= '<script src="' . $base . $js . '.js?v=' . $version . '"></script>' . "\n";
        }
        @header('Content-Type: text/html; charset=utf-8');
        @header('X-Frame-Options: SAMEORIGIN');
        echo '<!DOCTYPE html>
<html lang="' . s($config['lang']) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f2131">
<meta name="robots" content="noindex">
<title>' . s($config['coursename']) . ' · Tesapé</title>
<link rel="icon" href="' . $base . 'favicon.ico" sizes="16x16 32x32 48x48">
<link rel="icon" href="' . $base . 'favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="' . $base . 'apple-touch-icon.png">
<link rel="preload" href="' . $base . 'fonts/lexend-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="' . $base . 'tesape.css?v=' . $version . '">
</head>
<body class="tesape">
<div id="app" aria-live="polite"><noscript>' . $title . '</noscript></div>
<script>window.TESAPE_CONFIG = ' . $json . ';</script>
<script src="' . $base . 'core.js?v=' . $version . '"></script>
' . $scripts . '
</body>
</html>';
    }
}
