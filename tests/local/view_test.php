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
 * Tests for what the world of a student shows of the map: the things of each place on its painting, and where each one
 * of their team is.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\view
 */
#[\PHPUnit\Framework\Attributes\CoversClass(view::class)]
final class view_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_map_shows_the_spots_of_each_place_and_where_the_team_is(): void {
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
            characters::create($users[$i]->id, 'scout', 'm');
            engine::member(characters::get($users[$i]->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $users[$i]->id]);
        }
        // The first one plays the nest of the clearing; the second one has not played yet.
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($users[0]->id), $course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        scenes::play($course->id, $users[0]->id, $scene->id, $events, null);
        $world = view::world($course->id, $users[2]->id);
        $at = array_column($world['team']['members'], 'at', 'userid');
        $this->assertSame((int) $scene->chapterid, $at[$users[0]->id]);
        $this->assertSame(0, $at[$users[1]->id]);
        // The nest is in the twigs at the bottom of the painting of the clearing.
        $clearing = $world['mission']['chapters'][0];
        $spots = array_column($clearing['spots'], null, 'target');
        $this->assertSame(['target' => 'C1-2', 'x' => 50, 'y' => 76], $spots['C1-2']);
    }
}
