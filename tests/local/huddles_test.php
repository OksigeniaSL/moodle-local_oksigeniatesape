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
 * Tests for team sessions: the guide opens one, the team joins by asking, the hand decides on its tablet and the guide
 * takes it; who drops out stops counting and comes back; a session without signs of its guide is gone.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\huddles
 */
#[\PHPUnit\Framework\Attributes\CoversClass(huddles::class)]
final class huddles_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1800000000;

    /**
     * A course with the mission and a team of three, and the id of a scene.
     *
     * @return array [course, users, group, sceneid]
     */
    private function make_team(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $group = $gen->create_group(['courseid' => $course->id]);
        $users = [];
        foreach (['sage', 'scout', 'healer'] as $role) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
            engine::member(characters::create($user->id, $role, 'f'), $course->id, self::NOW);
            $users[] = $user;
        }
        $sceneid = (int) $DB->get_field(self::T . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C3-2']);
        return [$course, $users, $group, $sceneid];
    }

    public function test_the_team_joins_and_the_hand_decides_on_its_own_tablet(): void {
        $this->resetAfterTest();
        [$course, $users, $group, $sceneid] = $this->make_team();
        [$ana, $leo, $eva] = $users;
        $huddle = huddles::open($course->id, $ana->id, $sceneid, self::NOW);
        // The others see it, and join with a touch: no code.
        $view = huddles::mine($course->id, $leo->id, self::NOW + 2);
        $this->assertSame((int) $huddle->id, $view['id']);
        $this->assertFalse($view['guide']);
        $this->assertFalse($view['joined']);
        $this->assertNotSame('', $view['scene']);
        $this->assertCount(1, $view['members']);
        $this->assertTrue(huddles::join($course->id, $leo->id, self::NOW + 2)['joined']);
        // Opening it again from another tablet of the team joins the same one.
        $this->assertSame((int) $huddle->id, (int) huddles::open($course->id, $eva->id, $sceneid, self::NOW + 3)->id);
        $mine = huddles::mine($course->id, $ana->id, self::NOW + 4);
        $this->assertTrue($mine['guide']);
        $this->assertCount(3, $mine['members']);
        $this->assertSame((int) $ana->id, $mine['members'][0]['userid']);
        // The guide starts with those who are in; they are vouched for in the play.
        [$session, $char] = huddles::get($course->id, $ana->id, (int) $huddle->id, self::NOW + 5);
        $this->assertSame([(int) $ana->id, (int) $leo->id, (int) $eva->id], huddles::start($session, $char, self::NOW + 5));
        $this->assertSame([(int) $ana->id, (int) $leo->id, (int) $eva->id], huddles::players(
            $course->id,
            $char,
            self::NOW + 6
        ));
        // A decision to choose, with the hand on Leo.
        $seq = huddles::publish($session, $char, ['node' => 'n1', 'kind' => 'choose', 'prompt' => '¿Cuál?',
            'options' => [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']], 'hand' => (int) $leo->id], self::NOW + 6);
        $view = huddles::mine($course->id, $leo->id, self::NOW + 7);
        $this->assertTrue($view['myturn']);
        $this->assertSame(['a', 'b'], array_column($view['options'], 'id'));
        $this->assertArrayNotHasKey('pending', $view);
        // Not someone else, nor a decision already gone, nor an option that is not there.
        foreach ([[$eva, 'a', $seq], [$leo, 'a', $seq - 1], [$leo, 'z', $seq]] as [$who, $option, $at]) {
            [$s, $c] = huddles::get($course->id, $who->id, (int) $huddle->id, self::NOW + 8);
            try {
                huddles::decide($s, $c, $option, $at, self::NOW + 8);
                $this->fail('A choice that is not theirs');
            } catch (\moodle_exception $e) {
                $this->assertSame('huddlenotyours', $e->errorcode);
            }
        }
        [$s, $c] = huddles::get($course->id, $leo->id, (int) $huddle->id, self::NOW + 9);
        huddles::decide($s, $c, 'b', $seq, self::NOW + 9);
        // The guide sees it to play it; the rest, that it waits.
        $this->assertSame(
            ['by' => (int) $leo->id, 'option' => 'b', 'seq' => $seq],
            huddles::mine($course->id, $ana->id, self::NOW + 10)['pending']
        );
        $this->assertTrue(huddles::mine($course->id, $eva->id, self::NOW + 10)['waiting']);
        $this->assertArrayNotHasKey('pending', huddles::mine($course->id, $eva->id, self::NOW + 10));
        // The next decision clears it.
        [$session, $char] = huddles::get($course->id, $ana->id, (int) $huddle->id, self::NOW + 11);
        huddles::publish(
            $session,
            $char,
            ['node' => 'n2', 'kind' => 'classify', 'prompt' => 'Clasifica', 'hand' => 0],
            self::NOW + 11
        );
        $this->assertArrayNotHasKey('pending', huddles::mine($course->id, $ana->id, self::NOW + 12));
        // The class screen sees the team with a session.
        $this->assertSame([(int) $group->id => (int) $huddle->id], huddles::open_teams($course->id, self::NOW + 12));
        // Who follows from far counts in the play only if their own tablet was in.
        $GLOBALS['DB']->set_field(self::T . 'member', 'remote', 1, ['courseid' => $course->id,
            'charid' => characters::get($eva->id)->id]);
        $mates = new \ReflectionMethod(scenes::class, 'mates');
        $this->assertCount(0, $mates->invoke(null, $course->id, (int) $ana->id, [(int) $eva->id], self::NOW + 12));
        $this->assertCount(1, $mates->invoke(
            null,
            $course->id,
            (int) $ana->id,
            [(int) $eva->id],
            self::NOW + 12,
            [(int) $eva->id]
        ));
    }

    public function test_who_drops_out_stops_counting_and_a_session_without_its_guide_is_gone(): void {
        $this->resetAfterTest();
        [$course, $users, , $sceneid] = $this->make_team();
        [$ana, $leo] = $users;
        $huddle = huddles::open($course->id, $ana->id, $sceneid, self::NOW);
        huddles::join($course->id, $leo->id, self::NOW);
        // Leo stops giving signs: after a while he is not in.
        $later = self::NOW + huddles::PRESENT + 5;
        $this->assertCount(1, huddles::mine($course->id, $ana->id, $later)['members']);
        // He comes back where the team is.
        $this->assertCount(2, huddles::mine($course->id, $leo->id, $later + 1)['members']);
        // The guide stops: after two minutes the session is gone for everybody.
        $gone = $later + huddles::ALIVE + 5;
        $this->assertNull(huddles::mine($course->id, $leo->id, $gone));
        try {
            huddles::get($course->id, $leo->id, (int) $huddle->id, $gone);
            $this->fail('A session gone');
        } catch (\moodle_exception $e) {
            $this->assertSame('huddlegone', $e->errorcode);
        }
        // A new one replaces the old one of the team; and a scene for one cannot be played so.
        $new = huddles::open($course->id, $leo->id, $sceneid, $gone);
        $this->assertEquals(1, $GLOBALS['DB']->count_records(self::T . 'huddle', ['courseid' => $course->id]));
        huddles::close($new, $gone + 1);
        $this->assertNull(huddles::mine($course->id, $leo->id, $gone + 2));
        $scene = $GLOBALS['DB']->get_record(self::T . 'scene', ['id' => $sceneid]);
        $data = json_decode($scene->definition, true);
        $data['mode'] = 'solo';
        $GLOBALS['DB']->set_field(self::T . 'scene', 'definition', json_encode($data), ['id' => $sceneid]);
        $this->expectException(\moodle_exception::class);
        huddles::open($course->id, $ana->id, $sceneid, $gone + 3);
    }
}
