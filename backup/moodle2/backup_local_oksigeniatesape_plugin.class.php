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
 * What the game keeps in a course backup: its settings, the mission (by its code, to install it again), the classroom
 * challenges, the challenges of each activity and the powers of the course. Nothing of the students: their characters
 * belong to the whole site.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_oksigeniatesape_plugin extends backup_local_plugin {
    /** @var string The fields of a challenge in the backup, with its item and chapter by their codes. */
    private const CHALLENGE = ['kind', 'name', 'description', 'teamwork', 'saber', 'gems', 'steps', 'objectives', 'itemcode',
        'itemname', 'itemdescription', 'itemmission', 'chaptercode'];

    /**
     * The game of the course.
     *
     * @return backup_plugin_element
     */
    protected function define_course_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $game = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($game);

        $region = new backup_nested_element('region', ['id'], ['enabled', 'family', 'groupingid', 'weeklycap',
            'dailymanualcap', 'dailyenergylosscap', 'rules', 'leaderboard', 'nicknames', 'timezone', 'portal', 'hideacts',
            'gradebook', 'quiet']);
        $mission = new backup_nested_element('mission', ['id'], ['code', 'skin', 'variant', 'origin']);
        $challenges = new backup_nested_element('challenges');
        $challenge = new backup_nested_element('challenge', ['id'], self::CHALLENGE);
        $powers = new backup_nested_element('powers');
        $power = new backup_nested_element('power', ['id'], ['role', 'code', 'name', 'description', 'cost', 'minlevel',
            'enabled', 'autoapprove']);
        // The missions the teacher made, before the mission of the course, which may be one of them.
        $owns = new backup_nested_element('owns');
        $own = new backup_nested_element('own', ['id'], ['code', 'version', 'draft', 'mission', 'scenes']);
        $game->add_child($region);
        $game->add_child($owns);
        $owns->add_child($own);
        $game->add_child($mission);
        $game->add_child($challenges);
        $challenges->add_child($challenge);
        $game->add_child($powers);
        $powers->add_child($power);
        // What the teacher changed in the mission.
        $edits = new backup_nested_element('edits');
        $edit = new backup_nested_element('edit', ['id'], ['mission', 'scene', 'path', 'value']);
        $game->add_child($edits);
        $edits->add_child($edit);

        $region->set_source_table('local_oksigeniatesape_region', ['courseid' => backup::VAR_COURSEID]);
        $region->annotate_ids('grouping', 'groupingid');
        $mission->set_source_sql(
            'SELECT * FROM {local_oksigeniatesape_mission}
              WHERE id = (SELECT MAX(id) FROM {local_oksigeniatesape_mission} WHERE courseid = ?)',
            [backup::VAR_COURSEID]
        );
        $challenge->set_source_sql(self::challenges('c.courseid = ? AND c.cmid = 0'), [backup::VAR_COURSEID]);
        $power->set_source_table('local_oksigeniatesape_power', ['courseid' => backup::VAR_COURSEID]);
        $edit->set_source_table('local_oksigeniatesape_edit', ['courseid' => backup::VAR_COURSEID]);
        $own->set_source_table('local_oksigeniatesape_own', ['courseid' => backup::VAR_COURSEID]);
        return $plugin;
    }

    /**
     * The challenge of an activity, if it is one.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $game = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($game);
        $challenge = new backup_nested_element('challenge', ['id'], self::CHALLENGE);
        $game->add_child($challenge);
        $challenge->set_source_sql(self::challenges('c.cmid = ?'), [backup::VAR_MODID]);
        return $plugin;
    }

    /**
     * The query of the challenges, with the codes of their item and chapter.
     *
     * @param string $where
     * @return string
     */
    private static function challenges(string $where): string {
        return 'SELECT c.id, c.kind, c.name, c.description, c.teamwork, c.saber, c.gems, c.steps, c.objectives,
                       i.code AS itemcode, i.name AS itemname, i.description AS itemdescription, i.mission AS itemmission,
                       ch.code AS chaptercode
                  FROM {local_oksigeniatesape_challenge} c
             LEFT JOIN {local_oksigeniatesape_item} i ON i.id = c.itemid
             LEFT JOIN {local_oksigeniatesape_chapter} ch ON ch.id = c.chapterid
                 WHERE ' . $where;
    }
}
