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
 * Levels, stages and the weekly cap: the sums behind a character's progress, without touching the database.
 *
 * Going from level n to n + 1 takes FIRST_STEP + STEP_GROWTH * (n - 1) saber, so the first levels come soon (a
 * scene gives 15 to 30) and the later ones ask for weeks. The numbers are a first guess, to be tuned with classes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class progress {
    /** @var int Saber needed to go from level 1 to level 2. */
    public const FIRST_STEP = 20;

    /** @var int How much more each level asks than the one before. */
    public const STEP_GROWTH = 10;

    /** @var int Highest level. */
    public const MAX_LEVEL = 50;

    /** @var int[] Level at which each stage of the character's look starts: the second one comes in the first mission. */
    public const STAGES = [1, 5, 12, 25, 40];

    /**
     * Total saber needed to reach a level.
     *
     * @param int $level From 1 to MAX_LEVEL.
     * @return int
     */
    public static function saber_for_level(int $level): int {
        $level = max(1, min(self::MAX_LEVEL, $level));
        $steps = $level - 1;
        return self::FIRST_STEP * $steps + intdiv(self::STEP_GROWTH * $steps * ($steps - 1), 2);
    }

    /**
     * Level reached with an amount of saber.
     *
     * @param int $saber
     * @return int From 1 to MAX_LEVEL.
     */
    public static function level_for_saber(int $saber): int {
        $level = 1;
        while ($level < self::MAX_LEVEL && $saber >= self::saber_for_level($level + 1)) {
            $level++;
        }
        return $level;
    }

    /**
     * Stage of the character's look, from the table of stages.
     *
     * @param int $level
     * @return int From 1 to 5.
     */
    public static function stage_for_level(int $level): int {
        return count(array_filter(self::STAGES, fn($from) => $level >= $from)) ?: 1;
    }

    /**
     * How far a character has gone towards the next level, for the bar.
     *
     * @param int $saber
     * @return array{level: int, into: int, needed: int} Saber gained in this level and saber the level asks for
     *     (needed is 0 at the highest level).
     */
    public static function level_progress(int $saber): array {
        $level = self::level_for_saber($saber);
        if ($level >= self::MAX_LEVEL) {
            return ['level' => $level, 'into' => $saber - self::saber_for_level($level), 'needed' => 0];
        }
        $from = self::saber_for_level($level);
        return ['level' => $level, 'into' => $saber - $from, 'needed' => self::saber_for_level($level + 1) - $from];
    }

    /**
     * Splits an amount of saber into what counts and what the weekly cap of a course leaves out.
     *
     * @param int $amount Saber about to be given (only gains are capped).
     * @param int $earned Saber already earned in the course this week.
     * @param int $cap Weekly cap of the course; 0 for no cap.
     * @return array{granted: int, capped: int}
     */
    public static function apply_cap(int $amount, int $earned, int $cap): array {
        if ($amount <= 0 || $cap <= 0) {
            return ['granted' => $amount, 'capped' => 0];
        }
        $granted = max(0, min($amount, $cap - $earned));
        return ['granted' => $granted, 'capped' => $amount - $granted];
    }

    /**
     * Start of the week (Monday at midnight) a time falls in.
     *
     * @param int $time
     * @param \DateTimeZone $timezone Time zone of the school.
     * @return int
     */
    public static function week_start(int $time, \DateTimeZone $timezone): int {
        $date = (new \DateTimeImmutable('@' . $time))->setTimezone($timezone);
        return $date->modify('monday this week')->setTime(0, 0)->getTimestamp();
    }
}
