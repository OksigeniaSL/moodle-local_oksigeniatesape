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
 * Tests for the shop of the naturalist: a curiosity bought with gems and, read with its questions right, the gems back
 * and some more, a seal and a golden card, once.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\curiosities
 */
#[\PHPUnit\Framework\Attributes\CoversClass(curiosities::class)]
final class curiosities_test extends \advanced_testcase {
    public function test_curiosity_does_not_come_dear(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $c);
        engine::gems($member, 10, 'manual', 0);
        $gems = fn() => (int) characters::get($user->id)->gems;
        $start = $gems();
        $list = array_column(curiosities::for_student($c, $char), null, 'code');
        $this->assertSame(['cazadoras', 'luces', 'resistentes'], array_keys($list));
        $this->assertSame([6, 8, false, false], [$list['cazadoras']['price'], $list['cazadoras']['back'],
            $list['cazadoras']['bought'], $list['cazadoras']['done']]);
        // Not bought, not to read.
        try {
            curiosities::read($c, $user->id, 'cazadoras');
            $this->fail('Read without buying');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomarket', $e->errorcode);
        }
        curiosities::buy($c, $user->id, 'cazadoras');
        $this->assertSame($start - 6, $gems());
        foreach (['cazadoras' => 'alreadyowned', 'nada' => 'nomarket'] as $code => $error) {
            try {
                curiosities::buy($c, $user->id, $code);
                $this->fail('Bought ' . $code);
            } catch (\moodle_exception $e) {
                $this->assertSame($error, $e->errorcode);
            }
        }
        // Its pages and questions, each with its answer among its options.
        $read = curiosities::read($c, $user->id, 'cazadoras');
        $this->assertCount(3, $read['pages']);
        $this->assertCount(2, $read['questions']);
        foreach ($read['questions'] as $q) {
            $this->assertContains($q['answer'], $q['options']);
        }
        $this->assertStringContainsString('dionea', $read['fact']);
        // A wrong answer: not ended, nothing given.
        $wrong = array_map(fn($q) => array_values(array_diff($q['options'], [$q['answer']]))[0], $read['questions']);
        $this->assertSame(
            ['right' => false, 'first' => false, 'gems' => 0, 'seals' => 0],
            curiosities::finish($c, $user->id, 'cazadoras', $wrong)
        );
        // All right: the gems back and two more, the seal and the golden card; once.
        $right = array_column($read['questions'], 'answer');
        $this->assertSame(
            ['right' => true, 'first' => true, 'gems' => 8, 'seals' => 1],
            curiosities::finish($c, $user->id, 'cazadoras', $right)
        );
        $this->assertSame($start + 2, $gems());
        $this->assertSame(
            ['right' => true, 'first' => false, 'gems' => 0, 'seals' => 1],
            curiosities::finish($c, $user->id, 'cazadoras', $right)
        );
        $this->assertSame($start + 2, $gems());
        $this->assertTrue(array_column(curiosities::for_student($c, $char), null, 'code')['cazadoras']['done']);
        // In pause, the shop waits.
        engine::pause(engine::member($char, $c), 'Test', 0);
        try {
            curiosities::buy($c, $user->id, 'luces');
            $this->fail('Bought in pause');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomarket', $e->errorcode);
        }
    }
}
