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
 * The banner of a team grows with the weeks the team reaches its goal: cloth, border, emblem, ribbons and star. Each
 * stage is seen on the class screen and in the tablets, and the first two that come after the cloth give the team a
 * small help: with the border, its reserve holds two breaths more; with the emblem, the first hint of each scene it
 * plays together is free. It grows as a team, never one by one, and it never goes down. The lantern the team buys in
 * the market hangs from it, whatever its stage.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class banners {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int[] Weeks with the goal reached for each stage: cloth, border, emblem, ribbons, star. */
    public const WEEKS = [0, 1, 2, 4, 6];

    /** @var string[] The stages, by name. */
    public const STAGES = ['cloth', 'border', 'emblem', 'ribbons', 'star'];

    /** @var int Breaths more the reserve of the team holds from the border on. */
    public const RESERVE = 2;

    /**
     * The weeks a team has reached its goal.
     *
     * @param int $courseid
     * @param int $groupid
     * @return int
     */
    public static function weeks(int $courseid, int $groupid): int {
        global $DB;
        $times = $DB->get_fieldset_select(
            self::T . 'ledger',
            'timecreated',
            "courseid = ? AND reason = 'teamgoal' AND refid = ? AND undoneby = 0",
            [$courseid, $groupid]
        );
        return count(array_unique(array_map(fn($time) => engine::week_start((int) $time, $courseid), $times)));
    }

    /**
     * The stage of the banner of a team: 1 (cloth) to 5 (star).
     *
     * @param int $courseid
     * @param int $groupid
     * @return int
     */
    public static function stage(int $courseid, int $groupid): int {
        $weeks = self::weeks($courseid, $groupid);
        return count(array_filter(self::WEEKS, fn($needed) => $weeks >= $needed));
    }

    /**
     * How many breaths the reserve of a team holds.
     *
     * @param int $courseid
     * @param int $groupid
     * @return int
     */
    public static function reserve_max(int $courseid, int $groupid): int {
        return reserve::MAX + (self::stage($courseid, $groupid) >= 2 ? self::RESERVE : 0);
    }

    /**
     * Hints free in each scene a team plays together: one, from the emblem on.
     *
     * @param int $courseid
     * @param int $groupid
     * @return int
     */
    public static function free_hints(int $courseid, int $groupid): int {
        return self::stage($courseid, $groupid) >= 3 ? 1 : 0;
    }

    /**
     * The banner as the tablets and the class screen show it.
     *
     * @param int $courseid
     * @param int $groupid
     * @return array stage (1 to 5), name (its code), weeks, next (weeks still needed for the next stage; 0 at the star).
     */
    public static function view(int $courseid, int $groupid): array {
        $weeks = self::weeks($courseid, $groupid);
        $stage = count(array_filter(self::WEEKS, fn($needed) => $weeks >= $needed));
        return ['stage' => $stage, 'name' => self::STAGES[$stage - 1], 'weeks' => $weeks,
            'next' => $stage < count(self::WEEKS) ? self::WEEKS[$stage] - $weeks : 0];
    }
}
