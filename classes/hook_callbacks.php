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

namespace local_oksigeniatesape;

use core\hook\navigation\primary_extend;
use core\hook\navigation\secondary_extend;
use core\hook\output\before_http_headers;
use core\hook\output\before_standard_top_of_body_html_generation;
use local_oksigeniatesape\local\portal;
use local_oksigeniatesape\local\regions;

/**
 * Hook callbacks: the ways into the game from Moodle: the cover of the course page, the course bar and the top bar.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * In the course bar: the game for students where it is on, the panel for teachers always (to turn it on).
     *
     * @param secondary_extend $hook
     */
    public static function secondary_extend(secondary_extend $hook): void {
        global $PAGE;
        $course = $PAGE->course;
        if (!$course || $course->id == SITEID || !isloggedin() || isguestuser()) {
            return;
        }
        $context = \context_course::instance($course->id);
        if (has_capability('local/oksigeniatesape:award', $context)) {
            $url = new \moodle_url('/local/oksigeniatesape/teach.php', ['id' => $course->id]);
            $label = get_string('navteach', 'local_oksigeniatesape');
        } else if (has_capability('local/oksigeniatesape:play', $context) && regions::enabled((int) $course->id)) {
            $url = new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $course->id]);
            $label = get_string('navplay', 'local_oksigeniatesape');
        } else {
            return;
        }
        $view = $hook->get_secondaryview();
        $node = \navigation_node::create(
            $label,
            $url,
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_oksigeniatesape',
            new \pix_icon('i/star', '')
        );
        $view->add_node($node);
    }

    /**
     * In the top bar of the site: «Tesapé», to the courses where one plays or teaches it.
     *
     * @param primary_extend $hook
     */
    public static function primary_extend(primary_extend $hook): void {
        global $USER;
        if (!isloggedin() || isguestuser() || !portal::has_world((int) $USER->id)) {
            return;
        }
        $hook->get_primaryview()->add(
            get_string('navworld', 'local_oksigeniatesape'),
            new \moodle_url('/local/oksigeniatesape/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_oksigeniatesape_world'
        );
    }

    /**
     * Before the course page is sent: where the teacher chose so, students go straight into the valley.
     *
     * @param before_http_headers $hook
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;
        if (!self::is_course_page() || !portal::goes_straight_in((int) $PAGE->course->id)) {
            return;
        }
        redirect(new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $PAGE->course->id]));
    }

    /**
     * On the course page: the cover of the game the teacher chose, moved to the top of the content.
     *
     * @param before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html_generation(before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (!self::is_course_page()) {
            return;
        }
        $cover = portal::cover($PAGE->course);
        if ($cover === '') {
            return;
        }
        $hook->add_html(str_replace('<section id="tesape-portal"', '<section hidden id="tesape-portal"', $cover));
        $PAGE->requires->js_init_code(
            "var p = document.getElementById('tesape-portal');" .
            "var main = document.querySelector('#region-main [role=\"main\"]') || document.getElementById('region-main');" .
            "if (p && main) { main.insertBefore(p, main.firstChild); p.hidden = false; }"
        );
    }

    /**
     * Whether the page is the page of a course (not the site's front page).
     *
     * @return bool
     */
    private static function is_course_page(): bool {
        global $PAGE;
        return strpos((string) $PAGE->pagetype, 'course-view-') === 0 && !empty($PAGE->course->id) &&
            $PAGE->course->id != SITEID;
    }
}
