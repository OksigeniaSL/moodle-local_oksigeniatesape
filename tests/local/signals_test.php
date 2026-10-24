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
 * Tests for what the teacher may want to look at: who is stuck today, and whose step may weigh or fall short.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\signals
 */
#[\PHPUnit\Framework\Attributes\CoversClass(signals::class)]
final class signals_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A play of a scene, put straight into the table.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param int $sceneid
     * @param string $ending
     * @param int $attempt
     * @param int $time
     */
    private function play(\stdClass $char, int $courseid, int $sceneid, string $ending, int $attempt, int $time): void {
        global $DB;
        $DB->insert_record(self::T . 'sceneattempt', (object) ['sceneid' => $sceneid, 'charid' => $char->id,
            'courseid' => $courseid, 'teamplay' => 0, 'path' => '{}', 'score' => 0, 'objectives' => '{}',
            'ending' => $ending, 'attempt' => $attempt, 'timestarted' => $time, 'timefinished' => $time]);
    }

    public function test_who_to_look_at(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::get(missions::install($course->id, 'livingthings', 'valley', 'es')->courseid);
        $scenes = array_filter(scenes::summaries($mission), fn($s) => $s['best']);
        $ids = array_keys($scenes);
        $best = fn($id) => $scenes[$id]['best'][0];
        // Midday: the plays of a few minutes ago are today at any hour the test runs.
        $now = engine::day_start(time(), (int) $course->id) + 12 * HOURSECS;
        $students = [];
        foreach (['stuck', 'hard', 'easy', 'fine', 'paused'] as $who) {
            $user = $dg->create_and_enrol($course, 'student', ['firstname' => ucfirst($who)]);
            $char = characters::create($user->id, 'scout', 'f');
            engine::member($char, $course->id);
            $students[$who] = (object) ['user' => $user, 'char' => $char];
        }
        // Three tries today at the same scene, none ended well; and a minigame lost three times.
        foreach ([1, 2, 3] as $n) {
            $this->play($students['stuck']->char, $course->id, $ids[0], 'nope', $n, $now - 100 * $n);
            $DB->insert_record(self::T . 'minigame', (object) ['charid' => $students['stuck']->char->id, 'courseid' => $course->id,
                'cmid' => 0, 'game' => 'nestdefence', 'success' => 0, 'tries' => $n, 'detail' => '{}', 'timecreated' => $now - 50 * $n]);
        }
        // The last four plays, three of them not ended well; and four well at the first try; and a mixed one.
        foreach ([0, 1, 2, 3] as $n) {
            $this->play($students['hard']->char, $course->id, $ids[$n], $n ? 'nope' : $best($ids[$n]), 1, $now - DAYSECS - $n);
            $this->play($students['easy']->char, $course->id, $ids[$n], $best($ids[$n]), 1, $now - DAYSECS - $n);
            $this->play($students['fine']->char, $course->id, $ids[$n], $n % 2 ? 'nope' : $best($ids[$n]), 1, $now - DAYSECS - $n);
            $this->play($students['paused']->char, $course->id, $ids[$n], 'nope', 1, $now - DAYSECS - $n);
        }
        $DB->set_field(self::T . 'member', 'status', 'paused', ['charid' => $students['paused']->char->id]);
        $signals = signals::of_course($course->id, $now);
        $kinds = [];
        foreach ($signals as $s) {
            $kinds[$s['userid']][] = $s['kind'];
        }
        // The stuck ones first: the scene and the minigame; then the steps.
        $this->assertSame(['stuck', 'stuck'], $kinds[$students['stuck']->user->id]);
        $this->assertSame('stuck', $signals[0]['kind']);
        $this->assertStringContainsString('3', $signals[0]['text']);
        $this->assertSame(['hard'], $kinds[$students['hard']->user->id]);
        $this->assertSame(['easy'], $kinds[$students['easy']->user->id]);
        $this->assertArrayNotHasKey($students['fine']->user->id, $kinds);
        $this->assertArrayNotHasKey($students['paused']->user->id, $kinds);
        // At the first step, a step that weighs is not said: there is none below.
        $DB->set_field(self::T . 'member', 'step', 1, ['charid' => $students['hard']->char->id]);
        $kinds = array_column(array_filter(
            signals::of_course($course->id, $now),
            fn($s) => $s['userid'] === (int) $students['hard']->user->id
        ), 'kind');
        $this->assertSame([], $kinds);
        // Tomorrow, nobody is stuck any more.
        $tomorrow = array_column(signals::of_course($course->id, $now + 2 * DAYSECS), 'kind');
        $this->assertNotContains('stuck', $tomorrow);
    }
}
