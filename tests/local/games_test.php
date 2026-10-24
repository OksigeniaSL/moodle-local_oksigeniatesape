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
 * Tests for more than one game in a course: 5th A in the game of the course and 5th B in a game more, each with its
 * students, its world and its teams; the activities of the course, shared.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\games
 */
#[\PHPUnit\Framework\Attributes\CoversClass(games::class)]
final class games_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with two classes: two students in the group 5th A and two in 5th B, and a teacher.
     *
     * @return array course, a (students of A), b (students of B), groupb, teacher.
     */
    private function two_classes(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $groupa = $gen->create_group(['courseid' => $course->id, 'name' => '5.º A']);
        $groupb = $gen->create_group(['courseid' => $course->id, 'name' => '5.º B']);
        $a = $b = [];
        for ($i = 0; $i < 2; $i++) {
            $a[] = $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $user->id]);
            $b[] = $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $groupb->id, 'userid' => $user->id]);
        }
        return [$course, $a, $b, $groupb, $teacher];
    }

    public function test_each_class_plays_its_own_game(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $a, $b, $groupb, $teacher] = $this->two_classes();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es', null, true);
        $sections = $DB->count_records('course_sections', ['course' => $c]);
        // A game more for 5th B: its students go there; the rest stay in the game of the course.
        $this->setUser($teacher);
        $key = games::create($c, (int) $groupb->id);
        $this->assertLessThan(0, $key);
        $this->assertSame($c, games::course($key));
        $this->assertSame([$c, $key], games::keys($c));
        $this->assertSame($key, games::key($c, (int) $b[0]->id));
        $this->assertSame($c, games::key($c, (int) $a[0]->id));
        $this->assertEqualsCanonicalizing(array_column($a, 'id'), array_keys(view::students($c)));
        $this->assertEqualsCanonicalizing(array_column($b, 'id'), array_keys(view::students($key)));
        $this->assertStringEndsWith(' · 5.º B', games::name($key));
        // The same group cannot have two; groups free for one more: 5th A only.
        try {
            games::create($c, (int) $groupb->id);
            $this->fail('A group with two games');
        } catch (\moodle_exception $e) {
            $this->assertSame('gamegroupused', $e->errorcode);
        }
        // The teams of the game of the course were all its groups: they stay, in a grouping of their own; no group is
        // left free for another game.
        $grouping = (int) regions::get($c)->groupingid;
        $this->assertNotSame(0, $grouping);
        $this->assertSame(['5.º A'], array_column(groups_get_all_groups($c, 0, $grouping, 'g.id, g.name'), 'name'));
        $this->assertSame([], games::free_groups($c));
        // Into a game: who teaches, always; a student, only into their own.
        $this->assertTrue(games::can_enter($key, (int) $teacher->id));
        $this->assertTrue(games::can_enter($key, (int) $b[0]->id));
        $this->assertFalse(games::can_enter($key, (int) $a[0]->id));
        $this->assertFalse(games::can_enter($c, (int) $b[0]->id));
        // Its own world: its region and its mission; the activities of the course, taken as they are.
        regions::save($key, ['enabled' => 1]);
        $mission = missions::install($key, 'livingthings', 'valley', 'es', null, true);
        $this->assertSame($key, (int) $mission->courseid);
        $this->assertSame($sections, $DB->count_records('course_sections', ['course' => $c]));
        $theirs = $DB->get_records_menu(self::T . 'chapter', ['missionid' => missions::get($c)->id], 'code', 'code, sectionid');
        $ours = $DB->get_records_menu(self::T . 'chapter', ['missionid' => $mission->id], 'code', 'code, sectionid');
        $this->assertSame($theirs, $ours);
        $this->assertSame(
            $DB->count_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$c]),
            $DB->count_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$key])
        );
        // Its own teams, of its students, in the course; those of the game of the course do not change.
        $this->setAdminUser();
        $made = teams::make($key, 3);
        $this->assertCount(1, $made);
        $this->assertSame($c, (int) $made[0]->courseid);
        $this->assertEqualsCanonicalizing(array_column($b, 'id'), array_keys(groups_get_members($made[0]->id, 'u.id')));
        $this->assertSame('5.º A', teams::of($c, (int) $a[0]->id)->name);
        // A student of 5th B plays there: their place in the game says so.
        $char = characters::create((int) $b[0]->id, 'scout', 'f');
        $member = engine::member($char, $key);
        $this->assertSame($key, (int) $member->courseid);
        // Through the web services too: into their game, not into the other.
        $this->setUser($b[0]);
        $world = \local_oksigeniatesape\external\get_world::execute($key);
        $this->assertNotEmpty($world);
        try {
            \local_oksigeniatesape\external\get_world::execute($c);
            $this->fail('Into the game of another class');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        // The page of the game: the key and the name of the game.
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url('/local/oksigeniatesape/play.php', ['id' => $key]);
        ob_start();
        page::output('game', $course, [], $key);
        preg_match('/window\.TESAPE_CONFIG = (\{.*?\});<\/script>/s', ob_get_clean(), $m);
        $config = json_decode($m[1], true);
        $this->assertSame([$key, games::name($key)], [$config['courseid'], $config['coursename']]);
        // Closing it: all it had goes, and 5th B goes back to the game of the course.
        $this->setAdminUser();
        games::delete($key);
        $this->assertFalse($DB->record_exists(self::T . 'member', ['courseid' => $key]));
        $this->assertFalse($DB->record_exists(self::T . 'region', ['courseid' => $key]));
        $this->assertFalse($DB->record_exists(self::T . 'mission', ['courseid' => $key]));
        $this->assertSame($c, games::key($c, (int) $b[0]->id));
        $this->assertCount(4, view::students($c));
    }

    public function test_privacy_and_deleting_the_course_see_every_game(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, , $b, $groupb] = $this->two_classes();
        $c = (int) $course->id;
        $key = games::create($c, (int) $groupb->id);
        regions::save($key, ['enabled' => 1]);
        $char = characters::create((int) $b[0]->id, 'healer', 'm');
        engine::member($char, $key);
        // The data of a game more belong to the context of its course.
        $contexts = \local_oksigeniatesape\privacy\provider::get_contexts_for_userid((int) $b[0]->id)->get_contextids();
        $this->assertContainsEquals(\context_course::instance($c)->id, $contexts);
        $users = new \core_privacy\local\request\userlist(\context_course::instance($c), 'local_oksigeniatesape');
        \local_oksigeniatesape\privacy\provider::get_users_in_context($users);
        $this->assertContainsEquals((int) $b[0]->id, $users->get_userids());
        \local_oksigeniatesape\privacy\provider::delete_data_for_all_users_in_context(\context_course::instance($c));
        $this->assertFalse($DB->record_exists(self::T . 'member', ['courseid' => $key]));
        // Deleting the course takes its games more with it.
        engine::member($char, $key);
        delete_course($c, false);
        $this->assertFalse($DB->record_exists(self::T . 'member', ['courseid' => $key]));
        $this->assertFalse($DB->record_exists(self::T . 'region', ['courseid' => $key]));
        $this->assertFalse($DB->record_exists(self::T . 'game', ['courseid' => $c]));
    }
}
