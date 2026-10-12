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
 * Tests for teams: making them with the roles spread, the goal of the week and the ranking.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\teams
 */
#[\PHPUnit\Framework\Attributes\CoversClass(teams::class)]
final class teams_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on and students with characters of the given roles.
     *
     * @param string[] $roles
     * @return \stdClass
     */
    private function make_class(array $roles): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1, 'weeklycap' => 100]);
        $w->users = [];
        foreach ($roles as $role) {
            $user = $dg->create_and_enrol($w->course, 'student');
            characters::create($user->id, $role, 'm');
            engine::member(characters::get($user->id), $w->course->id);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * The character of a user in the course.
     *
     * @param \stdClass $w
     * @param \stdClass $user
     * @return \stdClass
     */
    private function member(\stdClass $w, \stdClass $user): \stdClass {
        return engine::member(characters::get($user->id), $w->course->id);
    }

    public function test_make_spreads_the_roles(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $roles = array_merge(
            array_fill(0, 3, 'protector'),
            array_fill(0, 3, 'healer'),
            array_fill(0, 3, 'sage'),
            array_fill(0, 3, 'scout')
        );
        $w = $this->make_class($roles);
        $groups = teams::make($w->course->id, 4);
        $this->assertCount(3, $groups);
        $region = regions::get($w->course->id);
        $this->assertNotEmpty($region->groupingid);
        foreach ($groups as $group) {
            $members = array_keys(groups_get_members($group->id, 'u.id'));
            $this->assertCount(4, $members);
            $teamroles = array_map(fn($id) => characters::get((int) $id)->role, $members);
            sort($teamroles);
            $this->assertSame(['healer', 'protector', 'sage', 'scout'], $teamroles);
            $this->assertTrue(groups_group_exists($group->id));
            $this->assertNotEmpty(groups_get_all_groups($w->course->id, 0, $region->groupingid)[$group->id] ?? null);
        }
        // Making them again leaves the old ones and uses a new grouping.
        teams::make($w->course->id, 6);
        $this->assertNotEquals($region->groupingid, regions::get($w->course->id)->groupingid);
        $this->assertCount(2, groups_get_all_groups($w->course->id, 0, regions::get($w->course->id)->groupingid));
    }

    public function test_the_team_activities_follow_the_teams(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class(['protector', 'healer', 'sage', 'scout', 'protector', 'healer']);
        // The mission first and the teams after: its team activities use the teams of the game, also made again.
        missions::install($w->course->id, 'livingthings', 'valley', 'es', null, true);
        $sql = 'SELECT id, teamsubmissiongroupingid FROM {assign} WHERE course = ? AND teamsubmission = 1';
        $this->assertNotEmpty($DB->get_records_sql($sql, [$w->course->id]));
        foreach ([3, 6] as $size) {
            teams::make($w->course->id, $size);
            $grouping = (int) regions::get($w->course->id)->groupingid;
            foreach ($DB->get_records_sql($sql, [$w->course->id]) as $assign) {
                $this->assertEquals($grouping, $assign->teamsubmissiongroupingid);
            }
        }
    }

    public function test_make_needs_students(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->expectException(\moodle_exception::class);
        teams::make($course->id);
    }

    public function test_goal_of_the_week(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(['protector', 'healer', 'sage']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $w->course->id]);
        foreach ($w->users as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        // The third is paused: the goal is for the two who play.
        engine::pause($this->member($w, $w->users[2]), 'A break', 0);
        $week = teams::week($w->course->id, $group->id);
        $this->assertSame(2, $week['needed']);
        $this->assertSame(2 * teams::GOAL_PART, $week['goal']);
        $this->assertSame(2, $week['players']);

        engine::saber($this->member($w, $w->users[0]), 60, 'classroom', 0);
        $this->assertFalse(teams::week($w->course->id, $group->id)['reached']);
        $this->assertSame(0, (int) characters::get($w->users[1]->id)->gems);

        engine::saber($this->member($w, $w->users[1]), 40, 'classroom', 0);
        $this->assertTrue(teams::week($w->course->id, $group->id)['reached']);
        $this->assertSame(teams::GOAL_GEMS, (int) characters::get($w->users[0]->id)->gems);
        $this->assertSame(teams::GOAL_GEMS, (int) characters::get($w->users[1]->id)->gems);
        $this->assertSame(0, (int) characters::get($w->users[2]->id)->gems);

        // Once a week.
        engine::saber($this->member($w, $w->users[0]), 10, 'classroom', 0);
        $this->assertSame(teams::GOAL_GEMS, (int) characters::get($w->users[0]->id)->gems);
        $this->assertSame(2, $DB->count_records(self::T . 'ledger', ['reason' => 'teamgoal', 'refid' => $group->id]));

        // Next week, again: each one their part.
        $next = time() + WEEKSECS;
        engine::saber($this->member($w, $w->users[0]), 100, 'classroom', 0, 0, 0, $next);
        $this->assertSame(teams::GOAL_GEMS, (int) characters::get($w->users[0]->id)->gems);
        engine::saber($this->member($w, $w->users[1]), teams::GOAL_PART, 'classroom', 0, 0, 0, $next);
        $this->assertSame(2 * teams::GOAL_GEMS, (int) characters::get($w->users[0]->id)->gems);
    }

    public function test_one_missing_does_not_sink_a_team_of_four(): void {
        $this->resetAfterTest();
        $w = $this->make_class(['protector', 'healer', 'sage', 'scout']);
        $group = $this->getDataGenerator()->create_group(['courseid' => $w->course->id]);
        foreach ($w->users as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        $this->assertSame(3, teams::week($w->course->id, $group->id)['needed']);
        foreach (array_slice($w->users, 0, 3) as $user) {
            engine::saber($this->member($w, $user), teams::GOAL_PART, 'classroom', 0);
        }
        // The fourth was ill all week: the team reaches its goal and everybody gets the gems.
        $this->assertTrue(teams::week($w->course->id, $group->id)['reached']);
        $this->assertSame(teams::GOAL_GEMS, (int) characters::get($w->users[3]->id)->gems);
    }

    public function test_standings(): void {
        $this->resetAfterTest();
        $w = $this->make_class(['protector', 'healer']);
        $dg = $this->getDataGenerator();
        $grouping = $dg->create_grouping(['courseid' => $w->course->id]);
        $owls = $dg->create_group(['courseid' => $w->course->id, 'name' => 'Owls']);
        $foxes = $dg->create_group(['courseid' => $w->course->id, 'name' => 'Foxes']);
        foreach ([$owls, $foxes] as $group) {
            $dg->create_grouping_group(['groupingid' => $grouping->id, 'groupid' => $group->id]);
        }
        $dg->create_group_member(['groupid' => $owls->id, 'userid' => $w->users[0]->id]);
        $dg->create_group_member(['groupid' => $foxes->id, 'userid' => $w->users[1]->id]);
        regions::save($w->course->id, ['groupingid' => $grouping->id]);
        engine::saber($this->member($w, $w->users[1]), 30, 'classroom', 0);
        $standings = teams::standings($w->course->id);
        $this->assertSame(['Foxes', 'Owls'], array_column($standings, 'name'));
        $this->assertSame([30, 0], array_column($standings, 'saber'));
        $this->assertSame($foxes->id, teams::of($w->course->id, $w->users[1]->id)->id);
    }
}
