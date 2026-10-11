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

use local_oksigeniatesape\autoplayer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/autoplayer.php');

/**
 * Tests for the battle against the Embrollo, with phones and aloud.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\live
 * @covers     \local_oksigeniatesape\local\scenes
 */
#[\PHPUnit\Framework\Attributes\CoversClass(live::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scenes::class)]
final class live_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    /**
     * A course with the game on, the sample mission and two teams of two players.
     *
     * @return array [course, members (four, with userid), groups (two)]
     */
    private function make_class(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $groups = [$gen->create_group(['courseid' => $course->id]), $gen->create_group(['courseid' => $course->id])];
        $members = [];
        foreach ([0, 0, 1, 1] as $team) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $groups[$team]->id, 'userid' => $user->id]);
            $member = engine::member(characters::create($user->id, 'sage', 'f'), $course->id, self::NOW);
            $member->userid = $user->id;
            $members[] = $member;
        }
        return [$course, $members, $groups];
    }

    /**
     * The right option of the round being played.
     *
     * @param \stdClass $live
     * @return int
     */
    private function correct(\stdClass $live): int {
        $state = json_decode($live->state, true);
        return (int) $state['rounds'][$live->round]['correct'];
    }

    public function test_phones_never_see_the_answer_before_it_is_shown(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        $live = live::next($live, self::NOW);
        $view = live::device_view($live, $members[0], self::NOW);
        $this->assertTrue($view['joined']);
        $this->assertArrayNotHasKey('correct', $view['question']);
        $this->assertArrayNotHasKey('why', $view['question']);
        $live = live::reveal($live, self::NOW);
        $this->assertArrayHasKey('correct', live::device_view($live, $members[0], self::NOW)['question']);
    }

    public function test_each_player_answers_once_and_only_while_the_round_is_open(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 20, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        $this->assertEquals(1, live::board_view($live, self::NOW)['joined']);
        $live = live::next($live, self::NOW);
        live::answer($live, $members[0], 1, 0, self::NOW);
        live::answer($live, $members[0], 1, 1, self::NOW);
        $this->assertEquals(0, $DB->get_field(self::T . 'livein', 'answer', ['liveid' => $live->id, 'round' => 1]));
        // Out of time, the server says no.
        live::join($live, $members[1], $members[1]->userid, self::NOW);
        $this->expectException(\moodle_exception::class);
        live::answer($live, $members[1], 1, 0, self::NOW + 30);
    }

    public function test_those_who_did_not_come_in_cannot_answer(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::next(live::start($course->id, 'phones', 4, 0, self::NOW), self::NOW);
        $this->expectException(\moodle_exception::class);
        live::answer($live, $members[0], 1, 0, self::NOW);
    }

    public function test_winning_with_phones_rewards_the_players_and_the_best_team(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 0, self::NOW);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        for ($round = 1; $round <= 4; $round++) {
            $live = live::next($live, self::NOW);
            // The first team gets everything right; the second team, nothing.
            foreach ($members as $i => $member) {
                $right = $this->correct($live);
                live::answer($live, $member, $round, $i < 2 ? $right : ($right + 1) % 2, self::NOW);
            }
            $live = live::reveal($live, self::NOW);
        }
        $view = live::board_view($live, self::NOW);
        $this->assertEquals(8, $view['maxhp']);
        $this->assertEquals(0, $view['hp']);
        $live = live::finish($live, 2, self::NOW);
        $this->assertEquals('won', live::board_view($live, self::NOW)['outcome']);
        $best = live::device_view($live, $members[0], self::NOW)['prize'];
        $other = live::device_view($live, $members[2], self::NOW)['prize'];
        $this->assertEquals(live::WIN_SABER, $best['saber']);
        $this->assertEquals(live::WIN_GEMS + live::TEAM_GEMS, $best['gems']);
        $this->assertTrue($best['best']);
        $this->assertEquals(live::WIN_GEMS, $other['gems']);
        $this->assertEquals(8, $DB->count_records(self::T . 'ledger', ['reason' => 'battle', 'refid' => $live->id]));
        // Finishing again gives nothing more.
        live::finish($live, 2, self::NOW);
        $this->assertEquals(8, $DB->count_records(self::T . 'ledger', ['reason' => 'battle', 'refid' => $live->id]));
    }

    public function test_losing_takes_nothing_from_anybody(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 0, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        $live = live::finish(live::next($live, self::NOW), 2, self::NOW);
        $this->assertEquals('lost', live::board_view($live, self::NOW)['outcome']);
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['courseid' => $course->id, 'reason' => 'battle']));
        $this->assertEquals(engine::ENERGY_MAX, $DB->get_field(self::T . 'member', 'energy', ['id' => $members[0]->id]));
    }

    public function test_aloud_the_teacher_marks_the_teams_after_the_answer(): void {
        $this->resetAfterTest();
        [$course, , $groups] = $this->make_class();
        $live = live::next(live::start($course->id, 'aloud', 4, 0, self::NOW), self::NOW);
        $this->assertEquals(4, live::board_view($live, self::NOW)['maxhp']);
        $live = live::reveal($live, self::NOW);
        $live = live::mark($live, (int) $groups[0]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[0]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[1]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[1]->id, false, self::NOW);
        $view = live::board_view($live, self::NOW);
        $this->assertEquals(3, $view['hp']);
        $this->assertEquals([1, 0], array_column($view['teams'], 'mark'));
    }

    public function test_a_new_battle_closes_the_last_one_and_old_ones_are_cleaned_up(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $first = live::start($course->id, 'phones', 4, 0, self::NOW);
        live::join($first, $members[0], $members[0]->userid, self::NOW);
        $second = live::start($course->id, 'phones', 6, 0, self::NOW);
        $this->assertEquals($second->id, live::current($course->id, self::NOW)->id);
        $this->assertNull(live::current($course->id, self::NOW + live::IDLE + 1));
        $this->assertEquals(2, live::cleanup(self::NOW + DAYSECS + 1));
        $this->assertEquals(0, $DB->count_records(self::T . 'livein'));
    }

    public function test_a_vote_of_the_class_counts_each_option(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $scene = $DB->get_records('local_oksigeniatesape_scene', [], 'code', 'id', 0, 1);
        $live = live::start_vote($course->id, (int) reset($scene)->id, self::NOW);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::vote($live, 'Which one?', ['North', 'South', 'East'], self::NOW);
        $this->assertArrayNotHasKey('tally', live::board_view($live, self::NOW));
        foreach ($members as $i => $member) {
            live::answer($live, $member, 1, $i === 3 ? 2 : 0, self::NOW);
        }
        $live = live::reveal($live, self::NOW);
        $this->assertEquals([3, 0, 1], live::board_view($live, self::NOW)['tally']);
        $this->assertEquals([3, 0, 1], live::device_view($live, $members[0], self::NOW)['tally']);
        $this->assertCount(4, live::joined($live));
        // A vote ends without prizes.
        $live = live::finish($live, 2, self::NOW);
        $this->assertEquals('done', live::board_view($live, self::NOW)['outcome']);
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['courseid' => $course->id, 'reason' => 'battle']));
    }

    public function test_a_scene_played_by_the_class_rewards_its_players_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $mission = missions::get($course->id);
        $scene = $DB->get_record('local_oksigeniatesape_scene', ['missionid' => $mission->id, 'code' => 'C3-2'], '*', MUST_EXIST);
        [$items, $roles] = scenes::class_context($course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $items, $roles);
        // Only the first two played (with their phones).
        $charids = [(int) $members[0]->charid, (int) $members[1]->charid];
        $result = scenes::play_class($course->id, (int) $scene->id, $events, null, $charids, self::NOW);
        $this->assertEquals('success', $result['outcome']);
        $this->assertEquals(2, $result['rewarded']);
        $this->assertEquals(0, $DB->count_records(self::T . 'sceneattempt', ['courseid' => $course->id]));
        $saber = $DB->get_field(self::T . 'char', 'saber', ['id' => $members[0]->charid]);
        $this->assertGreaterThan(0, $saber);
        $this->assertEquals(0, $DB->get_field(self::T . 'char', 'saber', ['id' => $members[2]->charid]));
        // Again: nothing more for anybody.
        $this->assertEquals(0, scenes::play_class($course->id, (int) $scene->id, $events, null, $charids, self::NOW)['rewarded']);
    }
}
