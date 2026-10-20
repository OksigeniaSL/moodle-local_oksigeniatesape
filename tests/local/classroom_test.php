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

/**
 * Tests for classroom challenges and the teacher's word on them.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\classroom
 */
#[\PHPUnit\Framework\Attributes\CoversClass(classroom::class)]
final class classroom_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on, a teacher, two students with characters and one without, and a challenge.
     *
     * @return \stdClass
     */
    private function make_class(): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->teacher = $dg->create_user();
        $w->ana = $dg->create_user();
        $w->leo = $dg->create_user();
        $w->noa = $dg->create_user();
        $w->anachar = characters::create($w->ana->id, 'healer', 'f');
        $w->leochar = characters::create($w->leo->id, 'scout', 'm');
        $w->challenge = classroom::save($w->course->id, [
            'name' => 'Draw the life cycle of a frog',
            'description' => 'On paper, with the stages in order.',
            'saber' => 20,
            'gems' => 2,
            'steps' => '2,3',
        ]);
        return $w;
    }

    /**
     * Saber and gems of a user's character.
     *
     * @param int $userid
     * @return array [saber, gems]
     */
    private function totals(int $userid): array {
        $char = characters::get($userid);
        return [(int) $char->saber, (int) $char->gems];
    }

    public function test_save_and_list(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->assertEquals(0, $w->challenge->cmid);
        $this->assertEquals('classroom', $w->challenge->kind);
        $this->assertEquals('2,3', $w->challenge->steps);
        $changed = classroom::save($w->course->id, ['id' => $w->challenge->id, 'steps' => '9,1,1', 'teamwork' => 1]);
        $this->assertEquals('1', $changed->steps);
        $this->assertEquals(1, $changed->teamwork);
        $this->assertEquals('Draw the life cycle of a frog', $changed->name);
        $this->assertCount(1, classroom::list($w->course->id));
        // A name is needed, and a challenge of another course cannot be changed from here.
        try {
            classroom::save($w->course->id, ['name' => '  ']);
            $this->fail('Saved without a name');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('name', $e->getMessage());
        }
        $other = $this->getDataGenerator()->create_course();
        $this->expectException(\invalid_parameter_exception::class);
        classroom::save($other->id, ['id' => $w->challenge->id, 'name' => 'Mine']);
    }

    public function test_validate_gives_once(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $userids = [$w->ana->id, $w->leo->id, $w->noa->id];
        $results = classroom::validate($w->challenge->id, $userids, 'done', 'Well done', $w->teacher->id);
        $this->assertEquals([$w->ana->id => 'done', $w->leo->id => 'done', $w->noa->id => 'nocharacter'], $results);
        $this->assertEquals([20, 2], $this->totals($w->ana->id));
        // Saying it again does not give it again.
        classroom::validate($w->challenge->id, [$w->ana->id], 'done', null, $w->teacher->id);
        $this->assertEquals([20, 2], $this->totals($w->ana->id));
        $validation = classroom::validations($w->challenge->id)[$w->anachar->id];
        $this->assertEquals('Well done', $validation->note);
        $this->assertEquals($w->teacher->id, $validation->validatedby);
    }

    public function test_mention_and_back(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        classroom::validate($w->challenge->id, [$w->ana->id], 'merit', '', $w->teacher->id);
        $this->assertEquals([20, 2 + classroom::MERIT_GEMS], $this->totals($w->ana->id));
        $this->assertNull(classroom::validations($w->challenge->id)[$w->anachar->id]->note);
        // From mention to done: the mention goes, the rest stays.
        classroom::validate($w->challenge->id, [$w->ana->id], 'done', null, $w->teacher->id);
        $this->assertEquals([20, 2], $this->totals($w->ana->id));
        // From done to mention, and then to not yet: everything goes back.
        classroom::validate($w->challenge->id, [$w->ana->id], 'merit', null, $w->teacher->id);
        classroom::validate($w->challenge->id, [$w->ana->id], 'notyet', 'Missing the tadpole', $w->teacher->id);
        $this->assertEquals([0, 0], $this->totals($w->ana->id));
        // And done again gives it again.
        classroom::validate($w->challenge->id, [$w->ana->id], 'done', null, $w->teacher->id);
        $this->assertEquals([20, 2], $this->totals($w->ana->id));
    }

    public function test_items_found_stay(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $item = $this->getDataGenerator()->get_plugin_generator('local_oksigeniatesape')->create_item(['code' => 'magnifier']);
        classroom::save($w->course->id, ['id' => $w->challenge->id, 'itemid' => $item->id]);
        classroom::validate($w->challenge->id, [$w->leo->id], 'done', null, $w->teacher->id);
        classroom::validate($w->challenge->id, [$w->leo->id], 'notyet', null, $w->teacher->id);
        $this->assertTrue($DB->record_exists(self::T . 'inventory', ['charid' => $w->leochar->id, 'itemid' => $item->id]));
        $this->assertEquals([0, 0], $this->totals($w->leo->id));
    }

    public function test_delete_keeps_the_log(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        classroom::validate($w->challenge->id, [$w->ana->id], 'done', null, $w->teacher->id);
        classroom::delete($w->challenge->id);
        $this->assertEmpty(classroom::list($w->course->id));
        $this->assertFalse($DB->record_exists(self::T . 'validation', ['challengeid' => $w->challenge->id]));
        $this->assertEquals([20, 2], $this->totals($w->ana->id));
        $this->assertEquals(2, $DB->count_records(self::T . 'ledger', ['charid' => $w->anachar->id, 'reason' => 'classroom']));
    }

    public function test_unknown_status(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->expectException(\invalid_parameter_exception::class);
        classroom::validate($w->challenge->id, [$w->ana->id], 'great', null, $w->teacher->id);
    }

    public function test_a_team_task_with_parts_marked_by_each_one(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $dg = $this->getDataGenerator();
        $group = $dg->create_group(['courseid' => $w->course->id]);
        foreach ([$w->ana, $w->leo, $w->noa] as $user) {
            $dg->enrol_user($user->id, $w->course->id, 'student');
            $dg->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        characters::create($w->noa->id, 'sage', 'f');
        // The parts: up to four, each with a role or none; with parts, it is for the team.
        $task = classroom::save($w->course->id, ['id' => $w->challenge->id, 'parts' => [
            ['role' => 'scout', 'text' => 'Choose the place and lead'],
            ['role' => 'sage', 'text' => 'Write the two lists'],
            ['role' => 'robot', 'text' => 'Check the material'],
            ['role' => 'healer', 'text' => ''],
            ['role' => 'healer', 'text' => 'Draw it'], ['role' => 'healer', 'text' => 'One too many'],
        ]]);
        $parts = classroom::parts($task);
        $this->assertCount(4, $parts);
        $this->assertSame('', $parts[2]['role']);
        $this->assertEquals(1, $task->teamwork);
        // Leo marks his part; Ana covers the one of the sage, missing today.
        classroom::mark_part($task->id, $w->leo->id, 0, true);
        classroom::mark_part($task->id, $w->ana->id, 1, true);
        $seen = classroom::team_parts($task, (int) $group->id, $w->ana->id);
        $this->assertSame([true, true, false, false], array_column($seen, 'done'));
        $this->assertSame([false, true, false, false], array_column($seen, 'mine'));
        $this->assertNotSame('', $seen[0]['by']);
        // Only who marked it takes it back; marking it again does nothing.
        try {
            classroom::mark_part($task->id, $w->noa->id, 1, false);
            $this->fail('Taken back by someone else');
        } catch (\moodle_exception $e) {
            $this->assertSame('partnotyours', $e->errorcode);
        }
        classroom::mark_part($task->id, $w->noa->id, 1, true);
        $this->assertEquals(1, $DB->count_records(self::T . 'challengepart', ['challengeid' => $task->id, 'part' => 1]));
        classroom::mark_part($task->id, $w->ana->id, 1, false);
        $this->assertSame([(int) $group->id => [0]], classroom::parts_done($task));
        // A part that does not exist, or someone without a team.
        $this->expectException(\moodle_exception::class);
        classroom::mark_part($task->id, $w->leo->id, 7, true);
    }

    public function test_the_mission_brings_the_parts_of_the_task_of_each_place(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => 'claro']);
        $task = $DB->get_record(self::T . 'challenge', ['courseid' => $course->id, 'chapterid' => $chapter->id]);
        $this->assertSame(['scout', 'sage', 'protector', 'healer'], array_column(classroom::parts($task), 'role'));
        $this->assertEquals(1, $task->teamwork);
        // A course whose task had no parts gets them when the mission comes again; one with its own keeps them.
        $DB->set_field(self::T . 'challenge', 'parts', null, ['id' => $task->id]);
        $chapter2 = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => 'bosque']);
        $own = $DB->get_record(self::T . 'challenge', ['courseid' => $course->id, 'chapterid' => $chapter2->id]);
        classroom::save($course->id, ['id' => $own->id, 'parts' => [['role' => 'sage', 'text' => 'Our own part']]]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $this->assertCount(4, classroom::parts(classroom::get((int) $task->id)));
        $this->assertSame('Our own part', classroom::parts(classroom::get((int) $own->id))[0]['text']);
    }
}
