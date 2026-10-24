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

namespace local_oksigeniatesape\event;

/**
 * A student finished a minigame of the mission.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class minigame_completed extends \core\event\base {
    /**
     * Its kind.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_oksigeniatesape_minigame';
    }

    /**
     * Its name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventlog_minigamecompleted', 'local_oksigeniatesape');
    }

    /**
     * What happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' finished the minigame '{$this->other['game']}'" . (!empty($this->other['success']) ? ', won.' : '.');
    }

    /**
     * Where it is seen.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $this->courseid]);
    }

    /**
     * Checks what it carries.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['game'])) {
            throw new \coding_exception('The \'game\' value must be set in other.');
        }
        if (!isset($this->other['success'])) {
            throw new \coding_exception('The \'success\' value must be set in other.');
        }
    }

    /**
     * Its object, when logs are restored: not mapped.
     *
     * @return array|false
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_oksigeniatesape_minigame', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * What it carries in other, when logs are restored: not mapped.
     *
     * @return array|false
     */
    public static function get_other_mapping() {
        return false;
    }
}
