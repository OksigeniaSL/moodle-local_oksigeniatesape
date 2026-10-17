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
 * Tests for the team plays a classmate made for a student: they count at once, not as their first try, and the teacher
 * can take them back.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\teamplay
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teamplay::class)]
final class teamplay_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_teacher_takes_back_a_team_play_made_for_a_student_who_was_not_there(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $team = $dg->create_group(['courseid' => $course->id]);
        $users = [];
        foreach ([0, 1, 2] as $i) {
            $users[$i] = $dg->create_and_enrol($course, 'student');
            characters::create($users[$i]->id, 'sage', 'f');
            engine::member(characters::get($users[$i]->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $users[$i]->id]);
        }
        // The third one follows the expedition from far: they cannot be at this device.
        $DB->set_field(self::T . 'member', 'remote', 1, ['charid' => characters::get($users[2]->id)->id]);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($users[0]->id), $course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        $result = scenes::play($course->id, $users[0]->id, $scene->id, $events, null, null, [$users[1]->id, $users[2]->id]);
        $this->assertSame([$users[1]->firstname], $result['teamwith']);
        $mate = characters::get($users[1]->id);
        $this->assertEquals(20, $mate->saber);
        // It counted for them, but it is not their first try.
        $this->assertSame([], scenes::first_tries($course->id, (int) $mate->id));
        $this->assertCount(1, scenes::first_tries($course->id, (int) characters::get($users[0]->id)->id));
        // The teacher sees it on their card, made by who played it.
        $list = teamplay::for_teacher($course->id);
        $this->assertSame([(int) $users[1]->id], array_keys($list));
        $this->assertSame((int) $users[0]->id, $list[$users[1]->id][0]['by']);
        // Their own play cannot be taken back this way.
        $own = $DB->get_field(self::T . 'sceneattempt', 'id', ['charid' => characters::get($users[0]->id)->id]);
        try {
            teamplay::annul($course->id, (int) $own, 2);
            $this->fail('Their own play is not a team play made for them');
        } catch (\moodle_exception $e) {
            $this->assertSame('notateamplay', $e->errorcode);
        }
        // Taken back: the play, the saber and the scene done go; who played it keeps theirs.
        teamplay::annul($course->id, (int) $list[$users[1]->id][0]['id'], 2);
        $this->assertEquals(0, characters::get($users[1]->id)->saber);
        $this->assertFalse($DB->record_exists(self::T . 'sceneattempt', ['charid' => $mate->id]));
        $done = ['charid' => $mate->id, 'missionid' => $mission->id, 'kind' => 'scene', 'code' => 'C1-2'];
        $this->assertFalse($DB->record_exists(self::T . 'found', $done));
        $this->assertEquals(20, characters::get($users[0]->id)->saber);
        $this->assertSame([], teamplay::for_teacher($course->id));
        // Played by themselves later, it is their first try.
        [$carried, $roles, $flags, $role] = scenes::context($mate, $course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        scenes::play($course->id, $users[1]->id, $scene->id, $events, null);
        $this->assertCount(1, scenes::first_tries($course->id, (int) $mate->id));
    }

    public function test_the_teacher_sees_how_many_decisions_each_one_took(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $team = $dg->create_group(['courseid' => $course->id]);
        $users = [];
        foreach ([0, 1, 2] as $i) {
            $users[$i] = $dg->create_and_enrol($course, 'student');
            characters::create($users[$i]->id, 'sage', 'f');
            engine::member(characters::get($users[$i]->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $users[$i]->id]);
        }
        // The library: a sorting and then a question, two decisions.
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C2-2']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($users[0]->id), $course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        // The device passes from hand to hand: the first decision is of who opened it, the rest of the second one. The
        // first placement says it was of the third, who was not in the play: it is not kept.
        $nodes = array_values(array_unique(array_column($events, 'n')));
        $this->assertGreaterThanOrEqual(2, count($nodes));
        foreach ($events as $i => $event) {
            $events[$i]['by'] = $event['n'] === $nodes[0] ? (int) $users[0]->id : (int) $users[1]->id;
        }
        $events[0]['by'] = (int) $users[2]->id;
        scenes::play($course->id, $users[0]->id, $scene->id, $events, null, null, [$users[1]->id]);
        $mate = characters::get($users[1]->id);
        $path = json_decode($DB->get_field(self::T . 'sceneattempt', 'path', ['charid' => $mate->id]), true);
        $this->assertArrayNotHasKey('by', $path['events'][0]);
        $play = teamplay::for_teacher($course->id)[$users[1]->id][0];
        // Each decision counts once, for who took it last; the one of the third is not anyone's.
        $took = [];
        foreach (array_slice($events, 1) as $event) {
            $took[$event['n']] = $event['by'];
        }
        $this->assertSame(count($took), $play['decisions']);
        $this->assertSame(count(array_keys($took, (int) $users[1]->id, true)), $play['decided']);
        $this->assertGreaterThan($play['decided'], $play['decisions']);
        $this->assertGreaterThan(0, $play['decided']);
    }
}
