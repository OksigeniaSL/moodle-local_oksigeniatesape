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

/**
 * The teacher panel of the game in a course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/oksigeniatesape:award', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniatesape/teach.php', ['id' => $course->id]));
$caps = [];
foreach (['manageregion', 'award', 'approvepowers', 'pause', 'viewlog', 'undo', 'runclassscreen'] as $cap) {
    $caps[$cap] = has_capability('local/oksigeniatesape:' . $cap, $context);
}
// The administration of the site, who can turn on what the game needs from it.
$caps['siteconfig'] = has_capability('moodle/site:config', context_system::instance());
\local_oksigeniatesape\local\page::output('teach', $course, ['caps' => $caps]);
