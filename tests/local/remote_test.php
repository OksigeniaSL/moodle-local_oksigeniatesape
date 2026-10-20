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
 * Tests for the remote of a scene played by the class: the class screen says its buttons, the phone presses one.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\remote
 */
#[\PHPUnit\Framework\Attributes\CoversClass(remote::class)]
final class remote_test extends \advanced_testcase {
    public function test_the_phone_presses_what_the_class_screen_shows(): void {
        $this->resetAfterTest();
        $courseid = 42;
        $now = time();
        $this->assertNull(remote::read($courseid, $now));
        // The class screen says its buttons; what it says is cleaned, and no more than a few.
        $buttons = [['key' => 'b0-1', 'label' => 'A. Al estante de los hongos'], ['key' => 'b1-2', 'label' => '<b>Seguir</b>'],
            ['key' => '', 'label' => 'Sin clave']];
        $state = remote::publish($courseid, 'Los estantes que crujen', '¿Dónde va la seta?', $buttons, $now);
        $this->assertSame(['b0-1', 'b1-2'], array_column($state['buttons'], 'key'));
        $this->assertSame('Seguir', $state['buttons'][1]['label']);
        $this->assertSame('Los estantes que crujen', remote::read($courseid, $now + 1)['title']);
        // The phone presses one: the class screen gets it, numbered, once.
        $this->assertSame(['seq' => 1, 'key' => 'b0-1'], remote::press($courseid, 'b0-1', $now + 2)['press']);
        $this->assertSame(['seq' => 1, 'key' => 'b0-1'], remote::alive($courseid, $now + 3)['press']);
        // A new step keeps what was pressed, so that the class screen does not do it again.
        remote::publish($courseid, 'Los estantes que crujen', '', [['key' => 'b0-9', 'label' => 'Seguir']], $now + 4);
        $this->assertSame(1, remote::read($courseid, $now + 4)['press']['seq']);
        try {
            remote::press($courseid, 'b0-1', $now + 5);
            $this->fail('A button the class screen does not show any more is pressed');
        } catch (\moodle_exception $e) {
            $this->assertSame('noremote', $e->errorcode);
        }
        // Without news from the class screen for a while, the phone sees nothing; while it lives, it stays.
        $this->assertNull(remote::read($courseid, $now + 4 + remote::FRESH + 1));
        remote::alive($courseid, $now + 4 + remote::FRESH);
        $this->assertNotNull(remote::read($courseid, $now + 4 + remote::FRESH + 1));
        // The scene closes: the remote goes.
        remote::clear($courseid);
        $this->assertNull(remote::read($courseid, $now + 40));
    }
}
