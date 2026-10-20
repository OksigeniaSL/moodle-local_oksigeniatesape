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
 * Tests for the return of who has been away: the welcome back, the one for who arrives late, and what the teacher sees.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\welcome
 */
#[\PHPUnit\Framework\Attributes\CoversClass(welcome::class)]
final class welcome_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission and students with characters in it.
     *
     * @param int $students
     * @return \stdClass course, mission, users.
     */
    private function make_class(int $students): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->mission = missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $w->users = [];
        for ($i = 0; $i < $students; $i++) {
            $user = $dg->create_and_enrol($w->course, 'student');
            characters::create($user->id, 'sage', 'f');
            engine::member(characters::get($user->id), $w->course->id);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * The member of a student in the course.
     *
     * @param \stdClass $w
     * @param \stdClass $user
     * @return \stdClass
     */
    private function member(\stdClass $w, \stdClass $user): \stdClass {
        return engine::member(characters::get($user->id), $w->course->id);
    }

    /**
     * The first place of the mission.
     *
     * @param \stdClass $w
     * @return \stdClass
     */
    private function first_place(\stdClass $w): \stdClass {
        global $DB;
        $places = $DB->get_records(self::T . 'chapter', ['missionid' => $w->mission->id], 'sortorder', '*', 0, 1);
        return reset($places);
    }

    public function test_who_comes_back_is_welcomed_with_a_short_way(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(2);
        $user = $w->users[0];
        // They came in two weeks ago; a week ago the class lit the lantern of the first place.
        $DB->set_field(self::T . 'member', 'lastseen', time() - 14 * DAYSECS, ['id' => $this->member($w, $user)->id]);
        $chapter = $this->first_place($w);
        $DB->set_field(self::T . 'chapter', 'litat', time() - 7 * DAYSECS, ['id' => $chapter->id]);
        $world = view::world($w->course->id, $user->id);
        $this->assertSame('back', $world['welcome']['kind']);
        $this->assertSame(14, $world['welcome']['days']);
        $this->assertCount(1, $world['welcome']['lanterns']);
        $this->assertCount(1, $world['welcome']['path']);
        // Once: the visit is noted, and the next time there is no welcome.
        $this->assertGreaterThan(time() - 60, (int) $this->member($w, $user)->lastseen);
        $this->assertArrayNotHasKey('welcome', view::world($w->course->id, $user->id));
    }

    public function test_who_arrives_late_is_welcomed_too_and_who_was_here_is_not(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        // Before the class lights anything, a new student just starts.
        $this->assertArrayNotHasKey('welcome', view::world($w->course->id, $w->users[0]->id));
        $chapter = $this->first_place($w);
        $DB->set_field(self::T . 'chapter', 'litat', time() - DAYSECS, ['id' => $chapter->id]);
        // Who came in yesterday is not welcomed.
        $DB->set_field(self::T . 'member', 'lastseen', time() - DAYSECS, ['id' => $this->member($w, $w->users[0])->id]);
        $this->assertArrayNotHasKey('welcome', view::world($w->course->id, $w->users[0]->id));
        // Who arrives with the expedition on its way is.
        $late = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        characters::create($late->id, 'scout', 'm');
        engine::member(characters::get($late->id), $w->course->id);
        $world = view::world($w->course->id, $late->id);
        $this->assertSame('new', $world['welcome']['kind']);
        $this->assertNotEmpty($world['welcome']['path']);
    }

    public function test_the_teacher_sees_who_is_far(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(3);
        $DB->set_field(self::T . 'member', 'lastseen', time() - 10 * DAYSECS, ['id' => $this->member($w, $w->users[0])->id]);
        $DB->set_field(self::T . 'member', 'lastseen', time() - DAYSECS, ['id' => $this->member($w, $w->users[1])->id]);
        $DB->set_field(self::T . 'member', 'remote', 1, ['id' => $this->member($w, $w->users[2])->id]);
        $away = welcome::away($w->course->id, time());
        $far = array_keys($away);
        sort($far);
        $this->assertSame([(int) $w->users[0]->id, (int) $w->users[2]->id], $far);
        $this->assertSame(10, $away[$w->users[0]->id]['days']);
        $this->assertTrue($away[$w->users[2]->id]['remote']);
    }
}
