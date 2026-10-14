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
 * Tests for the student who leaves a course: marked when they leave, back if they return, forgotten some time after.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\leaving
 */
#[\PHPUnit\Framework\Attributes\CoversClass(leaving::class)]
final class leaving_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_who_leaves_is_marked_comes_back_whole_and_is_forgotten_later(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $user = $dg->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'm');
        $member = engine::member($char, $course->id);
        engine::saber($member, 10, 'manual', 2);
        $manual = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        // Out of the course: marked, nothing deleted yet.
        $manual->unenrol_user($instance, $user->id);
        $left = (int) $DB->get_field(self::T . 'member', 'leftat', ['id' => $member->id]);
        $this->assertGreaterThan(0, $left);
        $this->assertEquals(0, leaving::forget());
        // Back by mistake: all theirs again.
        $manual->enrol_user($instance, $user->id, $DB->get_field('role', 'id', ['shortname' => 'student']));
        $this->assertEquals(0, (int) $DB->get_field(self::T . 'member', 'leftat', ['id' => $member->id]));
        // Out again, and long ago: what they did in the course goes; their character stays.
        $manual->unenrol_user($instance, $user->id);
        $this->assertEquals(1, leaving::forget(time() + (leaving::KEEP_DAYS + 1) * DAYSECS));
        $this->assertFalse($DB->record_exists(self::T . 'member', ['id' => $member->id]));
        $this->assertFalse($DB->record_exists(self::T . 'ledger', ['charid' => $char->id, 'courseid' => $course->id]));
        $this->assertTrue($DB->record_exists(self::T . 'char', ['id' => $char->id]));
    }
}
