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
 * The pack for home of a student, as a PDF to print: the place where the class is now, on paper.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$userid = required_param('user', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/oksigeniatesape:viewlog', $context);
require_sesskey();
if (!is_enrolled($context, $userid)) {
    throw new invalid_parameter_exception('Not a student of this course');
}

$filename = clean_filename(get_string('homepack_file', 'local_oksigeniatesape') . '-' . $course->shortname . '-' .
    userdate(time(), '%Y-%m-%d'));
send_file(\local_oksigeniatesape\local\homepack::pdf($course, $userid), $filename . '.pdf', 0, 0, true, true, 'application/pdf');
