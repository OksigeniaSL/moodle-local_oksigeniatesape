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
}
