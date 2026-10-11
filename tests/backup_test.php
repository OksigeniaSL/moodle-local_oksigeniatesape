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

namespace local_oksigeniatesape;

use local_oksigeniatesape\local\classroom;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\powers;
use local_oksigeniatesape\local\regions;

/**
 * Tests for the game in the backup and restore of a course.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_local_oksigeniatesape_plugin
 * @covers     \restore_local_oksigeniatesape_plugin
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\backup_local_oksigeniatesape_plugin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_local_oksigeniatesape_plugin::class)]
final class backup_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_game_of_a_course_comes_back_with_its_backup(): void {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->keeptempdirectoriesonbackup = true;
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $page = $dg->create_module('page', ['course' => $course->id]);
        regions::save($course->id, ['enabled' => 1, 'family' => 'animals', 'weeklycap' => 150]);
        missions::install($course->id, 'livingthings', 'azara', 'py');
        classroom::save($course->id, ['name' => 'Un reto propio', 'saber' => 7]);
        $now = time();
        $record = ['courseid' => $course->id, 'cmid' => $page->cmid, 'kind' => 'activity', 'saber' => 12,
            'timecreated' => $now, 'timemodified' => $now];
        $DB->insert_record(self::T . 'challenge', (object) $record);
        foreach (powers::catalog($course->id) as $power) {
            if ($power->code === 'shield') {
                $DB->set_field(self::T . 'power', 'cost', 1, ['id' => $power->id]);
            }
        }
        $before = $DB->count_records(self::T . 'challenge', ['courseid' => $course->id]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $newid = \restore_dbops::create_new_course('Copia', 'COPIA', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $region = regions::get($newid);
        $this->assertEquals(1, $region->enabled);
        $this->assertEquals('animals', $region->family);
        $this->assertEquals(150, $region->weeklycap);
        $mission = missions::get($newid);
        $this->assertEquals('livingthings', $mission->code);
        $this->assertEquals('azara', $mission->skin);
        $this->assertEquals('py', $mission->variant);
        // The challenges of the chapters are not made twice, and the one of the activity follows its new activity.
        $this->assertEquals($before, $DB->count_records(self::T . 'challenge', ['courseid' => $newid]));
        $this->assertTrue($DB->record_exists(self::T . 'challenge', ['courseid' => $newid, 'name' => 'Un reto propio',
            'saber' => 7]));
        $newcm = $DB->get_field_select(self::T . 'challenge', 'cmid', 'courseid = ? AND cmid > 0', [$newid]);
        $this->assertEquals($newid, $DB->get_field('course_modules', 'course', ['id' => $newcm]));
        $this->assertEquals(1, $DB->get_field(self::T . 'power', 'cost', ['courseid' => $newid, 'code' => 'shield']));
        $this->assertCount(1, $DB->get_records(self::T . 'power', ['courseid' => $newid, 'code' => 'shield']));
    }
}
