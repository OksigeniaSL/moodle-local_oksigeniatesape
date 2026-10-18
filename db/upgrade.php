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
 * Upgrade steps.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrades the plugin tables.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_oksigeniatesape_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026101001) {
        // The look of the character, as data.
        $table = new xmldb_table('local_oksigeniatesape_char');
        $field = new xmldb_field('appearance', XMLDB_TYPE_TEXT, null, null, null, null, null, 'creature');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Classroom challenges: no Moodle activity behind them, so cmid can be 0 and is no longer unique.
        $table = new xmldb_table('local_oksigeniatesape_challenge');
        $dbman->drop_key($table, new xmldb_key('cmid', XMLDB_KEY_FOREIGN_UNIQUE, ['cmid'], 'course_modules', ['id']));
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        $dbman->change_field_default($table, $field);
        $dbman->add_key($table, new xmldb_key('cmid', XMLDB_KEY_FOREIGN, ['cmid'], 'course_modules', ['id']));
        $fields = [
            new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'kind'),
            new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name'),
            new xmldb_field('teamwork', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'description'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Missions made by teachers.
        $table = new xmldb_table('local_oksigeniatesape_mission');
        $fields = [
            new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'code'),
            new xmldb_field('origin', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'catalog', 'name'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // A teacher's word on a classroom challenge.
        $table = new xmldb_table('local_oksigeniatesape_validation');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('challengeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('validatedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('challengeid', XMLDB_KEY_FOREIGN, ['challengeid'], 'local_oksigeniatesape_challenge', ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('challengeid-charid', XMLDB_INDEX_UNIQUE, ['challengeid', 'charid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101001, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101005) {
        // Missions installed from a package: local examples, version and their data.
        $table = new xmldb_table('local_oksigeniatesape_mission');
        $fields = [
            new xmldb_field('variant', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es', 'skin'),
            new xmldb_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'variant'),
            new xmldb_field('definition', XMLDB_TYPE_TEXT, null, null, null, null, null, 'version'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // What each character has found in a mission.
        $table = new xmldb_table('local_oksigeniatesape_found');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('missionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('missionid', XMLDB_KEY_FOREIGN, ['missionid'], 'local_oksigeniatesape_mission', ['id']);
        $table->add_index('charid-missionid-kind-code', XMLDB_INDEX_UNIQUE, ['charid', 'missionid', 'kind', 'code']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101005, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101010) {
        // The box of ideas of the students.
        $table = new xmldb_table('local_oksigeniatesape_idea');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('anonymous', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'new');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101010, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101104) {
        // The event of the day of the class screen.
        $table = new xmldb_table('local_oksigeniatesape_event');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'drawn');
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid-day', XMLDB_INDEX_UNIQUE, ['courseid', 'day']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101104, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101105) {
        // Live sessions of the class screen: the battles.
        $table = new xmldb_table('local_oksigeniatesape_live');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('kind', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('state', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('round', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('timeend-timemodified', XMLDB_INDEX_NOTUNIQUE, ['timeend', 'timemodified']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_oksigeniatesape_livein');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('liveid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('round', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('team', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('answer', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '-1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('liveid', XMLDB_KEY_FOREIGN, ['liveid'], 'local_oksigeniatesape_live', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_index('liveid-round-charid', XMLDB_INDEX_UNIQUE, ['liveid', 'round', 'charid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101105, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101109) {
        // Plans for the next class made by the AI of the site.
        $table = new xmldb_table('local_oksigeniatesape_nextclass');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101109, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101114) {
        // The time zone of the days and weeks of each course: a school of Asunción on a site in Madrid.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('timezone', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '99', 'nicknames');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101114, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101118) {
        // The design review: the weekly cap belongs to the character in the whole site (a setting of the plugin) and the
        // cap of each course is an option, off; the ranking of the teams is hidden unless the teacher shows it; the cheap
        // powers are approved by themselves.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('weeklycap', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'groupingid');
        $dbman->change_field_default($table, $field);
        $field = new xmldb_field('leaderboard', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'rules');
        $dbman->change_field_default($table, $field);
        $DB->set_field('local_oksigeniatesape_region', 'weeklycap', 0, ['weeklycap' => 100]);
        $DB->set_field('local_oksigeniatesape_region', 'leaderboard', 0);
        $DB->execute("UPDATE {local_oksigeniatesape_power} SET autoapprove = 1 WHERE code <> 'own' AND cost <= 2");

        upgrade_plugin_savepoint(true, 2026101118, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101119) {
        // What comes from the mission is marked in the ledger, so that it neither goes under the weekly cap nor fills it.
        $table = new xmldb_table('local_oksigeniatesape_ledger');
        $field = new xmldb_field('mission', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'capped');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $DB->execute("UPDATE {local_oksigeniatesape_ledger} SET mission = 1 WHERE reason IN ('scene', 'battle')");
        $DB->execute(
            "UPDATE {local_oksigeniatesape_ledger} SET mission = 1
              WHERE reason = 'completion' AND cmid IN (
                    SELECT cmid FROM {local_oksigeniatesape_challenge} WHERE chapterid > 0 AND cmid > 0)"
        );
        $DB->execute(
            "UPDATE {local_oksigeniatesape_ledger} SET mission = 1
              WHERE reason = 'classroom' AND refid IN (
                    SELECT v.id FROM {local_oksigeniatesape_validation} v
                      JOIN {local_oksigeniatesape_challenge} c ON c.id = v.challengeid
                     WHERE c.chapterid > 0)"
        );

        upgrade_plugin_savepoint(true, 2026101119, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101120) {
        // The battle tokens of the roles, which take the place of the power «glance».
        $table = new xmldb_table('local_oksigeniatesape_livetoken');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('liveid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('round', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('team', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('kind', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('liveid', XMLDB_KEY_FOREIGN, ['liveid'], 'local_oksigeniatesape_live', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_index('liveid-charid', XMLDB_INDEX_UNIQUE, ['liveid', 'charid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $glance = $DB->get_fieldset_select('local_oksigeniatesape_power', 'id', "code = 'glance'");
        if ($glance) {
            [$insql, $params] = $DB->get_in_or_equal($glance);
            $DB->delete_records_select('local_oksigeniatesape_powerreq', "powerid $insql", $params);
            $DB->delete_records_select('local_oksigeniatesape_power', "id $insql", $params);
        }
        $DB->delete_records_select('local_oksigeniatesape_livein', 'round < 0');

        upgrade_plugin_savepoint(true, 2026101120, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101122) {
        // The commitments of the teams.
        $table = new xmldb_table('local_oksigeniatesape_pact');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('missionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('chapter', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('text', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'open');
        $table->add_field('decidedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_key('missionid', XMLDB_KEY_FOREIGN, ['missionid'], 'local_oksigeniatesape_mission', ['id']);
        $table->add_index('charid-courseid-missionid-chapter', XMLDB_INDEX_UNIQUE, ['charid', 'courseid', 'missionid', 'chapter']);
        $table->add_index('courseid-status', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101122, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101123) {
        // The page of the day.
        $table = new xmldb_table('local_oksigeniatesape_daily');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('daystart', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('question', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('answer', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, null);
        $table->add_field('success', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('charid-courseid-daystart', XMLDB_INDEX_UNIQUE, ['charid', 'courseid', 'daystart']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101123, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101201) {
        // The market of the valley: what a character wears, and what was bought.
        $table = new xmldb_table('local_oksigeniatesape_char');
        $field = new xmldb_field('cosmetics', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'creaturestage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_oksigeniatesape_purchase');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('charid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('code', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('charid', XMLDB_KEY_FOREIGN, ['charid'], 'local_oksigeniatesape_char', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('charid-code', XMLDB_INDEX_NOTUNIQUE, ['charid', 'code']);
        $table->add_index('courseid-groupid-code', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'groupid', 'code']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101201, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101202) {
        // What a character wears can be empty (the first version of the field did not allow it).
        $table = new xmldb_table('local_oksigeniatesape_char');
        $field = new xmldb_field('cosmetics', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'creaturestage');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
            $dbman->change_field_default($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101202, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101203) {
        // Quests: scenes open to everybody that are not anybody's route.
        $table = new xmldb_table('local_oksigeniatesape_scene');
        $field = new xmldb_field('quest', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'step');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101203, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101205) {
        // The cover of the course page, and the activities of the mission only inside the valley.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('portal', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'door', 'timezone');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('hideacts', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'portal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026101205, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101208) {
        // The competency of Moodle each objective of a mission leaves evidence in.
        $table = new xmldb_table('local_oksigeniatesape_objcomp');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('missionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('objective', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('competencyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('missionid', XMLDB_KEY_FOREIGN, ['missionid'], 'local_oksigeniatesape_mission', ['id']);
        $table->add_key('competencyid', XMLDB_KEY_FOREIGN, ['competencyid'], 'competency', ['id']);
        $table->add_index('courseid-missionid-objective', XMLDB_INDEX_UNIQUE, ['courseid', 'missionid', 'objective']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026101208, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101301) {
        // The ratings of the competencies, to the gradebook if the teacher wants it.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('gradebook', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'hideacts');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101301, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101308) {
        // What a teacher changes in the mission of a course, kept apart as a layer over it.
        $table = new xmldb_table('local_oksigeniatesape_edit');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mission', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('scene', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('path', XMLDB_TYPE_CHAR, '200', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid-mission-scene-path', XMLDB_INDEX_UNIQUE, ['courseid', 'mission', 'scene', 'path']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101308, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101312) {
        // The missions a teacher makes from scratch in a course.
        $table = new xmldb_table('local_oksigeniatesape_own');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('version', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('draft', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('mission', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('scenes', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('code', XMLDB_INDEX_NOTUNIQUE, ['code']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101312, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101321) {
        // The teacher may take the sound away from the devices of the students.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('quiet', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'gradebook');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101321, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101412) {
        // The teacher may choose the theme of the class: the night of the valley or the day (none: the one of the world).
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('look', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'quiet');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101412, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101490) {
        // Who has been away, and who follows the expedition from far (home, hospital).
        $table = new xmldb_table('local_oksigeniatesape_member');
        foreach (
            [
                new xmldb_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lastregen'),
                new xmldb_field('remote', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'lastseen'),
            ] as $field
        ) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026101490, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101491) {
        // What a student needs to play as everybody else: the adaptations the teacher marks, never a diagnosis.
        $table = new xmldb_table('local_oksigeniatesape_member');
        $field = new xmldb_field('adapt', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'remote');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101491, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101492) {
        // Each play comes with a mark made by the device: sent again because the answer was lost, it counts once.
        $table = new xmldb_table('local_oksigeniatesape_sceneattempt');
        $field = new xmldb_field('playid', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'teamplay');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('charid-playid', XMLDB_INDEX_NOTUNIQUE, ['charid', 'playid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026101492, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101493) {
        // The adaptations of comfort a student turns on for themselves, and when a student left the course.
        $table = new xmldb_table('local_oksigeniatesape_member');
        foreach (
            [
                new xmldb_field('adaptself', XMLDB_TYPE_CHAR, '200', null, null, null, null, 'adapt'),
                new xmldb_field('leftat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'adaptself'),
            ] as $field
        ) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026101493, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101498) {
        // Indexes for what is asked again and again: the scenes of a chapter, the entries of a request and the plays
        // of a student in a course.
        foreach (
            [
                'scene' => new xmldb_index('chapterid', XMLDB_INDEX_NOTUNIQUE, ['chapterid']),
                'ledger' => new xmldb_index('refid-reason', XMLDB_INDEX_NOTUNIQUE, ['refid', 'reason']),
                'sceneattempt' => new xmldb_index('charid-courseid', XMLDB_INDEX_NOTUNIQUE, ['charid', 'courseid']),
            ] as $name => $index
        ) {
            $table = new xmldb_table('local_oksigeniatesape_' . $name);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
        upgrade_plugin_savepoint(true, 2026101498, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101705) {
        // A minigame sent again from the queue of the device counts once, as the scenes do.
        $table = new xmldb_table('local_oksigeniatesape_minigame');
        $field = new xmldb_field('playid', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'game');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index('charid-playid', XMLDB_INDEX_NOTUNIQUE, ['charid', 'playid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026101705, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101713) {
        // How the class plays, once for the course: with a tablet each, or without devices (cards and aloud).
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('devices', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'quiet');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101713, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101722) {
        // By turns, the verbs of the roles go to a team and come back after two turns: a target, one use for each round.
        $table = new xmldb_table('local_oksigeniatesape_livetoken');
        $field = new xmldb_field('target', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'kind');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $old = new xmldb_index('liveid-charid', XMLDB_INDEX_UNIQUE, ['liveid', 'charid']);
        if ($dbman->index_exists($table, $old)) {
            $dbman->drop_index($table, $old);
        }
        $new = new xmldb_index('liveid-charid-round', XMLDB_INDEX_UNIQUE, ['liveid', 'charid', 'round']);
        if (!$dbman->index_exists($table, $new)) {
            $dbman->add_index($table, $new);
        }
        upgrade_plugin_savepoint(true, 2026101722, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101724) {
        // By turns, a battle lost leaves the Embrollo as it escaped: the rematch goes on from there.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('battlestate', XMLDB_TYPE_TEXT, null, null, null, null, null, 'look');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101724, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101727) {
        // The music on the devices of the students: never, only in battles (as until now, none) or always.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('devicemusic', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'battlestate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026101727, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101802) {
        // The creature grows in the middle of the mission (0.79.0): whoever already had three lanterns of a mission gets
        // it now, once, as they would have.
        $sql = "SELECT charid, missionid, MAX(courseid) AS courseid
                  FROM {local_oksigeniatesape_found}
                 WHERE kind = 'lantern'
              GROUP BY charid, missionid
                HAVING COUNT(1) >= 3";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $row) {
            $key = ['charid' => $row->charid, 'missionid' => $row->missionid, 'kind' => 'grow', 'code' => 'mid'];
            $stage = (int) $DB->get_field('local_oksigeniatesape_char', 'creaturestage', ['id' => $row->charid]);
            if ($stage < 1 || $stage >= 5 || $DB->record_exists('local_oksigeniatesape_found', $key)) {
                continue;
            }
            $DB->insert_record('local_oksigeniatesape_found', (object) ($key + ['courseid' => $row->courseid,
                'timecreated' => time()]));
            $DB->set_field('local_oksigeniatesape_char', 'creaturestage', $stage + 1, ['id' => $row->charid]);
        }
        $rs->close();
        upgrade_plugin_savepoint(true, 2026101802, 'local', 'oksigeniatesape');
    }

    if ($oldversion < 2026101804) {
        // What the Embrollo does every day: the stains, and how much it does in each course.
        $table = new xmldb_table('local_oksigeniatesape_region');
        $field = new xmldb_field('mischief', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'calm', 'devicemusic');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $table = new xmldb_table('local_oksigeniatesape_stain');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('day', XMLDB_TYPE_INTEGER, '8', null, XMLDB_NOTNULL, null, null);
        $table->add_field('chapter', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('game', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sheet', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cleanedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('erased', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_index('courseid-day', XMLDB_INDEX_UNIQUE, ['courseid', 'day']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026101804, 'local', 'oksigeniatesape');
    }

    return true;
}
