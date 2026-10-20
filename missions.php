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
 * The missions of Tesapé on the site: those that come with the plugin and those uploaded to it, and the upload of a
 * package (a mission bought, or a newer version of one).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_oksigeniatesape\local\missions;

admin_externalpage_setup('local_oksigeniatesape_missions');
$url = new moodle_url('/local/oksigeniatesape/missions.php');
$form = new \local_oksigeniatesape\form\mission_upload($url);
if ($form->get_data()) {
    $zip = make_request_directory() . '/mission.zip';
    $form->save_file('package', $zip, true);
    try {
        $mission = missions::upload($zip);
        $a = (object) ['title' => missions::text($mission['title'], array_key_first($mission['skins'] ?? []) ?? ''),
            'version' => (int) $mission['version']];
        $done = get_string('missions_uploaded', 'local_oksigeniatesape', $a);
        redirect($url, $done, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (moodle_exception $e) {
        redirect($url, $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$table = new html_table();
$table->head = array_map(
    fn($k) => get_string('missions_' . $k, 'local_oksigeniatesape'),
    ['title', 'code', 'version', 'licence', 'origin', 'lastchange']
);
foreach (missions::packages() as $code => $mission) {
    $skin = (string) (array_key_first($mission['skins'] ?? []) ?? '');
    $change = $mission['changes'][0] ?? null;
    $licence = ($mission['licence'] ?? 'free') === 'paid' ? 'paid' : 'free';
    $table->data[] = [
        s(missions::text($mission['title'] ?? $code, $skin)),
        s($code),
        (int) ($mission['version'] ?? 1),
        implode(' · ', array_filter([
            get_string('missions_licence_' . $licence, 'local_oksigeniatesape'),
            implode(' ' . get_string('teach_mission_or', 'local_oksigeniatesape') . ' ', array_map(
                fn($r) => $r['url'] ? html_writer::link($r['url'], s($r['name'])) : s($r['name']),
                missions::rights($mission)
            )),
        ])),
        get_string('missions_origin_' . missions::package_origin($code), 'local_oksigeniatesape'),
        $change ? s(get_string(
            'missions_kind_' . clean_param($change['kind'] ?? 'improvement', PARAM_ALPHA),
            'local_oksigeniatesape'
        ) . ' · ' . ($change['text'] ?? '')) : '',
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('missions_admin', 'local_oksigeniatesape'));
echo html_writer::tag('p', get_string('missions_admin_help', 'local_oksigeniatesape'));
echo html_writer::table($table);
$form->display();
echo $OUTPUT->footer();
