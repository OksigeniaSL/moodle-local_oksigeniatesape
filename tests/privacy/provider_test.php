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

namespace local_oksigeniatesape\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\privacy\provider
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Two courses: Ana plays in both, Leo in the first; a teacher paused Leo, gave Ana saber and approved her power.
     *
     * @return \stdClass Courses, users, characters and members.
     */
    private function make_world(): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        /** @var \local_oksigeniatesape_generator $gen */
        $gen = $dg->get_plugin_generator('local_oksigeniatesape');
        $w = new \stdClass();
        $w->course1 = $dg->create_course();
        $w->course2 = $dg->create_course();
        $w->ana = $dg->create_user(['firstname' => 'Ana']);
        $w->leo = $dg->create_user(['firstname' => 'Leo']);
        $w->teacher = $dg->create_user();
        $w->anachar = $gen->create_char(['userid' => $w->ana->id, 'role' => 'healer', 'nameform' => 'f', 'saber' => 40]);
        $w->leochar = $gen->create_char(['userid' => $w->leo->id]);
        $w->ana1 = $gen->create_member(['charid' => $w->anachar->id, 'courseid' => $w->course1->id, 'renown' => 30]);
        $w->ana2 = $gen->create_member(['charid' => $w->anachar->id, 'courseid' => $w->course2->id]);
        $w->leo1 = $gen->create_member([
            'charid' => $w->leochar->id,
            'courseid' => $w->course1->id,
            'status' => 'paused',
            'statusreason' => 'Needs a break',
            'statusby' => $w->teacher->id,
        ]);
        $gen->create_ledger(['charid' => $w->anachar->id, 'courseid' => $w->course1->id, 'actorid' => $w->teacher->id]);
        $gen->create_ledger(['charid' => $w->anachar->id, 'courseid' => $w->course2->id, 'amount' => 20]);
        $gen->create_ledger(['charid' => $w->anachar->id, 'courseid' => 0, 'resource' => 'gems', 'amount' => -5]);
        $gen->create_ledger(['charid' => $w->leochar->id, 'courseid' => $w->course1->id]);
        $power = $gen->create_power(['courseid' => $w->course1->id, 'role' => 'healer', 'code' => 'breather']);
        $gen->create_powerreq([
            'powerid' => $power->id,
            'memberid' => $w->ana1->id,
            'targetmemberid' => $w->leo1->id,
            'status' => 'approved',
            'decidedby' => $w->teacher->id,
            'timedecided' => time(),
        ]);
        $item = $gen->create_item(['code' => 'magnifier', 'name' => 'Magnifier']);
        $gen->create_inventory(['charid' => $w->anachar->id, 'itemid' => $item->id, 'courseid' => $w->course1->id]);
        $mission = $gen->create_mission(['courseid' => $w->course1->id]);
        $scene = $gen->create_scene(['missionid' => $mission->id]);
        $gen->create_sceneattempt(['sceneid' => $scene->id, 'charid' => $w->anachar->id, 'courseid' => $w->course1->id]);
        $gen->create_sceneattempt(['sceneid' => $scene->id, 'charid' => $w->leochar->id, 'courseid' => $w->course1->id]);
        $gen->create_minigame(['charid' => $w->anachar->id, 'courseid' => $w->course1->id]);
        $gen->create_minigame(['charid' => $w->anachar->id, 'courseid' => $w->course2->id, 'success' => 0, 'tries' => 3]);
        // The teacher says Leo's drawing is done.
        $challenge = \local_oksigeniatesape\local\classroom::save($w->course1->id, ['name' => 'Draw a food chain']);
        $DB->insert_record(self::T . 'validation', (object) [
            'challengeid' => $challenge->id,
            'charid' => $w->leochar->id,
            'courseid' => $w->course1->id,
            'status' => 'done',
            'note' => 'Lovely colours',
            'validatedby' => $w->teacher->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $w->ctx1 = \context_course::instance($w->course1->id);
        $w->ctx2 = \context_course::instance($w->course2->id);
        $w->anactx = \context_user::instance($w->ana->id);
        return $w;
    }

    /**
     * Rows of a character in a course, in every course table.
     *
     * @param int $charid
     * @param int $courseid
     * @return int
     */
    private function rows(int $charid, int $courseid): int {
        global $DB;
        $count = 0;
        foreach (provider::COURSE_TABLES as $table) {
            $count += $DB->count_records(self::T . $table, ['charid' => $charid, 'courseid' => $courseid]);
        }
        return $count;
    }

    public function test_metadata(): void {
        $collection = provider::get_metadata(new collection('local_oksigeniatesape'));
        $tables = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $expected = ['char', 'member', 'ledger', 'powerreq', 'inventory', 'sceneattempt', 'minigame', 'validation', 'found'];
        foreach ($expected as $table) {
            $this->assertContains(self::T . $table, $tables);
        }
    }

    public function test_contexts_for_userid(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $expected = [$w->anactx->id, $w->ctx1->id, $w->ctx2->id];
        $actual = provider::get_contexts_for_userid($w->ana->id)->get_contextids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);
        // The teacher has no character, only what they did in the first course.
        $this->assertEquals([$w->ctx1->id], provider::get_contexts_for_userid($w->teacher->id)->get_contextids());
        $this->assertEmpty(provider::get_contexts_for_userid($this->getDataGenerator()->create_user()->id)->get_contextids());
    }

    public function test_users_in_context(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $userlist = new userlist($w->ctx1, 'local_oksigeniatesape');
        provider::get_users_in_context($userlist);
        $expected = [$w->ana->id, $w->leo->id, $w->teacher->id];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);

        $userlist = new userlist($w->ctx2, 'local_oksigeniatesape');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$w->ana->id], $userlist->get_userids());

        $userlist = new userlist($w->anactx, 'local_oksigeniatesape');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$w->ana->id], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $w = $this->make_world();
        $this->export_context_data_for_user($w->ana->id, $w->anactx, 'local_oksigeniatesape');
        $base = [get_string('pluginname', 'local_oksigeniatesape')];
        $writer = writer::with_context($w->anactx);
        $this->assertTrue($writer->has_any_data());
        $char = $writer->get_data($base);
        $this->assertEquals('healer', $char->role);
        $this->assertEquals(40, $char->saber);
        $ledger = $writer->get_data(array_merge($base, [get_string('privacy:path:ledger', 'local_oksigeniatesape')]));
        $this->assertCount(1, $ledger->entries);
        $this->assertEquals('gems', $ledger->entries[0]['resource']);

        $this->export_context_data_for_user($w->ana->id, $w->ctx1, 'local_oksigeniatesape');
        $writer = writer::with_context($w->ctx1);
        $this->assertEquals(30, $writer->get_data($base)->renown);
        $path = fn($key) => array_merge($base, [get_string('privacy:path:' . $key, 'local_oksigeniatesape')]);
        $this->assertCount(1, $writer->get_data($path('ledger'))->entries);
        $this->assertEquals('magnifier', $writer->get_data($path('items'))->items[0]['code']);
        $this->assertEquals('C1-2', $writer->get_data($path('scenes'))->attempts[0]['scene']);
        $this->assertEquals('nestdefence', $writer->get_data($path('minigames'))->results[0]['game']);
        $this->assertEquals('approved', $writer->get_data($path('powers'))->requests[0]['status']);

        // Leo sees why he was paused, and what the teacher said of his drawing.
        $this->export_context_data_for_user($w->leo->id, $w->ctx1, 'local_oksigeniatesape');
        $this->assertEquals('Needs a break', writer::with_context($w->ctx1)->get_data($base)->statusreason);
        $classroom = writer::with_context($w->ctx1)->get_data($path('classroom'));
        $this->assertEquals('Lovely colours', $classroom->challenges[0]['note']);
    }

    public function test_delete_data_for_user_in_a_course(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        provider::delete_data_for_user(new approved_contextlist($w->ana, 'local_oksigeniatesape', [$w->ctx1->id]));
        $this->assertSame(0, $this->rows($w->anachar->id, $w->course1->id));
        $this->assertSame(0, $DB->count_records(self::T . 'powerreq'));
        // Her other course, her character and Leo stay.
        $this->assertSame(3, $this->rows($w->anachar->id, $w->course2->id));
        $this->assertTrue($DB->record_exists(self::T . 'char', ['id' => $w->anachar->id]));
        $this->assertSame(4, $this->rows($w->leochar->id, $w->course1->id));
    }

    public function test_delete_data_for_a_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        provider::delete_data_for_user(new approved_contextlist($w->teacher, 'local_oksigeniatesape', [$w->ctx1->id]));
        // The students' records stay, without the teacher.
        $this->assertSame(0, (int) $DB->get_field(self::T . 'member', 'statusby', ['id' => $w->leo1->id]));
        $this->assertFalse($DB->record_exists(self::T . 'ledger', ['actorid' => $w->teacher->id]));
        $this->assertFalse($DB->record_exists(self::T . 'powerreq', ['decidedby' => $w->teacher->id]));
        $this->assertFalse($DB->record_exists(self::T . 'validation', ['validatedby' => $w->teacher->id]));
        $this->assertSame(1, $DB->count_records(self::T . 'powerreq'));
        $this->assertSame(5, $this->rows($w->anachar->id, $w->course1->id));
    }

    public function test_delete_the_character(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        provider::delete_data_for_user(new approved_contextlist($w->ana, 'local_oksigeniatesape', [$w->anactx->id]));
        $this->assertFalse($DB->record_exists(self::T . 'char', ['id' => $w->anachar->id]));
        foreach (provider::COURSE_TABLES as $table) {
            $this->assertFalse($DB->record_exists(self::T . $table, ['charid' => $w->anachar->id]), $table);
        }
        // Her power request goes with her; Leo's data stays.
        $this->assertSame(0, $DB->count_records(self::T . 'powerreq'));
        $this->assertSame(4, $this->rows($w->leochar->id, $w->course1->id));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        provider::delete_data_for_all_users_in_context($w->ctx1);
        foreach (provider::COURSE_TABLES as $table) {
            $this->assertSame(0, $DB->count_records(self::T . $table, ['courseid' => $w->course1->id]), $table);
        }
        $this->assertSame(0, $DB->count_records(self::T . 'powerreq'));
        $this->assertSame(3, $this->rows($w->anachar->id, $w->course2->id));
        $this->assertSame(2, $DB->count_records(self::T . 'char'));

        provider::delete_data_for_all_users_in_context($w->anactx);
        $this->assertSame(1, $DB->count_records(self::T . 'char'));
        $this->assertSame(0, $DB->count_records(self::T . 'member', ['charid' => $w->anachar->id]));
    }

    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_world();
        $userlist = new approved_userlist($w->ctx1, 'local_oksigeniatesape', [$w->leo->id, $w->teacher->id]);
        provider::delete_data_for_users($userlist);
        $this->assertSame(0, $this->rows($w->leochar->id, $w->course1->id));
        $this->assertSame(5, $this->rows($w->anachar->id, $w->course1->id));
        // Ana's power request stays, without its target and without the teacher.
        $request = $DB->get_record(self::T . 'powerreq', ['memberid' => $w->ana1->id]);
        $this->assertSame(0, (int) $request->targetmemberid);
        $this->assertSame(0, (int) $request->decidedby);
        $this->assertSame(0, $DB->count_records(self::T . 'ledger', ['actorid' => $w->teacher->id]));
    }
}
