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
 * Tests for the shop of the naturalist: a curiosity bought with gems and, read with its questions right, the gems back
 * and some more, a seal and a golden card, once.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\curiosities
 */
#[\PHPUnit\Framework\Attributes\CoversClass(curiosities::class)]
final class curiosities_test extends \advanced_testcase {
    public function test_curiosity_does_not_come_dear(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $c);
        engine::gems($member, 10, 'manual', 0);
        $gems = fn() => (int) characters::get($user->id)->gems;
        $start = $gems();
        $list = array_column(curiosities::for_student($c, $char), null, 'code');
        $this->assertSame(['cazadoras', 'luces', 'resistentes'], array_keys($list));
        $this->assertSame([6, 8, false, false], [$list['cazadoras']['price'], $list['cazadoras']['back'],
            $list['cazadoras']['bought'], $list['cazadoras']['done']]);
        // Not bought, not to read.
        try {
            curiosities::read($c, $user->id, 'cazadoras');
            $this->fail('Read without buying');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomarket', $e->errorcode);
        }
        curiosities::buy($c, $user->id, 'cazadoras');
        $this->assertSame($start - 6, $gems());
        foreach (['cazadoras' => 'alreadyowned', 'nada' => 'nomarket'] as $code => $error) {
            try {
                curiosities::buy($c, $user->id, $code);
                $this->fail('Bought ' . $code);
            } catch (\moodle_exception $e) {
                $this->assertSame($error, $e->errorcode);
            }
        }
        // Its pages and questions, each with its answer among its options.
        $read = curiosities::read($c, $user->id, 'cazadoras');
        $this->assertCount(3, $read['pages']);
        $this->assertCount(2, $read['questions']);
        foreach ($read['questions'] as $q) {
            $this->assertContains($q['answer'], $q['options']);
        }
        $this->assertStringContainsString('dionea', $read['fact']);
        // A wrong answer: not ended, nothing given.
        $wrong = array_map(fn($q) => array_values(array_diff($q['options'], [$q['answer']]))[0], $read['questions']);
        $this->assertSame(
            ['right' => false, 'first' => false, 'gems' => 0, 'seals' => 0],
            curiosities::finish($c, $user->id, 'cazadoras', $wrong)
        );
        // All right: the gems back and two more, the seal and the golden card; once.
        $right = array_column($read['questions'], 'answer');
        $this->assertSame(
            ['right' => true, 'first' => true, 'gems' => 8, 'seals' => 1],
            curiosities::finish($c, $user->id, 'cazadoras', $right)
        );
        $this->assertSame($start + 2, $gems());
        $this->assertSame(
            ['right' => true, 'first' => false, 'gems' => 0, 'seals' => 1],
            curiosities::finish($c, $user->id, 'cazadoras', $right)
        );
        $this->assertSame($start + 2, $gems());
        $this->assertTrue(array_column(curiosities::for_student($c, $char), null, 'code')['cazadoras']['done']);
        // In pause, the shop waits.
        engine::pause(engine::member($char, $c), 'Test', 0);
        try {
            curiosities::buy($c, $user->id, 'luces');
            $this->fail('Bought in pause');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomarket', $e->errorcode);
        }
    }

    /**
     * A student of a course reads a curiosity with its questions right.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     */
    private function read_it(int $courseid, int $userid, string $code): void {
        engine::gems(engine::member(characters::get($userid), $courseid), 6, 'manual', 0);
        curiosities::buy($courseid, $userid, $code);
        $read = curiosities::read($courseid, $userid, $code);
        curiosities::finish($courseid, $userid, $code, array_column($read['questions'], 'answer'));
    }

    public function test_the_library_of_the_team_the_fact_and_the_explorer(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        $CFG->enablebadges = 1;
        $CFG->badges_allowcoursebadges = 1;
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $ana = $gen->create_and_enrol($course, 'student');
        $leo = $gen->create_and_enrol($course, 'student');
        $group = $gen->create_group(['courseid' => $c, 'name' => 'Garza']);
        foreach ([[$ana, 'healer'], [$leo, 'scout']] as [$user, $role]) {
            characters::create($user->id, $role, 'f');
            engine::member(characters::get($user->id), $c);
            $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        missions::install($c, 'livingthings', 'valley', 'es');
        // The badge of the explorer, made with the mission, not turned on; the teacher turns it on.
        $mission = missions::get($c);
        $badgeid = badges::find($c, $mission, 'explorer');
        $this->assertNotSame(0, $badgeid);
        (new \core_badges\badge($badgeid))->set_status(BADGE_STATUS_ACTIVE);
        // Two books in the library of the team: not yet the lantern of the banner.
        $this->read_it($c, (int) $ana->id, 'cazadoras');
        $this->read_it($c, (int) $leo->id, 'luces');
        $library = curiosities::library($c, (int) $group->id);
        $this->assertSame([2, false], [$library['books'], $library['banner']]);
        $this->assertNotContains('banner-lantern', market::team_owns($c, (int) $group->id));
        // The third: the lantern is the team's, brought by the library.
        $this->read_it($c, (int) $ana->id, 'resistentes');
        $this->assertTrue(curiosities::library($c, (int) $group->id)['banner']);
        $this->assertContains('banner-lantern', market::team_owns($c, (int) $group->id));
        $banner = array_column(market::for_student(characters::get($ana->id), $c, (int) $ana->id), null, 'code')['banner-lantern'];
        $this->assertSame([true, true], [$banner['owned'], $banner['bylibrary']]);
        // The fact of the explorer, marked by the teacher: breath for the team, once.
        $breath = fn($user) => (int) engine::member(characters::get($user->id), $c)->breath;
        $DB->set_field('local_oksigeniatesape_member', 'breath', 1, ['courseid' => $c]);
        $this->assertFalse(curiosities::for_teacher($c, (int) characters::get($ana->id)->id)[0]['told']);
        $this->assertSame(2, curiosities::told($c, (int) $ana->id, 'cazadoras', (int) $teacher->id));
        $this->assertSame([2, 2], [$breath($ana), $breath($leo)]);
        $this->assertSame(0, curiosities::told($c, (int) $ana->id, 'cazadoras', (int) $teacher->id));
        $this->assertTrue(curiosities::for_teacher($c, (int) characters::get($ana->id)->id)[0]['told']);
        try {
            curiosities::told($c, (int) $leo->id, 'cazadoras', (int) $teacher->id);
            $this->fail('A fact of what they did not read');
        } catch (\moodle_exception $e) {
            $this->assertSame('nomarket', $e->errorcode);
        }
        // Three seals: Ana is an explorer of knowledge; Leo, with one, not yet.
        $badge = new \core_badges\badge($badgeid);
        $this->assertFalse($badge->is_issued((int) $ana->id));
        $this->read_it($c, (int) $ana->id, 'luces');
        $badge = new \core_badges\badge($badgeid);
        $this->assertTrue($badge->is_issued((int) $ana->id));
        $this->assertFalse($badge->is_issued((int) $leo->id));
    }
}
