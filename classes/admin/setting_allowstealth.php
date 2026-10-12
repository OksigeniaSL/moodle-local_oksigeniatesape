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

namespace local_oksigeniatesape\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * What the game needs from the site: activities available but not shown on the course page («stealth»), so that the
 * activities of a mission can stay inside the valley. The box writes the setting of the site itself. Until the
 * administration has answered it once, it counts as new: it shows when the plugin is installed or upgraded.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_allowstealth extends \admin_setting_configcheckbox {
    /**
     * The box, checked by default.
     */
    public function __construct() {
        parent::__construct(
            'local_oksigeniatesape/allowstealth',
            get_string('settings_allowstealth', 'local_oksigeniatesape'),
            get_string('settings_allowstealth_desc', 'local_oksigeniatesape'),
            1
        );
    }

    /**
     * The setting of the site, once the administration has answered; null before, so that it shows as new.
     *
     * @return string|null
     */
    public function get_setting() {
        global $CFG;
        if (get_config('local_oksigeniatesape', 'allowstealthasked') === false) {
            return null;
        }
        return empty($CFG->allowstealth) ? $this->no : $this->yes;
    }

    /**
     * Writes the setting of the site.
     *
     * @param mixed $data
     * @return string Empty: no error.
     */
    public function write_setting($data) {
        set_config('allowstealth', ((string) $data === $this->yes) ? 1 : 0);
        set_config('allowstealthasked', 1, 'local_oksigeniatesape');
        return '';
    }
}
