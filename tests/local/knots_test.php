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
 * Tests for the knots of the battle by turns: their data, the lanes each team plays and the knots a class has reached.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\knots
 */
#[\PHPUnit\Framework\Attributes\CoversClass(knots::class)]
final class knots_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_a_knot_has_its_idea_and_lanes_with_their_engines(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $mission = missions::get($course->id);
        $today = array_column(knots::usable($mission), null, 'code');
        $this->assertCount(8, $today);
        $setuga = $today['setuga'];
        $this->assertSame(2, $setuga['chapter']);
        $this->assertSame('SV-03', $setuga['objective']);
        $this->assertNotSame('', $setuga['weakness']);
        $this->assertNotSame('', $setuga['why']);
        // The battle plays its four lanes: to choose, to sort and the mistakes; only to choose, two.
        $this->assertSame(['choice', 'choice', 'classify', 'errors'], array_column($setuga['lanes'], 'engine'));
        $choose = array_column(knots::usable($mission, ['choice']), null, 'code');
        $this->assertSame(['choice', 'choice'], array_column($choose['setuga']['lanes'], 'engine'));
        // A knot without lanes of its own: each of its questions is a lane to choose.
        $this->assertSame(['Q1-1', 'Q1-2', 'Q1-4'], array_column($today['piedra']['lanes'], 'question'));
    }

    public function test_each_team_gets_a_lane_and_two_share_when_there_are_more_teams(): void {
        $knot = ['lanes' => [['engine' => 'choice', 'question' => 'a'], ['engine' => 'choice', 'question' => 'b']]];
        $lanes = knots::lanes($knot, [11, 12, 13]);
        $this->assertSame(['a', 'b', 'a'], array_column($lanes, 'question'));
        $this->assertSame([11, 12, 13], array_keys($lanes));
        // The next turn, they go round.
        $this->assertSame(['b', 'a', 'b'], array_column(knots::lanes($knot, [11, 12, 13], 1), 'question'));
        $this->assertSame([], knots::lanes(['lanes' => []], [11]));
    }

    public function test_the_rules_of_the_battle_come_from_the_mission(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $rules = knots::rules(missions::get($course->id));
        $this->assertSame(5, $rules['candles']);
        $this->assertSame(['short' => 5, 'normal' => 8, 'final' => 10], $rules['sizes']);
        $this->assertSame(['frayed' => 60, 'last' => 25], $rules['phases']);
        // Without anything in the mission, the default ones.
        $bare = (object) ['data' => []];
        $this->assertSame(knots::CANDLES, knots::rules($bare)['candles']);
    }

    public function test_a_battle_has_the_knots_of_the_places_the_class_reached(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        // In the clearing: only the knot of the clearing.
        $this->assertSame(['piedra'], array_values(array_unique(array_column(knots::plan($course->id, 4), 0))));
        // The clearing and the library lit: in the forest, the knots of the first three places.
        foreach (['claro', 'biblioteca'] as $code) {
            $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id, 'code' => $code]);
        }
        $codes = array_values(array_unique(array_column(knots::plan($course->id, 8), 0)));
        $this->assertSame(['piedra', 'arena', 'setuga', 'murcielago', 'arana'], $codes);
        // The final battle: the whole mission.
        $this->assertCount(8, array_unique(array_column(knots::plan($course->id, 8, true), 0)));
    }
}
