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
 * Tests for the banner of a team: it grows with the weeks the team reaches its goal, never one by one, and from the
 * border its reserve holds two breaths more.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\banners
 */
#[\PHPUnit\Framework\Attributes\CoversClass(banners::class)]
final class banners_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A Monday at noon, far from the edges of the week. */
    private const NOW = 1791806400;

    /**
     * A class with two teams of two students with characters.
     *
     * @return array course, the two groups, the members of each.
     */
    private function make_class(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $groups = [];
        $members = [];
        foreach ([0, 1] as $g) {
            $groups[$g] = $gen->create_group(['courseid' => $course->id]);
            foreach ([0, 1] as $i) {
                $user = $gen->create_and_enrol($course, 'student');
                $gen->create_group_member(['groupid' => $groups[$g]->id, 'userid' => $user->id]);
                $members[$g][] = engine::member(characters::create($user->id, 'scout', 'f'), $course->id);
            }
        }
        return [$course, $groups, $members];
    }

    /**
     * The team reaches its goal in a week: each member gets the gems of the goal.
     *
     * @param array $members
     * @param int $groupid
     * @param int $week Weeks after NOW.
     */
    private function goal(array $members, int $groupid, int $week): void {
        foreach ($members as $member) {
            engine::gems($member, teams::GOAL_GEMS, 'teamgoal', 0, 0, $groupid, self::NOW + $week * WEEKSECS);
        }
    }

    public function test_the_banner_grows_with_the_weeks_the_team_reaches_its_goal(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $groups, $members] = $this->make_class();
        $team = (int) $groups[0]->id;
        $this->assertSame(['stage' => 1, 'name' => 'cloth', 'weeks' => 0, 'next' => 1], banners::view($course->id, $team));
        // A week: the border. The two members got the gems, but it is one week.
        $this->goal($members[0], $team, 0);
        $this->assertSame(['stage' => 2, 'name' => 'border', 'weeks' => 1, 'next' => 1], banners::view($course->id, $team));
        // Two weeks, not in a row: the emblem.
        $this->goal($members[0], $team, 3);
        $this->assertSame(['stage' => 3, 'name' => 'emblem', 'weeks' => 2, 'next' => 2], banners::view($course->id, $team));
        $this->assertSame(1, banners::free_hints($course->id, $team));
        foreach ([4, 5, 6, 7] as $week) {
            $this->goal($members[0], $team, $week);
        }
        $this->assertSame(['stage' => 5, 'name' => 'star', 'weeks' => 6, 'next' => 0], banners::view($course->id, $team));
        // A goal taken back by the teacher does not count.
        $undone = ['teamgoal', self::NOW + 7 * WEEKSECS];
        $DB->set_field_select(self::T . 'ledger', 'undoneby', 1, 'reason = ? AND timecreated = ?', $undone);
        $this->assertSame(4, banners::stage($course->id, $team));
        // The other team, as it was.
        $other = (int) $groups[1]->id;
        $this->assertSame(1, banners::stage($course->id, $other));
        $this->assertSame(0, banners::free_hints($course->id, $other));
    }

    public function test_from_the_border_the_reserve_of_the_team_holds_two_breaths_more(): void {
        $this->resetAfterTest();
        [$course, $groups, $members] = $this->make_class();
        $this->goal($members[0], (int) $groups[0]->id, 0);
        foreach ($groups as $group) {
            reserve::credit($course->id, (int) $group->id, 'all', 20, self::NOW);
        }
        $this->assertSame(reserve::MAX + banners::RESERVE, reserve::amount((int) $groups[0]->id));
        $this->assertSame(reserve::MAX, reserve::amount((int) $groups[1]->id));
        $char = characters::get($this->user_of($members[0][0]));
        $this->assertSame(reserve::MAX + banners::RESERVE, reserve::view($course->id, (int) $groups[0]->id, $char, [])['max']);
    }

    /**
     * The user of a member.
     *
     * @param \stdClass $member
     * @return int
     */
    private function user_of(\stdClass $member): int {
        global $DB;
        return (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $member->charid]);
    }
}
