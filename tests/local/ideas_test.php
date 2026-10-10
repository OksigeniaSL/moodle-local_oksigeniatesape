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
 * Tests for the box of ideas.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\ideas
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ideas::class)]
final class ideas_test extends \advanced_testcase {
    public function test_send_read_and_delete(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $other = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $ana = $dg->create_and_enrol($course, 'student', ['firstname' => 'Ana', 'lastname' => 'Sol']);
        $leo = $dg->create_and_enrol($course, 'student', ['firstname' => 'Leo', 'lastname' => 'Mar']);
        characters::create($ana->id, 'sage', 'f');
        characters::create($leo->id, 'healer', 'm');

        ideas::send($course->id, $ana->id, '  A map of our school  ', false, 100);
        ideas::send($course->id, $leo->id, 'More scenes with dinosaurs', true, 200);
        $list = ideas::list($course->id);
        $this->assertCount(2, $list);
        $this->assertSame('More scenes with dinosaurs', $list[0]['message']);
        $this->assertSame('', $list[0]['fullname']);
        $this->assertSame(0, $list[0]['userid']);
        $this->assertSame('Ana Sol', $list[1]['fullname']);
        $this->assertSame('A map of our school', $list[1]['message']);
        $this->assertSame(2, ideas::unread($course->id));
        $this->assertSame([], ideas::list($other->id));

        // Leo sees his own, with the teacher's reading.
        ideas::handle($course->id, $list[0]['id'], 'read');
        $mine = ideas::mine($course->id, (int) characters::get($leo->id)->id);
        $this->assertSame('read', $mine[0]['status']);
        $this->assertTrue($mine[0]['anonymous']);
        $this->assertSame(1, ideas::unread($course->id));

        ideas::handle($course->id, $list[1]['id'], 'delete');
        $this->assertCount(1, ideas::list($course->id));
        $this->expectException(\dml_missing_record_exception::class);
        ideas::handle($other->id, $list[0]['id'], 'read');
    }

    public function test_limits(): void {
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $ana = $dg->create_and_enrol($course, 'student');
        characters::create($ana->id, 'sage', 'f');
        $now = time();
        for ($i = 0; $i < ideas::DAILY; $i++) {
            ideas::send($course->id, $ana->id, str_repeat('a', ideas::MAXLENGTH + 50), false, $now);
        }
        $this->assertSame(ideas::MAXLENGTH, \core_text::strlen(ideas::list($course->id)[0]['message']));
        try {
            ideas::send($course->id, $ana->id, 'One more', false, $now);
            $this->fail('Too many in a day');
        } catch (\moodle_exception $e) {
            $this->assertSame('ideastoday', $e->errorcode);
        }
        // Tomorrow, again.
        ideas::send($course->id, $ana->id, 'One more', false, $now + DAYSECS);
        $this->expectException(\invalid_parameter_exception::class);
        ideas::send($course->id, $ana->id, '   ', false, $now + DAYSECS);
    }
}
