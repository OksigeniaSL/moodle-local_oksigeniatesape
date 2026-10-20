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
 * A mission made in the workshop (here or on another site), loaded from its package as a new draft of the course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/oksigeniatesape:manageregion', $context);

$url = new moodle_url('/local/oksigeniatesape/author_import.php', ['id' => $course->id]);
$back = new moodle_url('/local/oksigeniatesape/teach.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('teach_au_import', 'local_oksigeniatesape'));
$PAGE->set_heading(format_string($course->fullname));

$form = new \local_oksigeniatesape\form\mission_upload($url, ['label' => get_string(
    'teach_au_import_file',
    'local_oksigeniatesape'
), 'button' => get_string('teach_au_import_go', 'local_oksigeniatesape')]);
if ($form->is_cancelled()) {
    redirect($back);
}
if ($form->get_data()) {
    $zip = make_request_directory() . '/mission.zip';
    $form->save_file('package', $zip, true);
    try {
        \local_oksigeniatesape\local\authoring::import($course->id, $zip);
        redirect(
            $back,
            get_string('teach_au_imported', 'local_oksigeniatesape'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teach_au_import', 'local_oksigeniatesape'));
echo html_writer::tag('p', get_string('teach_au_import_help', 'local_oksigeniatesape'));
$form->display();
echo html_writer::link($back, get_string('teach_ws_back', 'local_oksigeniatesape'));
echo $OUTPUT->footer();
