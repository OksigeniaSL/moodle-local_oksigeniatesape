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
        // And the being the sheet talks about, drawn, if it has one.
        $this->assertMatchesRegularExpression('/^(being\.[a-z]+)?$/', $view['picture']);
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

    public function test_the_footprints_come_one_each_school_day_since_the_last_lantern(): void {
        global $DB;
        [$course] = $this->make_class();
        $mission = missions::get($course->id);
        $DB->set_field(self::T . 'mission', 'timecreated', $this->monday, ['id' => $mission->id]);
        $mission = missions::get($course->id);
        $mission->timecreated = $this->monday;
        // Tuesday, Wednesday and Thursday.
        $this->assertSame(3, stains::prints($mission, $this->monday + 3 * DAYSECS));
        // The weekend does not count: from Monday to the next Monday, five.
        $this->assertSame(5, stains::prints($mission, $this->monday + 7 * DAYSECS));
        $this->assertSame(5, stains::prints($mission, $this->monday + 14 * DAYSECS));
        // The class lit a lantern on Wednesday: on Thursday, one.
        $DB->set_field(self::T . 'chapter', 'litat', $this->monday + 2 * DAYSECS, ['missionid' => $mission->id, 'code' => 'claro']);
        $this->assertSame(1, stains::prints($mission, $this->monday + 3 * DAYSECS));
        regions::save($course->id, ['mischief' => 'off']);
        $this->assertSame(0, stains::prints($mission, $this->monday + 3 * DAYSECS));
    }

    public function test_a_knot_left_hanging_has_its_page_stolen_and_the_class_brings_it_back(): void {
        global $DB;
        [$course, $member] = $this->make_class();
        $mission = missions::get($course->id);
        // The class left the Rolling Stone hanging in a battle last week.
        $DB->insert_record(self::T . 'live', (object) ['courseid' => $course->id, 'kind' => 'battle', 'round' => 0,
            'state' => json_encode(['knots' => ['piedra'], 'hanging' => ['piedra']]), 'timecreated' => $this->monday - DAYSECS,
            'timemodified' => $this->monday - DAYSECS, 'timeend' => $this->monday - DAYSECS]);
        $open = stains::open($course->id, $this->monday);
        $this->assertCount(1, $open);
        $page = $open[0];
        $this->assertSame(['page', 'piedra', 'ser-vivo', 'claro'], [$page->kind, $page->game, $page->sheet, $page->chapter]);
        // What the students see: the page, what the Embrollo claims and today's question, without the right option.
        $view = stains::view($page, $mission);
        $this->assertSame('page', $view['kind']);
        $this->assertSame('Ser vivo', $view['title']);
        $this->assertNotSame('', $view['claim']);
        $this->assertCount(4, $view['options']);
        $this->assertArrayNotHasKey('correct', $view);
        // Not hidden; after a battle lost (a rematch pending), the Embrollo has hidden it: found by opening its place.
        $this->assertFalse($view['hidden']);
        $DB->set_field(self::T . 'region', 'battlestate', json_encode(['left' => 0.5]), ['courseid' => $course->id]);
        regions::forget();
        $this->assertTrue(stains::view($page, $mission)['hidden']);
        $DB->set_field(self::T . 'region', 'battlestate', null, ['courseid' => $course->id]);
        regions::forget();
        $ids = $mission->data['battle']['knots'][0]['questions'];
        $day = (int) (new \DateTimeImmutable('@' . $this->monday))->setTimezone(engine::timezone($course->id))->format('Ymd');
        $correct = questions::get($course->id, $ids[$day % count($ids)], (int) $page->id)['correct'];
        // A wrong answer: the page escapes, and that student has tried for today.
        $char = characters::get((int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]));
        $this->assertFalse(stains::recover($course->id, (int) $page->id, $char, $member, ($correct + 1) % 4, $this->monday)['right']);
        $this->assertTrue(stains::tried($char, $page, $mission, $this->monday));
        try {
            stains::recover($course->id, (int) $page->id, $char, $member, $correct, $this->monday);
            $this->fail('Two tries the same day');
        } catch (\moodle_exception $e) {
            $this->assertSame('pagetried', $e->errorcode);
        }
        // A classmate answers right: back for the whole class, with a gem, the page and the why of its knot.
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $mate = characters::create($user->id, 'sage', 'm');
        $result = stains::recover($course->id, (int) $page->id, $mate, engine::member($mate, $course->id), $correct, $this->monday);
        $this->assertTrue($result['right']);
        $this->assertSame(stains::GEMS, $result['gems']);
        $this->assertStringContainsString('se nutre', $result['text']);
        $this->assertNotSame('', $result['why']);
        $this->assertSame([], stains::open($course->id, $this->monday + 60));
        // The next day the knot is still hanging, but its page is not stolen again this week: a stain.
        $next = stains::open($course->id, $this->monday + DAYSECS);
        $this->assertSame(['stain'], array_column($next, 'kind'));
    }

    public function test_the_class_cleans_it_aloud_on_the_class_screen(): void {
        global $DB;
        [$course] = $this->make_class();
        $stain = stains::open($course->id, $this->monday)[0];
        // The words of the sheet go to the class screen too, without what is right.
        $shown = array_column(today::get($course->id)['stains'], null, 'id')[$stain->id];
        $this->assertNotEmpty($shown['parts']);
        $this->assertArrayNotHasKey('right', $shown['parts'][0]);
        // With a mistake left, the ink stays; with all of them, clean for the class, and nobody gets a gem.
        $done = $this->cleaning($stain, $course->id);
        $missing = ['marks' => array_slice($done['marks'], 1), 'misses' => 0];
        $this->assertFalse(stains::clean_class($course->id, (int) $stain->id, $missing, $this->monday)['clean']);
        $result = stains::clean_class($course->id, (int) $stain->id, $done, $this->monday);
        $this->assertTrue($result['clean']);
        $this->assertNotSame('', $result['complaint']);
        $this->assertNotEmpty($result['solution'][0]['right']);
        $this->assertSame([], stains::open($course->id, $this->monday + 60));
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['reason' => 'stain']));
        // Clean, it is not cleaned again.
        $this->expectException(\moodle_exception::class);
        stains::clean_class($course->id, (int) $stain->id, $done, $this->monday + 60);
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
