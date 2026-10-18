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
 * Tests for what the Embrollo does every day: the stains on the place of today, cleaned by a student or by the class.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\stains
 */
#[\PHPUnit\Framework\Attributes\CoversClass(stains::class)]
final class stains_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A Monday at noon, in the time zone of the site. */
    private int $monday;

    /**
     * A class with the pilot mission and a student.
     *
     * @param string $mode
     * @return array [course, member]
     */
    private function make_class(string $mode = 'calm'): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1, 'mischief' => $mode]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $member = engine::member(characters::create($user->id, 'sage', 'f'), $course->id);
        $this->monday = (new \DateTimeImmutable('next monday 12:00', \core_date::get_server_timezone_object()))->getTimestamp();
        return [$course, $member];
    }

    /**
     * What cleans a stain: every mistake of its sheet.
     *
     * @param \stdClass $stain
     * @param int $courseid
     * @return array
     */
    private function cleaning(\stdClass $stain, int $courseid): array {
        $view = stains::view($stain, missions::get($courseid));
        return ['marks' => array_values(array_filter(array_column($view['parts'], 'mark'), fn($m) => $m >= 0)), 'misses' => 0];
    }

    public function test_the_embrollo_stains_the_place_of_today_once_a_school_day(): void {
        [$course] = $this->make_class();
        $open = stains::open($course->id, $this->monday);
        $this->assertCount(1, $open);
        $this->assertSame('claro', $open[0]->chapter);
        $this->assertSame('enredoclaro', $open[0]->game);
        // What the students see: the place, the title and the words, without what is right.
        $view = stains::view($open[0], missions::get($course->id));
        $this->assertNotSame('', $view['place']);
        $this->assertArrayNotHasKey('right', $view['parts'][1]);
        // The same day, the same stain; calm, never a second one while it is there.
        $this->assertCount(1, stains::open($course->id, $this->monday + 3600));
        $this->assertCount(1, stains::open($course->id, $this->monday + DAYSECS));
        // At the weekend, nothing new.
        global $DB;
        $before = $DB->count_records(self::T . 'stain');
        stains::open($course->id, $this->monday + 5 * DAYSECS);
        $this->assertSame($before, $DB->count_records(self::T . 'stain'));
        // Off, nothing at all.
        regions::save($course->id, ['mischief' => 'off']);
        $this->assertSame([], stains::open($course->id, $this->monday + 2 * DAYSECS));
    }

    public function test_naughty_it_leaves_one_more_each_day_it_is_not_cleaned_up_to_three(): void {
        [$course] = $this->make_class('naughty');
        foreach ([0, 1, 2, 3] as $day) {
            $open = stains::open($course->id, $this->monday + $day * DAYSECS);
        }
        $this->assertCount(stains::MAX, $open);
        // Each one with the next sheet of the hunt for mistakes of the place.
        $this->assertCount(3, array_unique(array_column($open, 'sheet')));
    }

    public function test_who_cleans_it_gets_a_gem_and_it_is_clean_for_the_whole_class(): void {
        [$course, $member] = $this->make_class();
        $stain = stains::open($course->id, $this->monday)[0];
        // With a mistake left, there is still ink.
        $done = $this->cleaning($stain, $course->id);
        $missing = ['marks' => array_slice($done['marks'], 1), 'misses' => 0];
        $this->assertFalse(stains::clean($course->id, (int) $stain->id, $member, $missing, $this->monday)['clean']);
        $result = stains::clean($course->id, (int) $stain->id, $member, $done, $this->monday);
        $this->assertTrue($result['clean']);
        $this->assertSame(stains::GEMS, $result['gems']);
        $this->assertNotSame('', $result['complaint']);
        $this->assertNotEmpty($result['solution'][0]['right']);
        $this->assertSame([], stains::open($course->id, $this->monday + 60));
        // Clean, nobody cleans it again.
        $this->expectException(\moodle_exception::class);
        stains::clean($course->id, (int) $stain->id, $member, $done, $this->monday + 60);
    }

    public function test_the_teacher_takes_it_as_cleaned_by_the_class(): void {
        global $DB;
        [$course, $member] = $this->make_class();
        $stain = stains::open($course->id, $this->monday)[0];
        stains::erase($course->id, (int) $stain->id, $this->monday);
        $this->assertSame([], stains::open($course->id, $this->monday + 60));
        $this->assertEquals(1, $DB->get_field(self::T . 'stain', 'erased', ['id' => $stain->id]));
        // Nobody got a gem for it.
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['reason' => 'stain']));
        // The next day, calm, a new one.
        $this->assertCount(1, stains::open($course->id, $this->monday + DAYSECS));
    }
}
