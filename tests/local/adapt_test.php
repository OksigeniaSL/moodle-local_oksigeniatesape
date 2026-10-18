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

use core_external\external_api;
use local_oksigeniatesape\external\set_adapt;

/**
 * Tests for the adaptations of a student: only those that exist, seen by the student and not by their classmates.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\adapt
 */
#[\PHPUnit\Framework\Attributes\CoversClass(adapt::class)]
final class adapt_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_adaptations_of_a_student_are_theirs(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $team = $dg->create_group(['courseid' => $course->id]);
        $users = [];
        foreach (['sage', 'scout'] as $role) {
            $user = $dg->create_and_enrol($course, 'student');
            characters::create($user->id, $role, 'f');
            engine::member(characters::get($user->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $user->id]);
            $users[] = $user;
        }
        $member = engine::member(characters::get($users[0]->id), $course->id);
        // Only those that exist, in their order, once.
        $this->assertSame(['bigtext', 'readaloud', 'hints'], adapt::save($member, ['hints', 'readaloud', 'bigtext', 'x',
            'hints']));
        $member = engine::member(characters::get($users[0]->id), $course->id);
        $this->assertTrue(adapt::has($member, 'readaloud'));
        $this->assertFalse(adapt::has($member, 'quiet'));
        // The student sees them; their teammate does not.
        $mine = view::world($course->id, $users[0]->id);
        $this->assertSame(['bigtext', 'readaloud', 'hints'], $mine['character']['member']['adapt']);
        $theirs = view::world($course->id, $users[1]->id);
        foreach ($theirs['team']['members'] as $mate) {
            if (!$mate['me'] && isset($mate['character']['member'])) {
                $this->assertSame([], $mate['character']['member']['adapt']);
            }
        }
        // None: nothing kept.
        $this->assertSame([], adapt::save($member, []));
        $this->assertSame([], adapt::of(engine::member(characters::get($users[0]->id), $course->id)));
    }

    public function test_a_profile_of_one_touch_adds_to_what_each_student_has(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $teacher = $dg->create_and_enrol($course, 'editingteacher');
        $users = [];
        foreach (['sage', 'scout'] as $role) {
            $user = $dg->create_and_enrol($course, 'student');
            characters::create($user->id, $role, 'f');
            engine::member(characters::get($user->id), $course->id);
            $users[] = $user;
        }
        $nochar = $dg->create_and_enrol($course, 'student');
        adapt::save(engine::member(characters::get($users[0]->id), $course->id), ['colours']);
        $this->setUser($teacher);
        $result = external_api::clean_returnvalue(
            set_adapt::execute_returns(),
            set_adapt::execute($course->id, 0, [], [$users[0]->id, $users[1]->id, $nochar->id], 'reading')
        );
        // What it marks is added to what was marked; who has no character yet is left out.
        $this->assertSame([
            ['userid' => (int) $users[0]->id, 'adapt' => ['bigtext', 'readaloud', 'colours', 'hints']],
            ['userid' => (int) $users[1]->id, 'adapt' => ['bigtext', 'readaloud', 'hints']],
        ], $result['students']);
        // One student, from their sheet.
        $result = external_api::clean_returnvalue(
            set_adapt::execute_returns(),
            set_adapt::execute($course->id, $users[1]->id, [], [], 'calm')
        );
        $this->assertSame(['bigtext', 'readaloud', 'notime', 'still', 'quiet', 'hints'], $result['adapt']);
        $this->expectException(\invalid_parameter_exception::class);
        set_adapt::execute($course->id, $users[1]->id, [], [], 'magic');
    }

    public function test_the_student_turns_on_those_of_comfort_and_the_team_sees_only_what_it_may(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $team = $dg->create_group(['courseid' => $course->id]);
        $users = [];
        foreach (['sage', 'scout'] as $role) {
            $user = $dg->create_and_enrol($course, 'student');
            characters::create($user->id, $role, 'f');
            engine::member(characters::get($user->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $user->id]);
            $users[] = $user;
        }
        $member = engine::member(characters::get($users[0]->id), $course->id);
        adapt::save($member, ['notime']);
        // Only those of comfort: no hurry and more help are for the teacher to decide.
        $this->assertSame(['bigtext'], adapt::save_own($member, ['hints', 'bigtext', 'notime']));
        $member = engine::member(characters::get($users[0]->id), $course->id);
        $this->assertSame(['bigtext', 'notime'], adapt::of($member));
        $mine = view::world($course->id, $users[0]->id)['character']['member'];
        $this->assertSame(['notime'], $mine['adaptteacher']);
        $this->assertSame(['bigtext'], $mine['adaptown']);
        // Paused and from far: their teammate sees that they are not playing, not why, and not that they are far.
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'status' => 'paused', 'remote' => 1,
            'statusreason' => 'En el hospital']);
        $seen = function () use ($course, $users): array {
            foreach (view::world($course->id, $users[1]->id)['team']['members'] as $mate) {
                if (!$mate['me']) {
                    return $mate['character']['member'];
                }
            }
            return [];
        };
        $this->assertSame('away', $seen()['status']);
        $this->assertSame('', $seen()['statusreason']);
        $this->assertFalse($seen()['remote']);
        // The teacher lets the team see that they follow from far.
        $DB->set_field(self::T . 'member', 'remote', 2, ['id' => $member->id]);
        $this->assertTrue($seen()['remote']);
        $this->assertFalse($seen()['remoteshown']);
    }
}
