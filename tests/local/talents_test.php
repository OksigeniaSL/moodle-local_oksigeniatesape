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
 * Tests for talents: one of two of the role at some levels, chosen once and kept by role; off where the course turns them
 * off; and what they do.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\talents
 */
#[\PHPUnit\Framework\Attributes\CoversClass(talents::class)]
final class talents_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course and a student of a role at a level.
     *
     * @param string $role
     * @param int $level
     * @param \stdClass|null $course The course (null: a new one).
     * @return array [course, user, char]
     */
    private function make_student(string $role, int $level, ?\stdClass $course = null): array {
        global $DB;
        $dg = $this->getDataGenerator();
        if (!$course) {
            $course = $dg->create_course();
            regions::save($course->id, ['enabled' => 1]);
        }
        $user = $dg->create_and_enrol($course, 'student');
        $char = characters::create($user->id, $role, 'f');
        $DB->set_field(self::T . 'char', 'level', $level, ['id' => $char->id]);
        engine::member(characters::get($user->id), $course->id);
        return [$course, $user, characters::get($user->id)];
    }

    public function test_a_level_brings_a_choice_of_two_of_the_role_chosen_once(): void {
        $this->resetAfterTest();
        [$course, $user, $char] = $this->make_student('protector', 2);
        $this->assertNull(talents::pending($char));
        $char->level = 3;
        $this->assertSame(['level' => 3, 'options' => ['longshield', 'teamguard']], talents::pending($char));
        // The student sees the choice waiting, with its cards.
        $this->setUser($user);
        $GLOBALS['DB']->set_field(self::T . 'char', 'level', 3, ['id' => $char->id]);
        $world = view::world($course->id, $user->id);
        $this->assertSame('longshield', $world['character']['talentpending']['options'][0]['code']);
        talents::choose($char, 3, 'teamguard');
        $this->assertSame([3 => 'teamguard'], talents::of($char));
        $this->assertNull(talents::pending($char));
        $this->assertSame('teamguard', view::world($course->id, $user->id)['character']['talents'][0]['code']);
        // Not twice, not one of another role.
        try {
            talents::choose($char, 3, 'longshield');
            $this->fail('Chosen twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('talentchosen', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        talents::choose($char, 3, 'doublecare');
    }

    public function test_off_in_a_course_they_wait_and_each_role_keeps_its_own(): void {
        $this->resetAfterTest();
        [$course, $user, $char] = $this->make_student('healer', 4);
        talents::choose($char, 3, 'doublecare');
        $this->assertTrue(talents::has((int) $char->id, 'doublecare', $course->id));
        regions::save($course->id, ['rules' => ['talents' => 0]]);
        $this->assertFalse(talents::on_in($course->id));
        // Another rule changed later keeps this one.
        regions::save($course->id, ['rules' => ['completion' => 5]]);
        $this->assertFalse(talents::on_in($course->id));
        // Off, they do not act there, and nothing is chosen there; the character keeps them for other courses.
        $this->assertFalse(talents::has((int) $char->id, 'doublecare', $course->id));
        $this->assertTrue(talents::has((int) $char->id, 'doublecare'));
        $this->setUser($user);
        $world = view::world($course->id, $user->id);
        $this->assertTrue($world['character']['talentsoff']);
        $this->assertSame('doublecare', $world['character']['talents'][0]['code']);
        // With another role, its own talents; back to the old one, the old ones.
        characters::change_role(characters::get($user->id), 'sage', true);
        $sage = characters::get($user->id);
        $this->assertSame([], talents::of($sage));
        $this->assertFalse(talents::has((int) $char->id, 'doublecare'));
        $this->assertSame(['househints', 'wideglance'], talents::pending($sage)['options']);
        talents::choose($sage, 3, 'wideglance');
        characters::change_role(characters::get($user->id), 'healer', true);
        $this->assertSame([3 => 'doublecare'], talents::of(characters::get($user->id)));
        // A teacher lets them choose again: those of the role they have now.
        talents::reset(characters::get($user->id));
        $this->assertSame([], talents::of(characters::get($user->id)));
        $this->assertEquals(1, $GLOBALS['DB']->count_records(self::T . 'talent', ['charid' => $char->id]));
    }

    public function test_the_shield_talents_of_the_protector(): void {
        $this->resetAfterTest();
        // «Long shield»: two mistakes stopped in a scene, not one.
        $scene = ['id' => 'T', 'start' => 'k', 'nodes' => [
            'k' => ['type' => 'classify', 'prompt' => '?', 'bins' => [['id' => 'p', 'label' => 'P'], ['id' => 'o', 'label' => 'O']],
                'items' => [['id' => 'i1', 'label' => 'I1', 'bin' => 'p'], ['id' => 'i2', 'label' => 'I2', 'bin' => 'o']],
                'next' => 'fin'],
            'fin' => ['type' => 'ending', 'outcome' => 'success', 'effects' => []],
        ]];
        $events = [['n' => 'k', 'item' => 'i1', 'bin' => 'o'], ['n' => 'k', 'item' => 'i1', 'bin' => 'p'],
            ['n' => 'k', 'item' => 'i2', 'bin' => 'p'], ['n' => 'k', 'item' => 'i2', 'bin' => 'o']];
        $this->assertSame(3, (new scene_runner($scene, [], [], [], 'protector'))->run($events)['points']);
        $this->assertSame(4, (new scene_runner($scene, [], [], [], 'protector'))->shields(2)->run($events)['points']);
        [$course, $user, $protector] = $this->make_student('protector', 3);
        $this->assertSame(1, scenes::shields($protector, $course->id));
        talents::choose($protector, 3, 'longshield');
        $this->assertSame(2, scenes::shields($protector, $course->id));
        // «Team guard»: the first mistake of each one who decides, whoever has the tablet.
        $by = function (array $who) use ($events): array {
            foreach ($events as $i => $event) {
                $events[$i]['by'] = $who[$i];
            }
            return $events;
        };
        $guarded = fn($who) => (new scene_runner($scene, [], [], [], 'sage'))->team_guard([10, 11])->run($by($who))['points'];
        $this->assertSame(4, $guarded([10, 10, 11, 11]));
        $this->assertSame(3, $guarded([10, 10, 10, 10]));
        $this->assertSame(2, (new scene_runner($scene, [], [], [], 'sage'))->run($by([10, 10, 11, 11]))['points']);
        // It comes with a protector who has it, among those who play.
        [, , $sage] = $this->make_student('sage', 1, $course);
        $this->assertFalse(scenes::gift([$sage, $protector], 'teamguard', $course->id));
        talents::reset($protector);
        talents::choose($protector, 3, 'teamguard');
        $this->assertTrue(scenes::gift([$sage, $protector], 'teamguard', $course->id));
    }

    public function test_the_talents_of_the_healer_the_sage_and_the_scout(): void {
        global $DB;
        $this->resetAfterTest();
        // «Hints of the house»: the first two hints free for the sage, then half.
        [$course, $user, $sage] = $this->make_student('sage', 3);
        $pay = new \ReflectionMethod(scenes::class, 'pay_hints');
        $member = engine::member($sage, $course->id);
        $this->assertSame(1, $pay->invoke(null, $sage, $member, 0, 3, time()));
        talents::choose($sage, 3, 'househints');
        $this->assertSame(1, $pay->invoke(null, $sage, engine::member($sage, $course->id), 0, 4, time()));
        $this->assertSame(0, $pay->invoke(null, $sage, engine::member($sage, $course->id), 0, 2, time()));
        // «Light breather»: Breather costs one less, never under one.
        [$course, $user, $healer] = $this->make_student('healer', 3);
        $breather = array_values(array_filter(powers::catalog($course->id), fn($p) => $p->code === 'breather'))[0];
        $this->assertSame(2, powers::cost($breather, $healer));
        talents::choose($healer, 3, 'lightbreather');
        $this->assertSame(1, powers::cost($breather, $healer));
        $one = clone $breather;
        $one->cost = 1;
        $this->assertSame(1, powers::cost($one, $healer));
        // And to a teammate who lacks no energy, it gives breath.
        [, , $mate] = $this->make_student('scout', 1, $course);
        $target = engine::member($mate, $course->id);
        $DB->set_field(self::T . 'member', 'breath', 2, ['id' => $target->id]);
        $act = new \ReflectionMethod(powers::class, 'act');
        $act->invoke(null, $breather, (object) ['id' => 0, 'targetmemberid' => $target->id], engine::member(
            $healer,
            $course->id
        ), time());
        $after = $DB->get_record(self::T . 'member', ['id' => $target->id]);
        $this->assertEquals(3, $after->breath);
        $this->assertEquals(engine::ENERGY_MAX, $after->energy);
        // «Shared trail»: the options of the scout are there in a play as a team with them.
        $runner = new scene_runner(['id' => 'T', 'start' => 'k', 'nodes' => []], [], [], [], 'sage');
        $this->assertFalse($runner->has(['role' => 'scout']));
        $this->assertTrue($runner->lend(['scout'])->has(['role' => 'scout']));
    }
}
