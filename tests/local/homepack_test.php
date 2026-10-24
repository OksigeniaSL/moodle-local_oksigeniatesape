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
 * Tests for the pack for home.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\homepack
 */
#[\PHPUnit\Framework\Attributes\CoversClass(homepack::class)]
final class homepack_test extends \advanced_testcase {
    public function test_the_place_of_now_on_paper_with_its_decisions_and_without_its_endings(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Mateo']);
        $char = characters::create($user->id, 'scout', 'm');
        engine::member($char, $course->id);
        $pack = homepack::pack($course->id, $user->id);
        $this->assertSame('Mateo', $pack['name']);
        $this->assertSame('Claro del Despertar', $pack['place']);
        $this->assertNotEmpty($pack['scenes']);
        $this->assertCount(2, $pack['home']);
        $all = json_encode($pack, JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('¿Qué cinco cosas llevamos al nido?', $all);
        // The story up to the first decision, with the creature named; never how the scene ends.
        $this->assertStringNotContainsString('{{', $all);
        $this->assertStringNotContainsString('aparece la primera página', $all);
        foreach ($pack['scenes'] as $scene) {
            foreach ($scene['decisions'] as $d) {
                $this->assertContains($d['kind'], ['pick', 'sort', 'order']);
                $this->assertNotEmpty($d['items']);
                $this->assertSame($d['kind'] === 'sort', (bool) $d['groups']);
            }
        }
        $this->assertStringStartsWith('%PDF', homepack::pdf($course, (int) $user->id));
    }
}
