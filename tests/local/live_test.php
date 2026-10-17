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

    /**
     * By turns, the right option of the lane of a team in the round being played.
     *
     * @param \stdClass $live
     * @param int $team
     * @return int
     */
    private function lane_correct(\stdClass $live, int $team): int {
        $state = json_decode($live->state, true);
        return (int) $state['rounds'][$live->round]['lanes'][$team]['correct'];
    }

    /**
     * By turns: a turn played to its resolution, each team right or wrong in its lane.
     *
     * @param \stdClass $live
     * @param array $members
     * @param array $groups
     * @param bool[] $right For each team, in order: its players get its lane right.
     * @param int $at When the turn started.
     * @return \stdClass The session, resolved.
     */
    private function play_turn(\stdClass $live, array $members, array $groups, array $right, int $at): \stdClass {
        $strike = $at + live::INTENT + live::PREP;
        foreach ($members as $i => $member) {
            $team = $i < 2 ? 0 : 1;
            $correct = $this->lane_correct($live, (int) $groups[$team]->id);
            live::answer($live, $member, (int) $live->round, $right[$team] ? $correct : ($correct + 1) % 2, $strike + 1);
        }
        return live::tick($live, 2, true, $strike + 31);
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
        $this->assertTrue($best['best']);
        // The object of the mission is what is left of the Embrollo at the end: not in a battle to practise. The team
        // that helped most gets more gems.
        $this->assertEquals('', $best['item']);
        $this->assertEquals(live::WIN_GEMS + live::TEAM_GEMS, $best['gems']);
        $this->assertEquals('', $other['item']);
        $this->assertEquals(live::WIN_GEMS, $other['gems']);
        $thread = $DB->get_field(self::T . 'item', 'id', ['code' => 'thread']);
        $this->assertFalse($thread && $DB->record_exists(self::T . 'inventory', ['charid' => $members[0]->charid,
            'itemid' => $thread]));
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
            // Battles to practise: more gems for the team that helped most, each time.
            $this->assertEquals('', $best['item']);
            $this->assertEquals(live::WIN_GEMS + live::TEAM_GEMS, $best['gems']);
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

    public function test_a_battle_by_turns_announces_the_knot_and_moves_on_by_itself(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        foreach (['sage', 'scout', 'protector', 'healer'] as $i => $role) {
            $DB->set_field(self::T . 'char', 'role', $role, ['id' => $members[$i]->charid]);
        }
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        $knots = json_decode($live->state, true)['knots'];
        $this->assertCount(4, $knots);
        $this->assertSame('piedra', $knots[0]);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        // The Embrollo announces the knot and the team it goes for: the lazada is not seen yet.
        $live = live::next($live, self::NOW);
        $view = live::board_view($live, self::NOW);
        $this->assertSame('intent', $view['stage']);
        $this->assertArrayNotHasKey('question', $view);
        $this->assertNotSame('', $view['knot']['claim']);
        $this->assertSame('', $view['knot']['laugh']);
        $this->assertSame((int) $groups[0]->id, $view['targetid']);
        // While it is announced and got ready, the verbs can be used, and the lazada takes no answers.
        $this->assertSame('shield', live::token($live, $members[2], self::NOW + 2));
        $this->assertSame('prep', live::board_view($live, self::NOW + live::INTENT)['stage']);
        try {
            live::answer($live, $members[0], 1, 0, self::NOW + live::INTENT);
            $this->fail('An answer before the knot fell');
        } catch (\moodle_exception $e) {
            $this->assertSame('roundclosed', $e->errorcode);
        }
        $strike = self::NOW + live::INTENT + live::PREP;
        $view = live::device_view($live, $members[0], $strike);
        $this->assertSame('strike', $view['stage']);
        $this->assertArrayHasKey('question', $view);
        $this->assertSame((int) $groups[0]->id, $view['myteam']);
        // Each team plays its own lane: two of the questions of the knot.
        $board = live::board_view($live, $strike);
        $this->assertCount(2, $board['lanes']);
        $this->assertNotSame($board['lanes'][0]['question']['id'], $board['lanes'][1]['question']['id']);
        $this->assertArrayNotHasKey('correct', $board['lanes'][0]['question']);
        $this->assertSame($board['lanes'][0]['question']['id'], $view['question']['id']);
        $this->assertSame(5, $view['candles']);
        // The team it goes for gets it wrong, the other right; the shield is on the other team, so the attack falls.
        $right = $this->lane_correct($live, (int) $groups[1]->id);
        $wrong = ($this->lane_correct($live, (int) $groups[0]->id) + 1) % 2;
        foreach ($members as $i => $member) {
            live::answer($live, $member, 1, $i < 2 ? $wrong : $right, $strike + 1);
        }
        // Nothing moves before the time is over.
        $this->assertFalse(live::board_view(live::tick($live, 2, true, $strike + 10), $strike + 10)['revealed']);
        // When it is over, the lanes are counted: one thread off; a candle goes out and the team it went for is tangled.
        $end = $strike + 31;
        $live = live::tick($live, 2, true, $end);
        $view = live::board_view($live, $end);
        $this->assertSame('resolve', $view['stage']);
        $this->assertSame(1, $view['damage']);
        $this->assertSame(12, $view['maxhp']);
        $this->assertSame(4, $view['candles']);
        $this->assertSame([(int) $groups[0]->id], $view['tangled']);
        $this->assertFalse($view['knot']['broken']);
        $this->assertGreaterThan(0, $view['counter']);
        $this->assertNotSame('', $view['knot']['laugh']);
        $this->assertTrue(live::device_view($live, $members[2], $end)['done']);
        $this->assertFalse(live::device_view($live, $members[0], $end)['done']);
        // After the resolution, the next turn starts by itself, for the other team.
        $live = live::tick($live, 2, true, $end + live::RESOLVE);
        $this->assertSame(2, (int) $live->round);
        $view = live::board_view($live, $end + live::RESOLVE);
        $this->assertSame('intent', $view['stage']);
        $this->assertSame((int) $groups[1]->id, $view['targetid']);
    }

    public function test_anyone_in_a_team_covers_the_verb_of_a_missing_role_once_a_battle(): void {
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        // Both teams are of sages: the scout, the protector and the healer are missing in each.
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $team = (int) $groups[0]->id;
        $this->assertEqualsCanonicalizing(['trail', 'shield', 'cheer'], live::covers($live, $team));
        $view = live::device_view($live, $members[0], self::NOW + 2);
        $this->assertEqualsCanonicalizing(['trail', 'shield', 'cheer'], $view['covers']);
        $this->assertCount(2, $view['targets']);
        // The verb of a role there is cannot be covered.
        try {
            live::token($live, $members[0], self::NOW + 2, 0, 'glance');
            $this->fail('A sage covered the glance');
        } catch (\moodle_exception $e) {
            $this->assertSame('tokenused', $e->errorcode);
        }
        // One of the team puts the shield of the missing protector on the other team: then, no more covers for the team.
        $this->assertSame('shield', live::token($live, $members[0], self::NOW + 2, (int) $groups[1]->id, 'shield'));
        $this->assertSame([], live::covers($live, $team));
        $this->assertSame([], live::device_view($live, $members[1], self::NOW + 3)['covers']);
        try {
            live::token($live, $members[1], self::NOW + 3, 0, 'trail');
            $this->fail('The team covered twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('tokenused', $e->errorcode);
        }
        // The other team still has its own, and its own verbs are there.
        $this->assertNotSame([], live::covers($live, (int) $groups[1]->id));
        $this->assertSame('glance', live::token($live, $members[1], self::NOW + 3));
    }

    public function test_a_lane_to_put_in_order_is_undone_with_its_steps_in_their_place(): void {
        $this->resetAfterTest();
        [$course] = $this->make_class();
        $mission = missions::get($course->id);
        $knot = array_column(knots::usable($mission, null), null, 'code')['garza'];
        $this->assertContains('order', array_column($knot['lanes'], 'engine'));
        $make = new \ReflectionMethod(live::class, 'lane_of');
        $order = $make->invoke(null, array_values(array_filter($knot['lanes'], fn($l) => $l['engine'] === 'order'))[0], $mission);
        // The tablets see the steps, but not in their order.
        $this->assertSame(['a', 'b', 'c', 'd'], $order['solution']);
        $this->assertNotSame($order['solution'], array_column($order['shown']['items'], 'id'));
        $this->assertTrue(live::lane_right($order, ['order' => ['a', 'b', 'c', 'd']]));
        // With four steps, each one in its place; a step missing or twice is not an order.
        $this->assertFalse(live::lane_right($order, ['order' => ['a', 'c', 'b', 'd']]));
        $this->assertFalse(live::lane_right($order, ['order' => ['a', 'b', 'c']]));
        $this->assertFalse(live::lane_right($order, ['order' => ['a', 'b', 'c', 'c']]));
        // From five steps on, two next to each other swapped are forgiven; two swaps are not.
        $five = ['engine' => 'order', 'solution' => ['a', 'b', 'c', 'd', 'e']];
        $this->assertTrue(live::lane_right($five, ['order' => ['a', 'b', 'd', 'c', 'e']]));
        $this->assertFalse(live::lane_right($five, ['order' => ['b', 'a', 'd', 'c', 'e']]));
    }

    public function test_a_class_of_twenty_five_plays_a_whole_final_battle_by_turns_and_wins_it(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $roles = ['sage', 'scout', 'protector', 'healer', 'sage'];
        $members = [];
        for ($t = 0; $t < 5; $t++) {
            $group = $gen->create_group(['courseid' => $course->id]);
            foreach ($roles as $role) {
                $user = $gen->create_and_enrol($course, 'student');
                $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
                $member = engine::member(characters::create($user->id, $role, 'f'), $course->id, self::NOW);
                $member->userid = $user->id;
                $member->team = (int) $group->id;
                $members[] = $member;
            }
        }
        $now = self::NOW;
        $live = live::start($course->id, 'phones', 10, 30, $now, true, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, $now);
        }
        $lobby = live::board_view($live, $now);
        $this->assertSame(25, $lobby['joined']);
        // From the lobby, the screens know it is a battle by turns: its candles are there.
        $this->assertSame(5, $lobby['maxcandles']);
        $live = live::next($live, $now);
        $engines = [];
        for ($turn = 0; $turn < 10 && json_decode($live->state, true)['outcome'] === ''; $turn++) {
            $round = (int) $live->round;
            $lanes = json_decode($live->state, true)['rounds'][$round]['lanes'];
            $now += live::INTENT + live::PREP + 1;
            // Every team undoes its lane, each in its own way: to choose, everybody; to sort, the one who sorts.
            foreach ($members as $member) {
                $lane = $lanes[$member->team];
                $engine = $lane['engine'] ?? 'choice';
                $engines[$engine] = true;
                if ($engine === 'classify' || $engine === 'order') {
                    if ((int) $lane['speaker'] === (int) $member->charid) {
                        $payload = $engine === 'order' ? ['order' => $lane['solution']] : ['placed' => $lane['solution']];
                        live::strike($live, $member, $round, $payload, $now);
                    }
                } else if ($engine === 'errors') {
                    live::strike($live, $member, $round, ['marks' => array_column($lane['solution'], 'mark')], $now);
                } else {
                    live::answer($live, $member, $round, (int) $lane['correct'], $now);
                }
            }
            // What the class screen and a tablet ask on every poll stays small with a whole class in.
            $reads = $DB->perf_get_reads();
            live::board_view($live, $now);
            live::device_view($live, $members[7], $now);
            $this->assertLessThan(40, $DB->perf_get_reads() - $reads);
            $now += 31;
            $live = live::tick($live, 2, true, $now);
            $view = live::board_view($live, $now);
            $this->assertTrue($view['knot']['broken'] ?? $view['outcome'] !== '');
            $now += live::RESOLVE;
            $live = live::tick($live, 2, true, $now);
        }
        $state = json_decode($live->state, true);
        $this->assertSame('won', $state['outcome']);
        $this->assertSame(5, $state['candles']);
        $this->assertSame([], $state['hanging']);
        // And at the end too, for the jar and its sounds.
        $this->assertSame(5, live::board_view($live, $now)['maxcandles']);
        // On the way, the class chose, sorted and found the mistakes of the Embrollo.
        $this->assertEqualsCanonicalizing(['choice', 'classify', 'errors'], array_keys($engines));
        // Everybody who played gets the prize of the battle.
        $this->assertCount(25, $state['rewarded']);
        $this->assertSame('', (string) $DB->get_field(self::T . 'region', 'battlestate', ['courseid' => $course->id]));
    }

    public function test_a_pause_stops_the_time_of_the_turn_and_nobody_answers_until_it_goes_on(): void {
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $strike = self::NOW + live::INTENT + live::PREP;
        // Ten seconds into the lane, the teacher stops it.
        $live = live::pause($live, $strike + 10);
        $view = live::board_view($live, $strike + 100);
        $this->assertTrue($view['paused']);
        $this->assertFalse($view['open']);
        $this->assertSame('strike', $view['stage']);
        $this->assertSame(20, $view['left']);
        // Nothing moves by itself, and nobody answers or uses a verb.
        $this->assertFalse(live::board_view(live::tick($live, 2, true, $strike + 500), $strike + 500)['revealed']);
        try {
            live::answer($live, $members[0], 1, 0, $strike + 100);
            $this->fail('An answer in the pause');
        } catch (\moodle_exception $e) {
            $this->assertSame('roundclosed', $e->errorcode);
        }
        // It goes on a minute later with the time it had left.
        $live = live::resume($live, $strike + 70);
        $view = live::board_view($live, $strike + 70);
        $this->assertFalse($view['paused']);
        $this->assertTrue($view['open']);
        $this->assertSame(20, $view['left']);
        live::answer($live, $members[0], 1, $this->lane_correct($live, (int) $groups[0]->id), $strike + 71);
        $this->assertFalse(live::board_view(live::tick($live, 2, true, $strike + 85), $strike + 85)['revealed']);
        $this->assertTrue(live::board_view(live::tick($live, 2, true, $strike + 91), $strike + 91)['revealed']);
    }

    public function test_a_knot_breaks_when_every_lane_is_undone_and_the_embrollo_is_dazed(): void {
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        // Both teams undo their lanes: two threads, and three more because the knot breaks; its why is told.
        $live = $this->play_turn($live, $members, $groups, [true, true], self::NOW);
        $view = live::board_view($live, self::NOW + 60);
        $this->assertSame(5, $view['damage']);
        $this->assertTrue($view['knot']['broken']);
        $this->assertNotSame('', $view['knot']['why']);
        $this->assertSame(1, $view['broken']);
        $this->assertSame(5, $view['candles']);
        // The next turn the Embrollo is dazed: it does not attack, and what the class undoes counts double.
        $next = self::NOW + live::INTENT + live::PREP + 31 + live::RESOLVE;
        $live = live::tick($live, 2, true, $next);
        $this->assertSame('dazed', live::board_view($live, $next)['intent']);
        $live = $this->play_turn($live, $members, $groups, [true, false], $next);
        $view = live::board_view($live, $next + 60);
        $this->assertSame(2, $view['damage']);
        $this->assertSame(5, $view['candles']);
        $this->assertSame(0, $view['counter']);
    }

    public function test_by_turns_the_verbs_go_to_a_team_and_rest_two_turns(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        // The protector is in the second team, the healer in the first.
        $DB->set_field(self::T . 'char', 'role', 'protector', ['id' => $members[2]->charid]);
        $DB->set_field(self::T . 'char', 'role', 'healer', ['id' => $members[0]->charid]);
        $live = live::start($course->id, 'phones', 8, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $first = (int) $groups[0]->id;
        // The shield goes on the other team, the one the knot goes for: it fails its lane, and the shield stops the blow.
        $this->assertSame('shield', live::token($live, $members[2], self::NOW + 2, $first));
        $device = live::device_view($live, $members[2], self::NOW + 3);
        $this->assertTrue($device['tokenused']);
        $this->assertSame(3, $device['tokenrest']);
        $this->assertCount(2, $device['targets']);
        $live = $this->play_turn($live, $members, $groups, [false, true], self::NOW);
        $view = live::board_view($live, self::NOW + 60);
        $this->assertTrue($view['shielded']);
        $this->assertSame(5, $view['candles']);
        $this->assertSame([], $view['tangled']);
        // The verb rests: not in the next two turns, again in the third.
        $at = self::NOW;
        foreach ([2, 3] as $round) {
            $at += live::INTENT + live::PREP + 31 + live::RESOLVE;
            $live = live::tick($live, 2, true, $at);
            $this->assertSame($round, (int) $live->round);
            try {
                live::token($live, $members[2], $at + 1, $first);
                $this->fail('A verb used again before its rest');
            } catch (\moodle_exception $e) {
                $this->assertSame('tokenused', $e->errorcode);
            }
            // The first team fails its lane, the second undoes it: in turn 3 the knot goes for the first team, which
            // has no shield now, so a candle goes out and it is tangled.
            $live = $this->play_turn($live, $members, $groups, [false, true], $at);
        }
        $view = live::board_view($live, $at + 60);
        $this->assertSame(4, $view['candles']);
        $this->assertSame([$first], $view['tangled']);
        // In the fourth turn the healer cheers their team: untangled, and a candle lights again.
        $at += live::INTENT + live::PREP + 31 + live::RESOLVE;
        $live = live::tick($live, 2, true, $at);
        $this->assertSame('cheer', live::token($live, $members[0], $at + 1, $first));
        $this->assertSame('shield', live::token($live, $members[2], $at + 1, $first));
        $live = $this->play_turn($live, $members, $groups, [true, true], $at);
        $view = live::board_view($live, $at + 60);
        $this->assertSame([], $view['tangled']);
        $this->assertSame(5, $view['candles']);
        // The class screen says where each verb went: the cheer on its own team, the shield on the other.
        $targets = array_column($view['tokens'], 'target', 'kind');
        $this->assertSame('', $targets['cheer']);
        $this->assertNotSame('', $targets['shield']);
    }

    public function test_a_broken_knot_does_not_come_back_and_the_last_thread_brings_the_hardest(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $live = $this->play_turn($live, $members, $groups, [false, false], self::NOW);
        // The knot planned for the next turn was broken already, and another one is left hanging: that one comes.
        $state = json_decode($live->state, true);
        $state['knots'][1] = 'arena';
        $state['broken'] = ['arena'];
        $state['hanging'] = ['piedra', 'setuga', 'setuga', 'setuga'];
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
        $live = live::next($DB->get_record(self::T . 'live', ['id' => $live->id]), self::NOW + 100);
        $this->assertSame('piedra', json_decode($live->state, true)['knots'][1]);
        // In the last thread, the knot the class could least undo, once (the piedra, unanswered, hangs once more).
        $state = json_decode($live->state, true);
        $state['hp'] = 1;
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
        $live = live::next($DB->get_record(self::T . 'live', ['id' => $live->id]), self::NOW + 200);
        $state = json_decode($live->state, true);
        $this->assertSame('setuga', $state['knots'][2]);
        $this->assertSame('setuga', $state['lastknot']);
        $this->assertNotEmpty($state['rounds'][3]['lanes']);
    }

    public function test_by_turns_the_untangled_thread_is_for_the_class_if_it_broke_half_the_knots(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $live = $this->play_turn($live, $members, $groups, [true, true], self::NOW);
        // One turn, one knot broken: half or more, so everybody who played gets the object of the battle.
        $state = json_decode($live->state, true);
        $state['hp'] = 0;
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
        $live = live::finish($DB->get_record(self::T . 'live', ['id' => $live->id]), 2, self::NOW + 100);
        $state = json_decode($live->state, true);
        $this->assertSame('won', $state['outcome']);
        $this->assertCount(4, $state['joined']);
        foreach ($members as $member) {
            $this->assertTrue($state['rewarded'][$member->charid]['best']);
        }
    }

    public function test_lanes_to_sort_and_to_touch_the_mistakes_are_checked_by_the_server(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $mission = missions::get($course->id);
        $knot = array_column(knots::usable($mission, null), null, 'code')['setuga'];
        $make = new \ReflectionMethod(live::class, 'lane_of');
        $sort = $make->invoke(null, $knot['lanes'][2], $mission);
        $mistakes = $make->invoke(null, $knot['lanes'][3], $mission);
        // What the tablets see does not say where each thing goes, nor the right words.
        $this->assertArrayNotHasKey('bin', $sort['shown']['items'][0]);
        $this->assertArrayNotHasKey('right', $mistakes['shown']['parts'][1]);
        $all = $sort['solution'];
        $this->assertTrue(live::lane_right($sort, ['placed' => $all]));
        // One slip of five things is forgiven; two are not.
        $one = $all;
        $one['seta'] = 'planta';
        $this->assertTrue(live::lane_right($sort, ['placed' => $one]));
        $two = $one;
        $two['musgo'] = 'hongo';
        $this->assertFalse(live::lane_right($sort, ['placed' => $two]));
        $marks = array_column($mistakes['solution'], 'mark');
        $this->assertTrue(live::lane_right($mistakes, ['marks' => $marks, 'misses' => 1]));
        $this->assertFalse(live::lane_right($mistakes, ['marks' => [$marks[0]], 'misses' => 0]));
        $this->assertFalse(live::lane_right($mistakes, ['marks' => $marks, 'misses' => 2]));
        // In a battle: the first team sorts (by one of its players), the second touches the mistakes.
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $state = json_decode($live->state, true);
        $speaker = min((int) $members[0]->charid, (int) $members[1]->charid);
        $state['rounds'][1]['lanes'] = [(int) $groups[0]->id => $sort + ['options' => 2, 'correct' => 1, 'speaker' => $speaker],
            (int) $groups[1]->id => $mistakes + ['options' => 2, 'correct' => 1]];
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
        $live = $DB->get_record(self::T . 'live', ['id' => $live->id]);
        $strike = self::NOW + live::INTENT + live::PREP + 1;
        $sorter = $speaker === (int) $members[0]->charid ? $members[0] : $members[1];
        $other = $sorter === $members[0] ? $members[1] : $members[0];
        $lane = json_decode(live::device_view($live, $other, $strike)['lane'], true);
        $this->assertFalse($lane['mine']);
        $this->assertNotSame('', $lane['speaker']);
        try {
            live::strike($live, $other, 1, ['placed' => $all], $strike);
            $this->fail('Who does not sort for the team in this turn sorted');
        } catch (\invalid_parameter_exception $e) {
            $this->assertTrue(true);
        }
        live::strike($live, $sorter, 1, ['placed' => $all], $strike);
        live::strike($live, $members[2], 1, ['marks' => $marks], $strike);
        live::strike($live, $members[3], 1, ['marks' => [$marks[0]]], $strike);
        $live = live::tick($live, 2, true, $strike + 30);
        $view = live::board_view($live, $strike + 30);
        // The sorter undid the lane of their team; of the second, one of two got it: not most of them.
        $done = array_column($view['lanes'], 'done', 'teamid');
        $this->assertTrue($done[(int) $groups[0]->id]);
        $this->assertFalse($done[(int) $groups[1]->id]);
        // Once resolved, the tablets get the solution.
        $this->assertArrayHasKey('solution', json_decode(live::device_view($live, $members[2], $strike + 31)['lane'], true));
    }

    public function test_who_plays_with_no_hurry_does_not_count_against_their_team_if_they_do_not_answer(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $DB->set_field(self::T . 'member', 'adapt', 'notime', ['id' => $members[1]->id]);
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        $strike = self::NOW + live::INTENT + live::PREP + 1;
        // In the first team, one gets it right and the one with no hurry does not get to answer: their team undoes it.
        live::answer($live, $members[0], 1, $this->lane_correct($live, (int) $groups[0]->id), $strike);
        $live = live::tick($live, 2, true, $strike + 30);
        $done = array_column(live::board_view($live, $strike + 30)['lanes'], 'done', 'teamid');
        $this->assertTrue($done[(int) $groups[0]->id]);
        // The second team, with nobody answering and nobody with no hurry, does not.
        $this->assertFalse($done[(int) $groups[1]->id]);
    }

    public function test_the_tower_in_the_dark_loses_the_battle(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        $live = live::start($course->id, 'phones', 6, 30, self::NOW, false, true);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $live = live::next($live, self::NOW);
        // Down to one candle: the team it goes for gets its lane wrong and the last candle goes out.
        $state = json_decode($live->state, true);
        $state['candles'] = 1;
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
        $live = $DB->get_record(self::T . 'live', ['id' => $live->id]);
        $live = $this->play_turn($live, $members, $groups, [false, false], self::NOW);
        $this->assertSame(0, live::board_view($live, self::NOW + 60)['candles']);
        $end = self::NOW + live::INTENT + live::PREP + 31 + live::RESOLVE;
        $live = live::tick($live, 2, true, $end);
        $this->assertSame('lost', json_decode($live->state, true)['outcome']);
        // The Embrollo escaped as it was: the rematch goes on from there, and meanwhile it boasts in its letter.
        $saved = json_decode((string) $DB->get_field(self::T . 'region', 'battlestate', ['courseid' => $course->id]), true);
        $this->assertSame(1.0, (float) $saved['left']);
        $this->assertSame(['piedra'], $saved['hanging']);
        $this->assertNotSame('', today::get($course->id)['gloat']);
        $rematch = live::start($course->id, 'phones', 4, 30, $end + 100, false, true);
        foreach ($members as $member) {
            live::join($rematch, $member, $member->userid, $end + 100);
        }
        $rematch = live::next($rematch, $end + 100);
        $view = live::board_view($rematch, $end + 100);
        $this->assertSame(100, $view['rematch']);
        $this->assertSame($view['maxhp'], $view['hp']);
        // Won, there is nothing left to go on with: the next battle starts afresh.
        $state = json_decode($rematch->state, true);
        $state['hp'] = 0;
        $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $rematch->id]);
        live::finish($DB->get_record(self::T . 'live', ['id' => $rematch->id]), 2, $end + 200);
        $this->assertNull($DB->get_field(self::T . 'region', 'battlestate', ['courseid' => $course->id]));
        $this->assertArrayNotHasKey('gloat', today::get($course->id));
    }

    public function test_a_rematch_leaves_out_the_knots_the_class_broke(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        $DB->set_field(self::T . 'region', 'battlestate', json_encode(['left' => 0.5, 'broken' => ['piedra'],
            'hanging' => ['arena'], 'final' => false]), ['courseid' => $course->id]);
        regions::forget();
        $live = live::start($course->id, 'phones', 4, 30, self::NOW, false, true);
        $knots = json_decode($live->state, true)['knots'];
        $this->assertNotContains('piedra', $knots);
        $this->assertSame('arena', $knots[0]);
        foreach ($members as $member) {
            live::join($live, $member, $member->userid, self::NOW);
        }
        $view = live::board_view(live::next($live, self::NOW), self::NOW);
        $this->assertSame(50, $view['rematch']);
        $this->assertSame((int) round($view['maxhp'] / 2), $view['hp']);
        // The final battle does not go on from a normal one.
        $final = json_decode(live::start($course->id, 'phones', 4, 30, self::NOW + 10, true, true)->state, true);
        $this->assertArrayNotHasKey('rematch', $final);
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
