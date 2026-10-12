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

namespace local_oksigeniatesape\local;

use core_competency\competency;

/**
 * The gradebook of Moodle, if the teacher wants it (off by default): the rating the teacher gives in the competencies
 * of each objective goes to an item of the gradebook, with the scale of its competency, in a category of the mission.
 * Only what the teacher rates: never the behaviour, the resources or the level of the game. Turned off, the items stay as
 * they were, so nothing of the teacher is lost.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class gradebook {
    /**
     * Whether the teacher of a course takes the ratings to the gradebook.
     *
     * @param int $courseid
     * @return bool
     */
    public static function on(int $courseid): bool {
        return !empty(regions::get($courseid)->gradebook);
    }

    /**
     * The rating of an objective, to the gradebook, if the teacher wants it. Never in the way of the competencies.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $objective
     * @param int $grade Of the scale, from 1.
     */
    public static function set(int $courseid, int $userid, string $objective, int $grade): void {
        if (!self::on($courseid)) {
            return;
        }
        try {
            $mission = missions::get($courseid);
            $links = competencies::links($courseid);
            if ($mission && !empty($links[$objective])) {
                self::item($courseid, $mission, $objective, (int) $links[$objective])
                    ->update_final_grade($userid, $grade, 'local_oksigeniatesape');
            }
        } catch (\Throwable $e) {
            debugging('Tesapé could not take the rating to the gradebook: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Makes the items of the mission in the gradebook and takes to them every rating the teacher has given already.
     *
     * @param int $courseid
     * @return int Ratings taken.
     */
    public static function sync(int $courseid): int {
        global $DB;
        $mission = missions::get($courseid);
        if (!self::on($courseid) || !$mission) {
            return 0;
        }
        $taken = 0;
        foreach (competencies::links($courseid) as $objective => $competencyid) {
            if (!$competencyid) {
                continue;
            }
            $item = self::item($courseid, $mission, (string) $objective, (int) $competencyid);
            $where = 'courseid = ? AND competencyid = ? AND grade IS NOT NULL';
            $params = [$courseid, $competencyid];
            $ratings = $DB->get_records_select('competency_usercompcourse', $where, $params, '', 'id, userid, grade');
            foreach ($ratings as $rating) {
                $item->update_final_grade((int) $rating->userid, (int) $rating->grade, 'local_oksigeniatesape');
                $taken++;
            }
        }
        return $taken;
    }

    /**
     * The item of an objective in the gradebook, made if it is not there: in the category of the mission, with the
     * scale of its competency.
     *
     * @param int $courseid
     * @param \stdClass $mission With data.
     * @param string $objective
     * @param int $competencyid
     * @return \grade_item
     */
    private static function item(int $courseid, \stdClass $mission, string $objective, int $competencyid): \grade_item {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $idnumber = 'tesape_' . $mission->code . '_' . $objective;
        $item = \grade_item::fetch(['courseid' => $courseid, 'idnumber' => $idnumber]);
        if ($item) {
            return $item;
        }
        $name = missions::text($mission->data['objectives'][$objective] ?? $objective, (string) $mission->skin);
        $scale = (new competency($competencyid))->get_scale();
        $item = new \grade_item([
            'courseid' => $courseid,
            'categoryid' => self::category($courseid, $mission)->id,
            'itemtype' => 'manual',
            'itemname' => $objective . ' · ' . $name,
            'idnumber' => $idnumber,
            'gradetype' => GRADE_TYPE_SCALE,
            'scaleid' => $scale->id,
        ], false);
        $item->insert('local_oksigeniatesape');
        return $item;
    }

    /**
     * The category of the mission in the gradebook, made if it is not there.
     *
     * @param int $courseid
     * @param \stdClass $mission With data.
     * @return \grade_category
     */
    private static function category(int $courseid, \stdClass $mission): \grade_category {
        $name = 'Tesapé · ' . missions::text($mission->data['title'] ?? $mission->code, (string) $mission->skin);
        $category = \grade_category::fetch(['courseid' => $courseid, 'fullname' => $name]);
        if ($category) {
            return $category;
        }
        $category = new \grade_category(['courseid' => $courseid, 'fullname' => $name], false);
        $category->apply_default_settings();
        $category->apply_forced_settings();
        $category->insert('local_oksigeniatesape');
        return $category;
    }
}
