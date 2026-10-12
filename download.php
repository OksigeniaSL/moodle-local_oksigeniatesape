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
 * The report of the class, downloaded in the format the teacher chooses (Excel, ODS, CSV, PDF…).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$format = required_param('format', PARAM_ALPHA);
$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/oksigeniatesape:viewlog', $context);
require_sesskey();

$formats = array_keys(core_plugin_manager::instance()->get_enabled_plugins('dataformat'));
if (!in_array($format, $formats, true)) {
    throw new invalid_parameter_exception('Not a format of this site: ' . $format);
}
$filename = clean_filename('tesape-' . $course->shortname . '-' . userdate(time(), '%Y-%m-%d'));
if ($format === 'pdf') {
    // A PDF laid out to be read, not a sheet printed.
    send_file(\local_oksigeniatesape\local\report::pdf($course), $filename . '.pdf', 0, 0, true, true, 'application/pdf');
}
\core\dataformat::download_data(
    $filename,
    $format,
    array_values(\local_oksigeniatesape\local\report::columns($course->id)),
    new ArrayIterator(\local_oksigeniatesape\local\report::rows($course->id))
);
