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
 * Tests for the page of the day.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\daily
 */
#[\PHPUnit\Framework\Attributes\CoversClass(daily::class)]
final class daily_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment: a Monday at noon. */
    private const NOW = 1791720000;

    /**
     * A student of the pilot mission who has played the nest (so the creature is born and the first chapter played).
     *
     * @return array [course, char, member]
     */
    private function make_student(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $course->id, self::NOW);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $events = autoplayer::play(json_decode($scene->definition, true), [], ['sage'], [], 'sage');
        scenes::play($course->id, $user->id, $scene->id, $events, null, self::NOW);
        return [$course, characters::get($user->id), $DB->get_record(self::T . 'member', ['id' => $member->id])];
    }

    /**
     * The right option of the page of a day, as the server knows it.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int $now
     * @return int
     */
    private function right(\stdClass $char, \stdClass $member, int $now): int {
        $page = daily::today($char, $member, $now);
        $this->assertFalse($page['answered']);
        $this->assertArrayNotHasKey('correct', $page);
        // The phone does not know the answer before answering; the test plays each option on a copy of the day.
        $mission = missions::get((int) $member->courseid);
        foreach ($mission->data['questions'] as $q) {
            if ($q['text'] === $page['question']['text']) {
                return array_search($q['options'][0], $page['question']['options'], true);
            }
        }
        $this->fail('The page is not a question of the mission');
    }

    public function test_the_page_comes_from_the_chapters_played_and_right_gives_breath_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $char, $member] = $this->make_student();
        $DB->set_field(self::T . 'member', 'breath', 1, ['id' => $member->id]);
        $member->breath = 1;
        $page = daily::today($char, $member, self::NOW);
        $mission = missions::get($course->id);
        $claro = array_column(array_filter($mission->data['questions'], fn($q) => $q['chapter'] === 'claro'), 'text');
        $this->assertContains($page['question']['text'], $claro);
        // The same page all day.
        $this->assertSame($page['question'], daily::today($char, $member, self::NOW + 3 * HOURSECS)['question']);
        $this->assertTrue(daily::answer($char, $member, $this->right($char, $member, self::NOW), self::NOW));
        $this->assertEquals(1 + daily::BREATH, $DB->get_field(self::T . 'member', 'breath', ['id' => $member->id]));
        $done = daily::today($char, $member, self::NOW);
        $this->assertTrue($done['answered'] && $done['right']);
        $this->assertArrayHasKey('correct', $done);
        $this->assertEquals(1, $done['streak']);
        // Once a day; nothing ever gives saber.
        try {
            daily::answer($char, $member, 0, self::NOW);
            $this->fail('Two pages in a day');
        } catch (\moodle_exception $e) {
            $this->assertSame('pagedone', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['charid' => $char->id, 'reason' => 'page',
            'resource' => 'saber']));
    }

    public function test_three_days_in_a_row_and_a_wrong_page_comes_back(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_student();
        for ($d = 0; $d < 3; $d++) {
            $now = self::NOW + $d * DAYSECS;
            daily::answer($char, $member, $this->right($char, $member, $now), $now);
        }
        $this->assertEquals(3, daily::today($char, $member, self::NOW + 2 * DAYSECS)['streak']);
        // The next day, before answering, the streak is still there.
        $this->assertEquals(3, daily::today($char, $member, self::NOW + 3 * DAYSECS)['streak']);
        // A wrong one breaks it, and that question comes back the next day.
        $now = self::NOW + 3 * DAYSECS;
        $wrong = daily::today($char, $member, $now);
        $right = $this->right($char, $member, $now);
        $this->assertFalse(daily::answer($char, $member, ($right + 1) % count($wrong['question']['options']), $now));
        $this->assertEquals(0, daily::today($char, $member, $now)['streak']);
        $next = daily::today($char, $member, $now + DAYSECS);
        $this->assertSame($wrong['question']['text'], $next['question']['text']);
    }

    public function test_no_page_without_a_creature_or_while_paused(): void {
        $this->resetAfterTest();
        [$course, $char, $member] = $this->make_student();
        $paused = engine::pause($member, 'A break', 2, self::NOW);
        $this->assertNull(daily::today($char, $paused, self::NOW));
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $new = characters::create($user->id, 'scout', 'm');
        $this->assertNull(daily::today($new, engine::member($new, $course->id, self::NOW), self::NOW));
    }
}
