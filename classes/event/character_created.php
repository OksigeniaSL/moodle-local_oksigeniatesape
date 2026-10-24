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
 * A student created their hero (their character for the whole site).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class character_created extends \core\event\base {
    /**
     * Its kind.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_oksigeniatesape_char';
    }

    /**
     * Its name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventlog_charactercreated', 'local_oksigeniatesape');
    }

    /**
     * What happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' created their hero, with the id '{$this->objectid}'.";
    }

    /**
     * Where it is seen.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $this->courseid ?: SITEID]);
    }

    /**
     * Its object, when logs are restored: not mapped.
     *
     * @return array|false
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_oksigeniatesape_char', 'restore' => \core\event\base::NOT_MAPPED];
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
