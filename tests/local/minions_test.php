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
 * Tests for the minions of the Embrollo on the road of the map.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minions::class)]
final class minions_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment, a Tuesday at noon. */
    private const NOW = 1791720000;

    /**
     * The right option of the question a minion asks a student on a day.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $code
     * @param int $now
     * @return int
     */
    private function right(\stdClass $char, \stdClass $mission, string $code, int $now): int {
        $knot = array_values(array_filter($mission->data['battle']['knots'], fn($k) => $k['code'] === $code))[0];
        $method = new \ReflectionMethod(minions::class, 'question');
        $day = engine::day_start($now, (int) $mission->courseid);
        return (int) $method->invoke(null, $char, $mission, $knot, $day)['correct'];
    }

    public function test_a_minion_stands_on_the_road_until_untangled_or_the_class_lights_the_place(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $mission = missions::get($course->id);
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $course->id, self::NOW);
        // Nothing lit yet: the first place is the one the class is opening.
        $this->assertSame(['claro'], minions::places($mission));
        $minion = minions::on_road($char, $mission, 'claro', self::NOW);
        $this->assertNotNull($minion);
        $this->assertNotSame('', $minion['claim']);
        $this->assertGreaterThanOrEqual(2, count($minion['options']));
        $this->assertFalse($minion['tried']);
        $this->assertNull(minions::on_road($char, $mission, 'biblioteca', self::NOW));
        // Wrong: it laughs and waits until tomorrow; a second try the same day is not taken.
        $right = $this->right($char, $mission, $minion['code'], self::NOW);
        $wrong = minions::answer($char, $member, $minion['code'], $right === 0 ? 1 : 0, self::NOW);
        $this->assertFalse($wrong['right']);
        $this->assertSame($right, $wrong['correct']);
        $this->assertTrue(minions::on_road($char, $mission, 'claro', self::NOW)['tried']);
        try {
            minions::answer($char, $member, $minion['code'], $right, self::NOW);
            $this->fail('A second try the same day');
        } catch (\moodle_exception $e) {
            $this->assertSame('miniontried', $e->errorcode);
        }
        // Tomorrow, right: untangled, with a gem, and what undoes it.
        $tomorrow = self::NOW + DAYSECS;
        $gems = (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]);
        $done = minions::answer(
            $char,
            $member,
            $minion['code'],
            $this->right($char, $mission, $minion['code'], $tomorrow),
            $tomorrow
        );
        $this->assertTrue($done['right']);
        $this->assertSame(minions::GEMS, $done['gems']);
        $this->assertNotSame('', $done['weakness']);
        $this->assertSame($gems + minions::GEMS, (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]));
        $this->assertTrue($DB->record_exists(self::T . 'found', ['charid' => $char->id, 'kind' => 'untangled',
            'code' => $minion['code']]));
        $next = minions::on_road($char, $mission, 'claro', $tomorrow);
        $this->assertTrue($next === null || $next['code'] !== $minion['code']);
        // The class lights the place: its minions are gone, and the next place has its own.
        $DB->set_field(self::T . 'chapter', 'litat', $tomorrow, ['missionid' => $mission->id, 'code' => 'claro']);
        $this->assertNull(minions::on_road($char, $mission, 'claro', $tomorrow));
        $this->assertSame(['biblioteca'], minions::places($mission));
        $this->assertNotNull(minions::on_road($char, $mission, 'biblioteca', $tomorrow));
    }
}
