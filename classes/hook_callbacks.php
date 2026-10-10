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

use core\hook\navigation\secondary_extend;
use local_oksigeniatesape\local\regions;

/**
 * Hook callbacks: the way into the game from a course.
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
}
