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
            // The first team gets everything right; the second team, only the first two.
            foreach ($members as $i => $member) {
                $right = $this->correct($live);
                live::answer($live, $member, $round, $i < 2 || $round < 3 ? $right : ($right + 1) % 2, self::NOW);
            }
            $live = live::reveal($live, self::NOW);
        }
        $view = live::board_view($live, self::NOW);
        // Six answers of ten right are needed: four players and four questions.
        $this->assertEquals(10, $view['maxhp']);
        $this->assertEquals(0, $view['hp']);
        $live = live::finish($live, 2, self::NOW);
        $this->assertEquals('won', live::board_view($live, self::NOW)['outcome']);
        $best = live::device_view($live, $members[0], self::NOW)['prize'];
        $other = live::device_view($live, $members[2], self::NOW)['prize'];
        $this->assertEquals(live::WIN_SABER, $best['saber']);
        $this->assertEquals(live::WIN_GEMS, $best['gems']);
        $this->assertTrue($best['best']);
        // The sample mission has an object for the team that helped most.
        $this->assertEquals('el hilo desenredado', $best['item']);
        $this->assertEquals('', $other['item']);
        $this->assertEquals(live::WIN_GEMS, $other['gems']);
        $thread = $DB->get_field(self::T . 'item', 'id', ['code' => 'thread']);
        $this->assertTrue($DB->record_exists(self::T . 'inventory', ['charid' => $members[0]->charid, 'itemid' => $thread]));
        $this->assertFalse($DB->record_exists(self::T . 'inventory', ['charid' => $members[2]->charid, 'itemid' => $thread]));
        $where = "reason = 'battle' AND refid = ? AND resource IN ('saber', 'gems')";
        $this->assertEquals(8, $DB->count_records_select(self::T . 'ledger', $where, [$live->id]));
        // Finishing again gives nothing more.
        live::finish($live, 2, self::NOW);
        $this->assertEquals(8, $DB->count_records_select(self::T . 'ledger', $where, [$live->id]));
    }

    public function test_the_team_that_helped_most_is_counted_for_each_player_who_came_in(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        // One player of the first team comes in, and both of the second.
        $players = [$members[0], $members[2], $members[3]];
        foreach ([1, 2] as $battle) {
            $at = self::NOW + $battle;
            $live = live::start($course->id, 'phones', 4, 0, $at);
            foreach ($players as $member) {
                live::join($live, $member, $member->userid, $at);
            }
            for ($round = 1; $round <= 4; $round++) {
                $live = live::next($live, $at);
                $right = $this->correct($live);
                // Four right for one player against six for two: the first team helped most.
                foreach ($players as $i => $member) {
                    live::answer($live, $member, $round, $i === 0 || $round < 4 ? $right : ($right + 1) % 2, $at);
                }
                $live = live::reveal($live, $at);
            }
            $live = live::finish($live, 2, $at);
            $best = live::device_view($live, $members[0], $at)['prize'];
            $other = live::device_view($live, $members[2], $at)['prize'];
            $this->assertTrue($best['best']);
            $this->assertFalse($other['best']);
            $this->assertEquals(live::WIN_GEMS, $other['gems']);
            if ($battle === 1) {
                $this->assertEquals('el hilo desenredado', $best['item']);
                $this->assertEquals(live::WIN_GEMS, $best['gems']);
            } else {
                // They already have the object: the gems instead.
                $this->assertEquals('', $best['item']);
                $this->assertEquals(live::WIN_GEMS + live::TEAM_GEMS, $best['gems']);
            }
        }
    }

    public function test_a_second_tap_on_the_class_screen_does_nothing(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 0, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        $live = live::locked($course->id, fn() => live::next($live, self::NOW, 0));
        $this->assertEquals(1, $live->round);
        // The second tap of "Next" still says round 0: the class stays on question 1.
        $this->assertEquals(1, live::next($live, self::NOW, 0)->round);
        // The answer shown by itself for round 1 does not close round 2 if the teacher already went on.
        $live = live::next(live::reveal($live, self::NOW, 1), self::NOW, 1);
        $this->assertEquals(2, $live->round);
        $live = live::reveal($live, self::NOW, 1);
        $this->assertFalse(live::board_view($live, self::NOW)['revealed']);
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
        $this->assertEquals(5, live::board_view($live, self::NOW)['maxhp']);
        $live = live::reveal($live, self::NOW);
        $live = live::mark($live, (int) $groups[0]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[0]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[1]->id, true, self::NOW);
        $live = live::mark($live, (int) $groups[1]->id, false, self::NOW);
        $view = live::board_view($live, self::NOW);
        $this->assertEquals(4, $view['hp']);
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
        // The class screen, back after a while away, finds it still; the next morning, not.
        $this->assertEquals($second->id, live::current($course->id, self::NOW + live::IDLE + 1, true)->id);
        $this->assertNull(live::current($course->id, self::NOW + live::HOST_IDLE + 1, true));
        // A day after, what each one answered goes; a week after, the battles, which only say how the class did.
        $this->assertEquals(0, live::cleanup(self::NOW + DAYSECS + 1));
        $this->assertEquals(0, $DB->count_records(self::T . 'livein'));
        $this->assertEquals(2, $DB->count_records(self::T . 'live'));
        $this->assertEquals(2, live::cleanup(self::NOW + live::KEPT_DAYS * DAYSECS + 1));
    }

    public function test_who_follows_from_far_hears_how_the_battles_went(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 0, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        $live = live::next($live, self::NOW);
        live::finish($live, 0, self::NOW + 60);
        // Who was not in the battle hears how it went; who was in it, not.
        $missed = live::missed($course->id, self::NOW - 10, (int) $members[3]->charid);
        $this->assertCount(1, $missed);
        $this->assertSame('lost', $missed[0]['outcome']);
        $this->assertSame(self::NOW + 60, $missed[0]['time']);
        $this->assertSame([], live::missed($course->id, self::NOW - 10, (int) $members[0]->charid));
        // Nor a battle before they last came in.
        $this->assertSame([], live::missed($course->id, self::NOW + 61, (int) $members[3]->charid));
    }

    public function test_a_battle_nobody_played_can_be_called_off(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 0, self::NOW);
        live::join($live, $members[0], $members[0]->userid, self::NOW);
        live::cancel($live, self::NOW);
        // Gone for the class screen and for the phones, without an outcome to show.
        $this->assertNull(live::recent($course->id, self::NOW + 1, true));
        $this->assertNull(live::recent($course->id, self::NOW + 1));
        // Once played, it is finished, not called off.
        $live = live::next(live::start($course->id, 'phones', 4, 0, self::NOW), self::NOW);
        $this->expectException(\moodle_exception::class);
        live::cancel($live, self::NOW);
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

    /**
     * Gives each member of the class a role: a sage and a scout in the first team, a protector and a healer in the
     * second, and lets them all in a battle with phones.
     *
     * @param \stdClass $course
     * @param \stdClass[] $members
     * @param int $secs Seconds per question.
     * @return \stdClass The battle.
     */
    private function battle_with_roles(\stdClass $course, array $members, int $secs = 0): \stdClass {
        global $DB;
        foreach (['sage', 'scout', 'protector', 'healer'] as $i => $role) {
            $DB->set_field(self::T . 'char', 'role', $role, ['id' => $members[$i]->charid]);
        }
        $live = live::start($course->id, 'phones', 4, $secs, self::NOW);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        return $live;
    }

    public function test_the_tokens_help_the_team_of_who_uses_them_once_a_battle(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = $this->battle_with_roles($course, $members);
        // Before the first question, no token can be used.
        try {
            live::token($live, $members[0], self::NOW);
            $this->fail('A token was used before the first question');
        } catch (\moodle_exception $e) {
            $this->assertSame('roundclosed', $e->errorcode);
        }
        $live = live::next($live, self::NOW);
        $this->assertSame('glance', live::device_view($live, $members[0], self::NOW)['token']);
        $this->assertSame('glance', live::token($live, $members[0], self::NOW));
        // The glance strikes out a wrong option for the whole team, the same on every phone; not for the other team.
        $struck = live::device_view($live, $members[1], self::NOW)['struck'];
        $this->assertNotEquals($this->correct($live), $struck);
        $this->assertSame($struck, live::device_view($live, $members[0], self::NOW)['struck']);
        $this->assertArrayNotHasKey('struck', live::device_view($live, $members[2], self::NOW));
        $this->assertTrue(live::device_view($live, $members[0], self::NOW)['tokenused']);
        // The trail tells the team in which place of the mission the answer is.
        $this->assertSame('trail', live::token($live, $members[1], self::NOW));
        $chapter = json_decode($live->state, true)['rounds'][1]['chapter'];
        $this->assertNotSame('', $chapter);
        $this->assertNotEmpty(live::device_view($live, $members[0], self::NOW)['trail']);
        // The class screen announces them, with the team.
        $tokens = live::board_view($live, self::NOW)['tokens'];
        $this->assertSame(['glance', 'trail'], array_column($tokens, 'kind'));
        $this->assertNotSame('', $tokens[0]['team']);
        // Once a battle: not again in the next question.
        $live = live::next(live::reveal($live, self::NOW), self::NOW);
        $this->assertArrayNotHasKey('struck', live::device_view($live, $members[0], self::NOW));
        try {
            live::token($live, $members[0], self::NOW);
            $this->fail('A token was used twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('tokenused', $e->errorcode);
        }
    }

    public function test_the_embrollo_pulls_itself_together_unless_a_shield_stops_it(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $live = $this->battle_with_roles($course, $members, 30);
        $answer = function (\stdClass $live, int $round, array $right) use ($members): void {
            foreach ($members as $i => $member) {
                $correct = $this->correct($live);
                live::answer($live, $member, $round, in_array($i, $right, true) ? $correct : ($correct + 1) % 2, self::NOW);
            }
        };
        // Round 1, all right: 10 - 4.
        $live = live::next($live, self::NOW);
        $this->assertEquals(30, live::board_view($live, self::NOW)['left']);
        $answer($live, 1, [0, 1, 2, 3]);
        $live = live::reveal($live, self::NOW);
        $this->assertEquals(6, live::board_view($live, self::NOW)['hp']);
        // Round 2, the class mostly wrong: the Embrollo gets back a tenth of its life.
        $live = live::next($live, self::NOW);
        $answer($live, 2, [0]);
        $live = live::reveal($live, self::NOW);
        $view = live::board_view($live, self::NOW);
        $this->assertEquals(1, $view['counter']);
        $this->assertEquals(6, $view['hp']);
        $this->assertEquals(1, $view['phase']);
        // Round 3, the same, but the protector holds up the shield.
        $live = live::next($live, self::NOW);
        $this->assertSame('shield', live::token($live, $members[2], self::NOW));
        $answer($live, 3, [3]);
        $live = live::reveal($live, self::NOW);
        $view = live::board_view($live, self::NOW);
        $this->assertEquals(0, $view['counter']);
        $this->assertTrue($view['shielded']);
        $this->assertEquals(5, $view['hp']);
        // Down to half its life: the second phase, with less time for each question.
        $this->assertEquals(2, $view['phase']);
        $live = live::next($live, self::NOW);
        $this->assertEquals(30 - live::PHASE_SECS, live::board_view($live, self::NOW)['left']);
        // Round 4, the healer cheers: each right answer of their team takes double life.
        $this->assertSame('cheer', live::token($live, $members[3], self::NOW));
        $answer($live, 4, [2, 3]);
        $live = live::reveal($live, self::NOW);
        $this->assertEquals(1, live::board_view($live, self::NOW)['hp']);
        $this->assertEquals('lost', live::board_view(live::finish($live, 2, self::NOW), self::NOW)['outcome']);
    }

    public function test_the_final_battle_gives_what_the_mission_saves_for_the_end_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $mission = missions::get($course->id);
        $data = $mission->data;
        $data['battle']['final'] = ['saber' => 30, 'item' => 'naturalistlens', 'lantern' => 'torre', 'creature' => 'grow'];
        $DB->set_field(self::T . 'mission', 'definition', json_encode($data), ['id' => $mission->id]);
        missions::forget();
        $DB->set_field(self::T . 'char', 'creaturestage', 1, ['id' => $members[0]->charid]);
        $win = function (bool $final, int $at) use ($course, $members): \stdClass {
            $live = live::start($course->id, 'phones', 4, 0, $at, $final);
            live::join($live, $members[0], $members[0]->userid, $at);
            for ($round = 1; $round <= 4; $round++) {
                $live = live::next($live, $at);
                live::answer($live, $members[0], $round, $this->correct($live), $at);
                $live = live::reveal($live, $at);
            }
            return live::finish($live, 2, $at);
        };
        // An ordinary battle: the usual prize.
        $live = $win(false, self::NOW);
        $this->assertFalse(live::board_view($live, self::NOW)['final']);
        $prize = live::device_view($live, $members[0], self::NOW)['prize'];
        $this->assertEquals(live::WIN_SABER, $prize['saber']);
        $this->assertSame('', $prize['final']);
        // The final one: the saber of the end, its object, the lantern of the tower for the class and the creature grows.
        $live = $win(true, self::NOW + 1);
        $this->assertTrue(live::board_view($live, self::NOW + 1)['final']);
        $prize = live::device_view($live, $members[0], self::NOW + 1)['prize'];
        $this->assertEquals(30, $prize['saber']);
        $this->assertSame('la lupa del naturalista', $prize['final']);
        $this->assertTrue($prize['grew']);
        $this->assertEquals(2, $DB->get_field(self::T . 'char', 'creaturestage', ['id' => $members[0]->charid]));
        $this->assertNotEquals(0, $DB->get_field(self::T . 'chapter', 'litat', ['missionid' => $mission->id, 'code' => 'torre']));
        // Who was not in class that day gets the lantern of the tower too, so as not to miss the badge; nothing else.
        foreach ([1, 2, 3] as $i) {
            $mine = ['charid' => $members[$i]->charid, 'missionid' => $mission->id];
            $this->assertTrue($DB->record_exists(self::T . 'found', $mine + ['kind' => 'lantern', 'code' => 'torre']));
            $this->assertFalse($DB->record_exists(self::T . 'found', $mine + ['kind' => 'final']));
        }
        // Winning it again gives the usual prize.
        $live = $win(true, self::NOW + 2);
        $prize = live::device_view($live, $members[0], self::NOW + 2)['prize'];
        $this->assertEquals(live::WIN_SABER, $prize['saber']);
        $this->assertSame('', $prize['final']);
        $this->assertFalse($prize['grew']);
    }
}
