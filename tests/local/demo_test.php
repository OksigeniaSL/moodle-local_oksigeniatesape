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
 * Tests for the made-up class.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\demo
 */
#[\PHPUnit\Framework\Attributes\CoversClass(demo::class)]
final class demo_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_class_with_teams_and_characters(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = demo::create_class('DEMO1', 12, 4);
        $courseid = $class->course->id;
        $this->assertCount(12, $class->students);
        $this->assertCount(3, $class->teams);
        $this->assertSame(13, count(get_enrolled_users(\context_course::instance($courseid))));
        $this->assertTrue($DB->record_exists(self::T . 'region', ['courseid' => $courseid, 'enabled' => 1]));
        $this->assertSame(3, $DB->count_records(self::T . 'team', ['courseid' => $courseid]));
        $this->assertSame(12, $DB->count_records(self::T . 'member', ['courseid' => $courseid]));
        // With the mission of the plugin.
        $this->assertSame('livingthings', missions::get($courseid)->code);
        // The mission brings its Moodle activities as challenges.
        $this->assertSame(5, $DB->count_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$courseid]));
        $this->assertSame(0, powers::pending_count($courseid));
        foreach ($class->teams as $groupid) {
            $members = groups_get_members($groupid, 'u.id');
            $this->assertCount(4, $members);
            // Each team has the four roles.
            [$insql, $params] = $DB->get_in_or_equal(array_keys($members));
            $roles = $DB->get_fieldset_select(self::T . 'char', 'role', "userid $insql", $params);
            sort($roles);
            $this->assertSame(['healer', 'protector', 'sage', 'scout'], $roles);
        }
        // Nobody has made progress yet, and the users are easy to find.
        $this->assertSame(0, (int) $DB->get_field_sql('SELECT SUM(saber) FROM {' . self::T . 'char}'));
        $this->assertStringStartsWith(demo::USERNAME_PREFIX . $courseid, $class->teacher->username);
    }

    public function test_progress_respects_the_weekly_cap(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = demo::create_class('DEMO2', 6, 3, 'animals', 4);
        $courseid = $class->course->id;
        foreach ($class->students as $student) {
            $char = $DB->get_record(self::T . 'char', ['userid' => $student->id]);
            $this->assertNull($char->lineage);
            $sum = (int) $DB->get_field(self::T . 'ledger', 'SUM(amount)', ['charid' => $char->id, 'resource' => 'saber']);
            $this->assertSame($sum, (int) $char->saber);
            // Outside the scenes, within the weekly cap of the site.
            $where = "charid = ? AND resource = 'saber' AND reason <> 'scene'";
            $outside = (int) $DB->get_field_select(self::T . 'ledger', 'SUM(amount)', $where, [$char->id]);
            $this->assertLessThanOrEqual(4 * engine::site_weekly_cap(), $outside);
            $this->assertSame(progress::level_for_saber($sum), (int) $char->level);
            $renown = $DB->get_field(self::T . 'member', 'renown', ['charid' => $char->id, 'courseid' => $courseid]);
            $this->assertSame($sum, (int) $renown);
        }
        // A class in the middle of the game: a cheap power that went through by itself, one waiting, ideas in the box.
        $this->assertSame(1, powers::pending_count($courseid));
        $this->assertCount(1, powers::requests($courseid)['decided']);
        $this->assertCount(2, ideas::list($courseid));
    }

    public function test_same_seed_same_class(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $names = fn($class) => array_map(fn($u) => $u->firstname, $class->students);
        $first = demo::create_class('DEMO3', 8, 4, 'storyheroes', 0, 7);
        $second = demo::create_class('DEMO4', 8, 4, 'storyheroes', 0, 7);
        $this->assertSame($names($first), $names($second));
    }

    public function test_an_account_joins_the_class(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = demo::create_class('DEMO4', 8, 4);
        $courseid = $class->course->id;
        $visitor = $this->getDataGenerator()->create_and_enrol($class->course, 'student', ['username' => 'alumno']);
        $joined = demo::join_class($courseid, 'alumno', 2, 'f');
        // In the smallest team, with the role it lacked, and some progress like the rest.
        $this->assertContains($joined->team, array_map('intval', $class->teams));
        $this->assertTrue(groups_is_member($joined->team, $visitor->id));
        $char = characters::get((int) $visitor->id);
        $this->assertSame('f', $char->nameform);
        $this->assertGreaterThan(0, (int) $char->saber);
        $this->assertTrue($DB->record_exists(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]));
        // Twice does nothing twice.
        demo::join_class($courseid, 'alumno', 2, 'f');
        $this->assertSame(1, $DB->count_records(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]));
        $this->assertSame(1, $DB->count_records(self::T . 'char', ['userid' => $visitor->id]));
        // An account outside the course cannot join.
        $this->getDataGenerator()->create_user(['username' => 'fuera']);
        $this->expectException(\invalid_parameter_exception::class);
        demo::join_class($courseid, 'fuera');
    }

    public function test_taken_shortname(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->getDataGenerator()->create_course(['shortname' => 'TAKEN']);
        $this->expectException(\moodle_exception::class);
        demo::create_class('TAKEN');
    }
}
