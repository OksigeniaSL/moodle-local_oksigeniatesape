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
 * Test data generator.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_oksigeniatesape_generator extends component_generator_base {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Inserts a row with the times filled in.
     *
     * @param string $table Without the prefix.
     * @param array $defaults
     * @param array $record
     * @return stdClass
     */
    private function insert(string $table, array $defaults, array $record): stdClass {
        global $DB;
        $record = (object) array_merge($defaults, $record);
        $record->id = $DB->insert_record(self::T . $table, $record);
        return $record;
    }

    /**
     * A character.
     *
     * @param array $record At least userid.
     * @return stdClass
     */
    public function create_char(array $record): stdClass {
        return $this->insert('char', [
            'role' => 'sage',
            'nameform' => 'm',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * A character in a course.
     *
     * @param array $record At least charid and courseid.
     * @return stdClass
     */
    public function create_member(array $record): stdClass {
        return $this->insert('member', [
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * An entry of the log.
     *
     * @param array $record At least charid.
     * @return stdClass
     */
    public function create_ledger(array $record): stdClass {
        return $this->insert('ledger', [
            'resource' => 'saber',
            'amount' => 10,
            'reason' => 'manual',
            'timecreated' => time(),
        ], $record);
    }

    /**
     * A power of a course.
     *
     * @param array $record At least courseid.
     * @return stdClass
     */
    public function create_power(array $record): stdClass {
        return $this->insert('power', [
            'role' => 'all',
            'code' => 'extratime',
            'name' => 'Extra time',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * A request to use a power.
     *
     * @param array $record At least powerid and memberid.
     * @return stdClass
     */
    public function create_powerreq(array $record): stdClass {
        return $this->insert('powerreq', ['timecreated' => time()], $record);
    }

    /**
     * An item of the catalogue.
     *
     * @param array $record
     * @return stdClass
     */
    public function create_item(array $record = []): stdClass {
        static $n = 0;
        $n++;
        return $this->insert('item', [
            'code' => 'item' . $n,
            'name' => 'Item ' . $n,
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * An item carried by a character in a course.
     *
     * @param array $record At least charid, itemid and courseid.
     * @return stdClass
     */
    public function create_inventory(array $record): stdClass {
        return $this->insert('inventory', ['source' => 'minigame', 'timecreated' => time()], $record);
    }

    /**
     * A mission in a course.
     *
     * @param array $record At least courseid.
     * @return stdClass
     */
    public function create_mission(array $record): stdClass {
        return $this->insert('mission', [
            'code' => 'livingthings',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * A scene of a mission.
     *
     * @param array $record At least missionid.
     * @return stdClass
     */
    public function create_scene(array $record): stdClass {
        return $this->insert('scene', [
            'code' => 'C1-2',
            'definition' => '{"start":"n1","nodes":{}}',
            'timecreated' => time(),
            'timemodified' => time(),
        ], $record);
    }

    /**
     * A play of a scene.
     *
     * @param array $record At least sceneid, charid and courseid.
     * @return stdClass
     */
    public function create_sceneattempt(array $record): stdClass {
        return $this->insert('sceneattempt', [
            'path' => '["n1"]',
            'ending' => 'success',
            'timestarted' => time(),
            'timefinished' => time(),
        ], $record);
    }

    /**
     * A minigame result.
     *
     * @param array $record At least charid and courseid.
     * @return stdClass
     */
    public function create_minigame(array $record): stdClass {
        return $this->insert('minigame', [
            'game' => 'nestdefence',
            'success' => 1,
            'timecreated' => time(),
        ], $record);
    }
}
