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
 * Tests for the reserve of breath of a team: what fills it, once, and the spendings that two members agree on.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\reserve
 */
#[\PHPUnit\Framework\Attributes\CoversClass(reserve::class)]
final class reserve_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment, at midday. */
    private const NOW = 1791720000;

    /**
     * A course with the mission and a team of three.
     *
     * @return array [course, group, characters (three)]
     */
    private function make_team(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $group = $gen->create_group(['courseid' => $course->id]);
        $chars = [];
        foreach (['protector', 'healer', 'sage'] as $role) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
            $chars[] = characters::create($user->id, $role, 'f');
            engine::member(end($chars), $course->id, self::NOW);
        }
        return [$course, $group, $chars];
    }

    public function test_it_fills_once_for_each_thing_up_to_the_most_and_gives_back_what_went_in(): void {
        $this->resetAfterTest();
        [$course, $group] = $this->make_team();
        reserve::credit($course->id, (int) $group->id, 'pacts-claro', reserve::PACTS, self::NOW);
        reserve::credit($course->id, (int) $group->id, 'pacts-claro', reserve::PACTS, self::NOW);
        $this->assertSame(reserve::PACTS, reserve::amount((int) $group->id));
        foreach (range(1, 10) as $i) {
            reserve::credit($course->id, (int) $group->id, 'task-' . $i, reserve::TASK, self::NOW);
        }
        $this->assertSame(reserve::MAX, reserve::amount((int) $group->id));
        // What filled it when it was full gave nothing: taken back, it takes nothing.
        reserve::uncredit($course->id, (int) $group->id, 'task-10', self::NOW);
        $this->assertSame(reserve::MAX, reserve::amount((int) $group->id));
        reserve::uncredit($course->id, (int) $group->id, 'pacts-claro', self::NOW);
        $this->assertSame(reserve::MAX - reserve::PACTS, reserve::amount((int) $group->id));
    }

    public function test_the_task_of_a_place_validated_fills_it(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $group, $chars] = $this->make_team();
        $task = $DB->get_records_select(
            self::T . 'challenge',
            'courseid = ? AND chapterid > 0 AND cmid = 0',
            [$course->id],
            'id',
            '*',
            0,
            1
        );
        classroom::validate((int) reset($task)->id, array_map(fn($c) => (int) $c->userid, $chars), 'done', null, 0, self::NOW);
        $this->assertSame(reserve::TASK, reserve::amount((int) $group->id));
    }

    public function test_a_breather_needs_another_member_to_confirm_it(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $group, $chars] = $this->make_team();
        try {
            reserve::propose($course->id, $chars[0], 'refresh', self::NOW);
            $this->fail('Proposed with an empty reserve');
        } catch (\moodle_exception $e) {
            $this->assertSame('reserveshort', $e->errorcode);
        }
        reserve::credit($course->id, (int) $group->id, 'pacts-claro', reserve::COSTS['refresh'], self::NOW);
        foreach ($chars as $char) {
            $DB->set_field(self::T . 'member', 'energy', 3, ['charid' => $char->id, 'courseid' => $course->id]);
        }
        $spend = reserve::propose($course->id, $chars[0], 'refresh', self::NOW);
        // Proposed again, the same proposal; who proposed it cannot confirm it.
        $this->assertSame($spend->id, reserve::propose($course->id, $chars[0], 'refresh', self::NOW)->id);
        try {
            reserve::confirm($course->id, $chars[0], (int) $spend->id, self::NOW);
            $this->fail('Confirmed by who proposed it');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Another member', $e->getMessage());
        }
        $view = reserve::view($course->id, (int) $group->id, $chars[1], [], self::NOW);
        $this->assertSame([['id' => (int) $spend->id, 'kind' => 'refresh', 'mine' => false, 'by' => '']], $view['open']);
        $this->assertSame('done', reserve::confirm($course->id, $chars[1], (int) $spend->id, self::NOW)->status);
        $this->assertSame(0, reserve::amount((int) $group->id));
        foreach ($chars as $char) {
            $this->assertEquals(3 + reserve::REFRESH_ENERGY, engine::member($char, $course->id, self::NOW)->energy);
        }
    }

    public function test_who_is_worn_out_asks_to_get_up_and_a_teammate_confirms(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $group, $chars] = $this->make_team();
        reserve::credit($course->id, (int) $group->id, 'pacts-claro', reserve::COSTS['rise'], self::NOW);
        try {
            reserve::propose($course->id, $chars[1], 'rise', self::NOW);
            $this->fail('Asked by who is not worn out');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('worn out', $e->getMessage());
        }
        $DB->set_field(self::T . 'member', 'energy', 0, ['charid' => $chars[0]->id, 'courseid' => $course->id]);
        $DB->set_field(self::T . 'member', 'status', 'fallen', ['charid' => $chars[0]->id, 'courseid' => $course->id]);
        $spend = reserve::propose($course->id, $chars[0], 'rise', self::NOW);
        // Another day, the proposal is not open any more; the same day, a teammate gets them up.
        try {
            reserve::confirm($course->id, $chars[2], (int) $spend->id, self::NOW + DAYSECS);
            $this->fail('Confirmed another day');
        } catch (\moodle_exception $e) {
            $this->assertSame('nospend', $e->errorcode);
        }
        reserve::confirm($course->id, $chars[2], (int) $spend->id, self::NOW + 60);
        $this->assertSame('active', engine::member($chars[0], $course->id, self::NOW + 60)->status);
        $this->assertSame(0, reserve::amount((int) $group->id));
    }

    public function test_who_proposed_it_takes_it_back(): void {
        $this->resetAfterTest();
        [$course, $group, $chars] = $this->make_team();
        reserve::credit($course->id, (int) $group->id, 'pacts-claro', reserve::COSTS['refresh'], self::NOW);
        $spend = reserve::propose($course->id, $chars[0], 'refresh', self::NOW);
        reserve::cancel($course->id, $chars[1], (int) $spend->id);
        $this->assertCount(1, reserve::view($course->id, (int) $group->id, $chars[0], [], self::NOW)['open']);
        reserve::cancel($course->id, $chars[0], (int) $spend->id);
        $this->assertSame([], reserve::view($course->id, (int) $group->id, $chars[0], [], self::NOW)['open']);
        $this->assertSame(reserve::COSTS['refresh'], reserve::amount((int) $group->id));
    }
}
