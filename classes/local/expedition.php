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
 * The strength of the expedition: what the class did during the mission weakens the Embrollo before the final battle.
 * The lanterns of the class lit weigh most; the stains cleaned and the pages recovered, and the tasks of the places
 * done by at least half of the class, the rest. The final battle starts with the Embrollo up to 30 % weaker, and up to
 * 20 % more with the strands the class loosened in the last great untangling. The knots
 * of what the class still mixes up in the scenes, unless it broke them in a practice battle since, are known to the
 * Embrollo: it keeps them for the end of the final battle.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class expedition {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var float How much weaker the Embrollo starts the final battle with the whole strength. */
    public const MOST = 0.3;

    /** @var float How much weaker the last great untangling leaves it, with all its strands loose. */
    public const UNTANGLE = 0.2;

    /** @var int Stains cleaned or pages recovered that count fully. */
    public const STAINS = 6;

    /** @var float Below this share of answers right in the scenes, the class still mixes up an objective. */
    public const MIXED = 0.6;

    /** @var int Answers of an objective it takes to tell. */
    public const ANSWERS = 4;

    /** @var int Known knots, at most. */
    public const KNOWN = 3;

    /**
     * The strength of the expedition of a course.
     *
     * @param int $courseid
     * @return array strength (from 0 to 1), weaker (the share of the threads the Embrollo starts without, as a
     *     percentage), lanterns (lit and of how many), stains (cleaned), tasks (done and of how many) and untangled (the
     *     strands loosened in the last great untangling, and of how many).
     */
    public static function strength(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        $view = ['strength' => 0.0, 'weaker' => 0, 'lanterns' => [0, 0], 'stains' => 0, 'tasks' => [0, 0], 'untangled' => [0, 0]];
        if (!$mission) {
            return $view;
        }
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], '', 'id, litat');
        $lit = count(array_filter($chapters, fn($c) => !empty($c->litat)));
        $stains = $DB->count_records_select(
            self::T . 'stain',
            "courseid = ? AND chapter <> '' AND cleanedat > 0",
            [$courseid]
        );
        // A task of a place is done when at least half of those who play have it done.
        $tasks = $DB->get_records_select(
            self::T . 'challenge',
            'courseid = ? AND cmid = 0 AND chapterid > 0',
            [$courseid],
            '',
            'id'
        );
        $players = max(1, $DB->count_records_select(self::T . 'member', "courseid = ? AND status <> 'paused'", [$courseid]));
        $done = 0;
        foreach ($tasks as $task) {
            $count = $DB->count_records_select(
                self::T . 'validation',
                "challengeid = ? AND status IN ('done', 'merit')",
                [$task->id]
            );
            $done += 2 * $count >= $players ? 1 : 0;
        }
        $strength = ($chapters ? 0.6 * $lit / count($chapters) : 0) + 0.2 * min(1, $stains / self::STAINS) +
            ($tasks ? 0.2 * $done / count($tasks) : 0);
        $strength = round(min(1, $strength), 2);
        // The great untangling, on top: each strand the class loosened in the last one.
        [$loose, $strands] = untangles::last($courseid);
        $untangled = $strands ? self::UNTANGLE * $loose / $strands : 0;
        return ['strength' => $strength, 'weaker' => (int) round(100 * (self::MOST * $strength + $untangled)),
            'lanterns' => [$lit, count($chapters)], 'stains' => $stains, 'tasks' => [$done, count($tasks)],
            'untangled' => [$loose, $strands]];
    }

    /**
     * The knots the Embrollo knows: those of the objectives the class still mixes up in the scenes (less than 60 % right,
     * with four answers or more), unless the class broke them in a practice battle after the last time it missed one.
     * The worst first.
     *
     * @param int $courseid
     * @return array List of code and name, three at most.
     */
    public static function known(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        $knots = $mission ? knots::usable($mission) : [];
        if (!$knots) {
            return [];
        }
        $objectives = [];
        $attempts = $DB->get_recordset_select(
            self::T . 'sceneattempt',
            'courseid = ? AND timefinished > 0',
            [$courseid],
            '',
            'id, objectives, timefinished'
        );
        foreach ($attempts as $attempt) {
            foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                $mine = $objectives[$code] ?? ['ok' => 0, 'total' => 0, 'missed' => 0];
                $mine['ok'] += (int) ($o['ok'] ?? 0);
                $mine['total'] += (int) ($o['total'] ?? 0);
                if ((int) ($o['ok'] ?? 0) < (int) ($o['total'] ?? 0)) {
                    $mine['missed'] = max($mine['missed'], (int) $attempt->timefinished);
                }
                $objectives[$code] = $mine;
            }
        }
        $attempts->close();
        $reviewed = json_decode((string) (regions::get($courseid)->reviewed ?? ''), true) ?: [];
        $known = [];
        foreach ($knots as $knot) {
            $o = $objectives[$knot['objective']] ?? null;
            if (!$o || $o['total'] < self::ANSWERS || $o['ok'] >= self::MIXED * $o['total']) {
                continue;
            }
            if ((int) ($reviewed[$knot['code']] ?? 0) >= $o['missed']) {
                continue;
            }
            $known[] = ['code' => $knot['code'], 'name' => missions::text($knot['name'], $mission->skin, $mission->variant),
                'rate' => $o['ok'] / $o['total']];
        }
        usort($known, fn($a, $b) => $a['rate'] <=> $b['rate']);
        return array_map(fn($k) => ['code' => $k['code'], 'name' => $k['name']], array_slice($known, 0, self::KNOWN));
    }

    /**
     * Knots broken in a practice battle: reviewed, the Embrollo stops knowing them until the class misses them again.
     *
     * @param int $courseid
     * @param string[] $codes
     * @param int $now
     */
    public static function reviewed(int $courseid, array $codes, int $now): void {
        global $DB;
        $region = regions::get($courseid);
        if (!$region || !$codes) {
            return;
        }
        $reviewed = json_decode((string) ($region->reviewed ?? ''), true) ?: [];
        foreach ($codes as $code) {
            $reviewed[(string) $code] = $now;
        }
        $DB->set_field(self::T . 'region', 'reviewed', json_encode($reviewed), ['courseid' => $courseid]);
        regions::forget();
    }
}
