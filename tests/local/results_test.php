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
 * Tests for the results of the teacher: the knots the class left hanging in the battles of the week.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\results
 */
#[\PHPUnit\Framework\Attributes\CoversClass(results::class)]
final class results_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1790000000;

    public function test_the_knots_left_hanging_this_week_come_first_with_their_idea(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $battle = function (array $state, int $end) use ($DB, $course) {
            $DB->insert_record(self::T . 'live', (object) ['courseid' => $course->id, 'kind' => 'battle',
                'state' => json_encode($state), 'round' => 4, 'timecreated' => $end - 600, 'timemodified' => $end,
                'timeend' => $end]);
        };
        $battle(['knots' => ['piedra', 'setuga'], 'hanging' => ['setuga', 'piedra'], 'broken' => []], self::NOW - DAYSECS);
        $battle(['knots' => ['piedra', 'setuga'], 'hanging' => ['setuga', 'setuga'], 'broken' => ['piedra']], self::NOW - 3600);
        // Two weeks ago, and a battle without knots (the one of questions): neither counts.
        $battle(['knots' => ['arena'], 'hanging' => ['arena'], 'broken' => []], self::NOW - 14 * DAYSECS);
        $battle(['hanging' => ['arena']], self::NOW - 3600);
        $knots = results::knots($course->id, missions::get($course->id), self::NOW);
        $this->assertSame(['setuga', 'piedra'], array_column($knots, 'code'));
        $this->assertSame([2, 1], array_column($knots, 'hanging'));
        $this->assertSame([0, 1], array_column($knots, 'broken'));
        $this->assertNotSame('', $knots[0]['claim']);
        $this->assertNotSame('', $knots[0]['why']);
        // In the results of the mission, they come with the rest.
        $this->assertArrayHasKey('knots', results::mission($course->id));
    }
}
