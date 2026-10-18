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
 * Tests for the encounters with the beings of the valley and the field guide of the beings.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\encounters
 */
#[\PHPUnit\Framework\Attributes\CoversClass(encounters::class)]
final class encounters_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A class with the pilot mission and a student.
     *
     * @return array [course, char, member, mission]
     */
    private function make_class(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $course->id);
        return [$course, $char, $member, missions::get($course->id)];
    }

    public function test_a_being_peeks_out_once_a_day_and_its_options_are_of_its_group(): void {
        [, $char, , $mission] = $this->make_class();
        $now = time();
        $being = encounters::today($char, $mission, 'claro', $now);
        $this->assertSame('claro', $being['place']);
        $view = encounters::view($being, $mission);
        $this->assertCount(3, $view['clues']);
        $this->assertCount(4, $view['options']);
        $this->assertContains((string) $being['id'], array_column($view['options'], 'id'));
        // The other three are of the same group: the creature says the group, it does not give the name away.
        $groups = array_column($mission->data['encounters'], 'group', 'id');
        foreach ($view['options'] as $option) {
            if (isset($groups[$option['id']])) {
                $this->assertSame($being['group'], $groups[$option['id']]);
            } else {
                $this->assertStringStartsWith('decoy-', $option['id']);
            }
        }
        // The same being all the day, the same question.
        $this->assertSame($being['id'], encounters::today($char, $mission, 'claro', $now + 60)['id']);
        $this->assertSame($view, encounters::view($being, $mission));
    }

    public function test_recognised_with_one_clue_it_goes_golden_to_the_guide_and_gives_gems(): void {
        global $DB;
        [, $char, $member, $mission] = $this->make_class();
        $now = time();
        $being = encounters::today($char, $mission, 'claro', $now);
        $result = encounters::answer($char, $member, $mission, 'claro', (string) $being['id'], 1, $now);
        $this->assertTrue($result['right']);
        $this->assertTrue($result['golden']);
        $this->assertSame(2 * encounters::GEMS, $result['gems']);
        $this->assertNotSame('', $result['fact']);
        // Once a day in each place: nothing more peeks out there today.
        $this->assertNull(encounters::today($char, $mission, 'claro', $now + 60));
        // In the guide, golden; the others of the place, gaps.
        $guide = array_column(encounters::guide($char, $mission), null, 'place');
        $this->assertSame(6, $guide['claro']['total']);
        $this->assertSame([(string) $being['id']], array_column($guide['claro']['beings'], 'id'));
        $this->assertTrue($guide['claro']['beings'][0]['golden']);
        // Never saber.
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['charid' => $char->id, 'resource' => 'saber',
            'reason' => 'encounter']));
        // Another day, another being of the place: the one registered does not come back.
        $tomorrow = encounters::today($char, $mission, 'claro', $now + DAYSECS);
        $this->assertNotSame($being['id'], $tomorrow['id']);
    }

    public function test_not_recognised_it_hides_until_another_day_without_a_cost(): void {
        [, $char, $member, $mission] = $this->make_class();
        $now = time();
        $being = encounters::today($char, $mission, 'bosque', $now);
        $wrong = array_values(array_filter(encounters::view($being, $mission)['options'], fn($o) => $o['id'] !== $being['id']));
        $result = encounters::answer($char, $member, $mission, 'bosque', $wrong[0]['id'], 3, $now);
        $this->assertFalse($result['right']);
        $this->assertSame(0, $result['gems']);
        $this->assertNull(encounters::today($char, $mission, 'bosque', $now + 60));
        $this->assertSame([], array_column(encounters::guide($char, $mission), 'beings', 'place')['bosque']);
        // Recognised later with three clues: a card, not golden.
        $later = encounters::today($char, $mission, 'bosque', $now + DAYSECS);
        $result = encounters::answer($char, $member, $mission, 'bosque', (string) $later['id'], 3, $now + DAYSECS);
        $this->assertTrue($result['right']);
        $this->assertFalse($result['golden']);
        $this->assertSame(encounters::GEMS, $result['gems']);
    }

    public function test_some_beings_only_peek_out_with_the_lantern(): void {
        [$course, $char, , $mission] = $this->make_class();
        $night = array_column(array_filter($mission->data['encounters'], fn($b) => !empty($b['requires']['item'])), 'id');
        $this->assertNotEmpty($night);
        // Without the lantern, over many days, none of them peeks out.
        $seen = [];
        for ($day = 0; $day < 20; $day++) {
            foreach (['bosque', 'jardin', 'torre'] as $place) {
                $b = encounters::today($char, $mission, $place, time() + $day * DAYSECS);
                if ($b) {
                    $seen[] = $b['id'];
                }
            }
        }
        $this->assertSame([], array_intersect($night, $seen));
        $this->assertNotNull($course);
    }
}
