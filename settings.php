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
 * Settings of the plugin for the whole site.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_oksigeniatesape', get_string('pluginname', 'local_oksigeniatesape'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configtext(
            'local_oksigeniatesape/weeklycap',
            get_string('settings_weeklycap', 'local_oksigeniatesape'),
            get_string('settings_weeklycap_desc', 'local_oksigeniatesape'),
            \local_oksigeniatesape\local\engine::SITE_WEEKLY_CAP,
            PARAM_INT
        ));
        // What the game needs from the site, asked when the plugin is installed.
        $settings->add(new admin_setting_heading(
            'local_oksigeniatesape/requirements',
            get_string('settings_requirements', 'local_oksigeniatesape'),
            ''
        ));
        $settings->add(new \local_oksigeniatesape\admin\setting_allowstealth());
        // The competencies of Moodle: the game works without them, but then the scenes leave no evidence.
        if (\core_competency\api::is_enabled()) {
            $competencies = get_string('settings_competencies_on', 'local_oksigeniatesape');
        } else {
            $where = html_writer::link(
                new moodle_url('/admin/settings.php', ['section' => 'optionalsubsystems']),
                get_string('advancedfeatures', 'admin') . ' › ' . get_string('enablecompetencies', 'core_competency')
            );
            $competencies = get_string('settings_competencies_off', 'local_oksigeniatesape', $where);
        }
        $settings->add(new admin_setting_description(
            'local_oksigeniatesape/competencies',
            get_string('settings_competencies', 'local_oksigeniatesape'),
            $competencies
        ));
    }
}
