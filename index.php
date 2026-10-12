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
 * «My world»: the courses where the current user plays or teaches the game, each with its way in.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login(null, false);
if (isguestuser()) {
    redirect(new moodle_url('/'));
}
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/oksigeniatesape/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('world_title', 'local_oksigeniatesape'));
$PAGE->set_heading(get_string('world_title', 'local_oksigeniatesape'));

$world = \local_oksigeniatesape\local\portal::world((int) $USER->id);
echo $OUTPUT->header();
echo html_writer::tag('p', s(get_string('world_help', 'local_oksigeniatesape')), ['class' => 'tesape-world-help']);
if (!$world) {
    echo $OUTPUT->notification(get_string('world_none', 'local_oksigeniatesape'), 'info');
}
echo html_writer::start_div('tesape-world');
foreach ($world as $place) {
    $buttons = '';
    foreach ($place['buttons'] as [$url, $label, $main]) {
        $buttons .= html_writer::link($url, s($label), ['class' => 'btn ' . ($main ? 'btn-primary' : 'btn-secondary')]);
    }
    $art = $place['image'] ? ' style="background-image:url(\'' . s($place['image']) . '\')"' : '';
    echo '<section class="tesape-portal tesape-card"><div class="tesape-portal-art"' . $art . '></div>' .
        '<div class="tesape-portal-body"><div class="tesape-portal-kicker">' . s($place['name']) . '</div><h2>' .
        s($place['mission']) . '</h2>' . ($place['line'] ? '<p>' . s($place['line']) . '</p>' : '') .
        ($place['note'] ? '<p class="tesape-portal-note">' . s($place['note']) . '</p>' : '') .
        '<div class="tesape-portal-actions">' . $buttons . '</div></div></section>';
}
echo html_writer::end_div();
echo $OUTPUT->footer();
