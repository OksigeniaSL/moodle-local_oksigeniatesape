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
 * Tests for levels, stages and the weekly cap.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\progress
 */
#[\PHPUnit\Framework\Attributes\CoversClass(progress::class)]
final class progress_test extends \basic_testcase {
    public function test_first_levels_come_soon(): void {
        $this->assertSame(0, progress::saber_for_level(1));
        $this->assertSame(20, progress::saber_for_level(2));
        $this->assertSame(50, progress::saber_for_level(3));
        $this->assertSame(90, progress::saber_for_level(4));
        // One scene of the pilot mission (15 to 30 saber) is enough for level 2.
        $this->assertSame(2, progress::level_for_saber(20));
        $this->assertSame(1, progress::level_for_saber(19));
    }

    public function test_levels_and_thresholds_agree(): void {
        for ($level = 1; $level <= progress::MAX_LEVEL; $level++) {
            $saber = progress::saber_for_level($level);
            $this->assertSame($level, progress::level_for_saber($saber), "level $level");
            if ($level > 1) {
                $this->assertSame($level - 1, progress::level_for_saber($saber - 1), "just below level $level");
            }
        }
    }

    public function test_each_level_asks_more(): void {
        $previous = 0;
        for ($level = 2; $level <= progress::MAX_LEVEL; $level++) {
            $step = progress::saber_for_level($level) - progress::saber_for_level($level - 1);
            $this->assertGreaterThan($previous, $step);
            $previous = $step;
        }
    }

    public function test_levels_stop_at_the_top(): void {
        $top = progress::saber_for_level(progress::MAX_LEVEL);
        $this->assertSame(progress::MAX_LEVEL, progress::level_for_saber($top * 10));
        $this->assertSame(1, progress::level_for_saber(-5));
        $this->assertSame(['level' => progress::MAX_LEVEL, 'into' => 100, 'needed' => 0], progress::level_progress($top + 100));
    }

    public function test_level_progress(): void {
        $this->assertSame(['level' => 1, 'into' => 0, 'needed' => 20], progress::level_progress(0));
        $this->assertSame(['level' => 2, 'into' => 15, 'needed' => 30], progress::level_progress(35));
    }

    public function test_stages(): void {
        $this->assertSame(1, progress::stage_for_level(1));
        $this->assertSame(1, progress::stage_for_level(4));
        $this->assertSame(2, progress::stage_for_level(5));
        $this->assertSame(2, progress::stage_for_level(11));
        $this->assertSame(3, progress::stage_for_level(12));
        $this->assertSame(4, progress::stage_for_level(25));
        $this->assertSame(5, progress::stage_for_level(40));
        $this->assertSame(5, progress::stage_for_level(99));
        // The pilot mission (some 450 of saber) shows the second stage.
        $this->assertSame(2, progress::stage_for_level(progress::level_for_saber(450)));
    }

    public function test_weekly_cap(): void {
        $this->assertSame(['granted' => 30, 'capped' => 0], progress::apply_cap(30, 50, 100));
        $this->assertSame(['granted' => 20, 'capped' => 10], progress::apply_cap(30, 80, 100));
        $this->assertSame(['granted' => 0, 'capped' => 30], progress::apply_cap(30, 100, 100));
        $this->assertSame(['granted' => 0, 'capped' => 30], progress::apply_cap(30, 130, 100));
        // No cap, and nothing to cap when taking away (an undo).
        $this->assertSame(['granted' => 30, 'capped' => 0], progress::apply_cap(30, 500, 0));
        $this->assertSame(['granted' => -30, 'capped' => 0], progress::apply_cap(-30, 100, 100));
    }

    public function test_week_starts_on_monday(): void {
        $madrid = new \DateTimeZone('Europe/Madrid');
        $monday = (new \DateTimeImmutable('2026-10-05 00:00', $madrid))->getTimestamp();
        $this->assertSame($monday, progress::week_start($monday, $madrid));
        $wednesday = (new \DateTimeImmutable('2026-10-07 10:30', $madrid))->getTimestamp();
        $this->assertSame($monday, progress::week_start($wednesday, $madrid));
        $sunday = (new \DateTimeImmutable('2026-10-11 23:59', $madrid))->getTimestamp();
        $this->assertSame($monday, progress::week_start($sunday, $madrid));
        // Sunday night in Asunción is already Monday in Madrid: each school counts its own week.
        $asuncion = new \DateTimeZone('America/Asuncion');
        $late = (new \DateTimeImmutable('2026-10-11 22:00', $asuncion))->getTimestamp();
        $there = (new \DateTimeImmutable('2026-10-05 00:00', $asuncion))->getTimestamp();
        $here = (new \DateTimeImmutable('2026-10-12 00:00', $madrid))->getTimestamp();
        $this->assertSame($there, progress::week_start($late, $asuncion));
        $this->assertSame($here, progress::week_start($late, $madrid));
    }
}
