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

use local_oksigeniatesape\local\edits;
use local_oksigeniatesape\local\missions;

/**
 * Brings back the game of a course from a backup: its settings, the mission (installed again from the package of the
 * plugin, without making its activities again: those come with the backup), the classroom challenges, the challenges
 * of each activity and the powers of the course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_oksigeniatesape_plugin extends restore_local_plugin {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var array The codes of the missions the teacher made: in the backup => in this course. */
    private $owncodes = [];

    /**
     * What the course part of the backup has.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        return [
            new restore_path_element('oksigeniatesape_region', $this->get_pathfor('/region')),
            new restore_path_element('oksigeniatesape_own', $this->get_pathfor('/owns/own')),
            new restore_path_element('oksigeniatesape_mission', $this->get_pathfor('/mission')),
            new restore_path_element('oksigeniatesape_challenge', $this->get_pathfor('/challenges/challenge')),
            new restore_path_element('oksigeniatesape_power', $this->get_pathfor('/powers/power')),
            new restore_path_element('oksigeniatesape_edit', $this->get_pathfor('/edits/edit')),
        ];
    }

    /**
     * What the part of each activity has.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [new restore_path_element('oksigeniatesape_cmchallenge', $this->get_pathfor('/challenge'))];
    }

    /**
     * The settings of the game in the course, on top of any it already has.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_region($data) {
        global $DB;
        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        $now = time();
        $record = (object) [
            'courseid' => $courseid,
            'enabled' => (int) $data->enabled,
            'family' => $data->family,
            'groupingid' => $data->groupingid ? (int) $this->get_mappingid('grouping', $data->groupingid) : 0,
            'weeklycap' => (int) $data->weeklycap,
            'dailymanualcap' => (int) $data->dailymanualcap,
            'dailyenergylosscap' => (int) $data->dailyenergylosscap,
            'rules' => $data->rules,
            'leaderboard' => (int) $data->leaderboard,
            'nicknames' => (int) $data->nicknames,
            'timezone' => $data->timezone ?? '99',
            'portal' => in_array($data->portal ?? '', \local_oksigeniatesape\local\portal::MODES, true) ? $data->portal : 'door',
            'hideacts' => (int) ($data->hideacts ?? 0),
            'gradebook' => (int) ($data->gradebook ?? 0),
            'timemodified' => $now,
        ];
        $existing = $DB->get_field(self::T . 'region', 'id', ['courseid' => $courseid]);
        if ($existing) {
            $record->id = $existing;
            $DB->update_record(self::T . 'region', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record(self::T . 'region', $record);
        }
    }

    /**
     * The mission, installed again from the package of the plugin if the site has it.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_mission($data) {
        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        if (isset($this->owncodes[$data->code])) {
            // A mission the teacher made: it comes back with the code of its copy.
            missions::install($courseid, $this->owncodes[$data->code], $data->skin, $data->variant);
            return;
        }
        if (!isset(missions::packages()[$data->code])) {
            $this->log('local_oksigeniatesape: this site does not have the mission ' . $data->code, backup::LOG_WARNING);
            return;
        }
        missions::install($courseid, $data->code, $data->skin, $data->variant);
    }

    /**
     * A mission the teacher made, with a code of its own in this course.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_own($data) {
        global $DB;
        $data = (object) $data;
        $now = time();
        $id = $DB->insert_record(self::T . 'own', (object) ['courseid' => $this->task->get_courseid(), 'code' => '',
            'version' => (int) $data->version, 'draft' => (string) $data->draft, 'timecreated' => $now, 'timemodified' => $now]);
        $code = \local_oksigeniatesape\local\authoring::PREFIX . $id;
        // The mission and its scenes, as they were made, with the new code.
        $mission = json_decode((string) ($data->mission ?? ''), true);
        $scenes = json_decode((string) ($data->scenes ?? ''), true);
        if (is_array($mission)) {
            $mission['code'] = $code;
        }
        if (is_array($scenes)) {
            foreach ($scenes as &$scene) {
                $scene['mission'] = $code;
            }
            unset($scene);
        }
        $DB->update_record(self::T . 'own', (object) ['id' => $id, 'code' => $code,
            'mission' => is_array($mission) ? json_encode($mission) : null,
            'scenes' => is_array($scenes) ? json_encode($scenes) : null]);
        $this->owncodes[(string) $data->code] = $code;
    }

    /**
     * A change of the teacher to the mission, on top of any the course already has.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_edit($data) {
        $data = (object) $data;
        edits::store(
            $this->task->get_courseid(),
            (string) $data->mission,
            (string) $data->scene,
            (string) $data->path,
            (string) $data->value
        );
    }

    /**
     * A classroom challenge. The mission already made those of its chapters: the one of the backup updates it.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_challenge($data) {
        global $DB;
        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        $record = $this->challenge($data, $courseid);
        $record->cmid = 0;
        $same = $DB->get_records(self::T . 'challenge', ['courseid' => $courseid, 'cmid' => 0, 'kind' => $record->kind,
            'chapterid' => $record->chapterid]);
        $same = array_filter($same, fn($c) => $c->name === $record->name);
        if ($same) {
            $record->id = reset($same)->id;
            $DB->update_record(self::T . 'challenge', $record);
        } else {
            $record->timecreated = $record->timemodified;
            $DB->insert_record(self::T . 'challenge', $record);
        }
    }

    /**
     * The challenge of an activity, for the activity made by the restore.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_cmchallenge($data) {
        global $DB;
        $data = (object) $data;
        $cmid = $this->task->get_moduleid();
        if ($DB->record_exists(self::T . 'challenge', ['cmid' => $cmid])) {
            return;
        }
        $record = $this->challenge($data, $this->task->get_courseid());
        $record->cmid = $cmid;
        $record->timecreated = $record->timemodified;
        $DB->insert_record(self::T . 'challenge', $record);
    }

    /**
     * A power of the course: the one of the catalog with the same code takes its settings; the teacher's own are added.
     *
     * @param array|object $data
     */
    public function process_oksigeniatesape_power($data) {
        global $DB;
        $data = (object) $data;
        $courseid = $this->task->get_courseid();
        $now = time();
        $record = (object) [
            'courseid' => $courseid,
            'role' => $data->role,
            'code' => $data->code,
            'name' => $data->name,
            'description' => $data->description,
            'cost' => (int) $data->cost,
            'minlevel' => (int) $data->minlevel,
            'enabled' => (int) $data->enabled,
            'autoapprove' => (int) $data->autoapprove,
            'timemodified' => $now,
        ];
        $existing = $data->code === 'own' ? null : $DB->get_record(self::T . 'power', ['courseid' => $courseid,
            'code' => $data->code]);
        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record(self::T . 'power', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record(self::T . 'power', $record);
        }
    }

    /**
     * A challenge of the backup for this course: its item and its chapter found again by their codes.
     *
     * @param \stdClass $data
     * @param int $courseid
     * @return \stdClass
     */
    private function challenge(\stdClass $data, int $courseid): \stdClass {
        global $DB;
        $itemid = 0;
        if ($data->itemcode) {
            $itemid = missions::item_id($data->itemcode, $data->itemname, $data->itemdescription, $data->itemmission ?: null);
        }
        $chapterid = 0;
        $mission = $data->chaptercode ? missions::get($courseid) : null;
        if ($mission) {
            $where = ['missionid' => $mission->id, 'code' => $data->chaptercode];
            $chapterid = (int) $DB->get_field(self::T . 'chapter', 'id', $where);
        }
        return (object) [
            'courseid' => $courseid,
            'kind' => $data->kind,
            'name' => $data->name,
            'description' => $data->description,
            'teamwork' => (int) $data->teamwork,
            'saber' => (int) $data->saber,
            'gems' => (int) $data->gems,
            'steps' => $data->steps,
            'itemid' => $itemid,
            'objectives' => $data->objectives,
            'chapterid' => $chapterid,
            'timemodified' => time(),
        ];
    }
}
