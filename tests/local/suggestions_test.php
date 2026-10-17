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
 * Tests for the suggestions to Oksigenia: off by default, a few an hour for each person, and nothing without text.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\suggestions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(suggestions::class)]
final class suggestions_test extends \advanced_testcase {
    public function test_off_by_default(): void {
        $this->resetAfterTest();
        $this->assertFalse(suggestions::enabled());
        $this->expectException(\moodle_exception::class);
        suggestions::send((int) $this->getDataGenerator()->create_user()->id, 'Una idea');
    }

    public function test_a_few_an_hour_and_a_right_email(): void {
        $this->resetAfterTest();
        set_config('suggestions', 1, 'local_oksigeniatesape');
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        for ($i = 0; $i < suggestions::PER_HOUR; $i++) {
            // The receiver answers; nothing really goes out in a test.
            \curl::mock_response('');
            suggestions::send((int) $user->id, 'Una idea ' . $i, $i ? '' : 'docente@example.com', $now);
        }
        try {
            suggestions::send((int) $user->id, 'Una más', '', $now + 60);
            $this->fail('More suggestions than allowed in an hour');
        } catch (\moodle_exception $e) {
            $this->assertSame('suggestbusy', $e->errorcode);
        }
        // An hour later, again.
        \curl::mock_response('');
        suggestions::send((int) $user->id, 'Otra hora', '', $now + HOURSECS + 1);
        try {
            suggestions::send((int) $this->getDataGenerator()->create_user()->id, 'Con correo', 'no es un correo', $now);
            $this->fail('A wrong email went out');
        } catch (\moodle_exception $e) {
            $this->assertSame('suggestemail', $e->errorcode);
        }
        $this->expectException(\invalid_parameter_exception::class);
        suggestions::send((int) $user->id, '<b></b>   ', '', $now + 2 * HOURSECS);
    }
}
