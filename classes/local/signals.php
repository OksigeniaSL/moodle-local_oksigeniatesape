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
 * What the teacher may want to look at, only in their panel: who has tried the same thing several times today without
 * getting it (a scene not ended well, a minigame lost), and whose step may weigh on them or fall short, from their last
 * plays. Hints, never marks: they are worked out when the panel asks, nothing is kept, and the class screen never shows
 * them.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signals {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Tries at the same thing in a day, none of them good, to say that a student is stuck. */
    public const STUCK = 3;

    /** @var int The last plays of a student that say how their step suits them. */
    public const RECENT = 4;

    /** @var int Of those, the ones not ended well that say their step may weigh on them. */
    public const HARD = 3;

    /**
     * The signals of a course, the students stuck today first.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array Of userid, kind (stuck, hard or easy) and text.
     */
    public static function of_course(int $courseid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        $students = view::students($courseid);
        if (!$students) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
        $chars = $DB->get_records_select(self::T . 'char', "userid $insql", $params, '', 'id, userid');
        if (!$chars) {
            return [];
        }
        [$charsql, $charparams] = $DB->get_in_or_equal(array_keys($chars));
        $members = $DB->get_records_select(self::T . 'member', "charid $charsql AND courseid = ?", array_merge(
            $charparams,
            [$courseid]
        ), '', 'charid, step, status');
        $scenes = scenes::summaries($mission);
        $since = engine::day_start($now, $courseid);
        $name = fn($charid) => fullname($students[$chars[$charid]->userid]);
        $stuck = [];
        $steps = [];
        // The plays of each one: their own, not those a teammate made for them on their device.
        $plays = $DB->get_records_select(
            self::T . 'sceneattempt',
            "charid $charsql AND courseid = ? AND (teamplay = 0 OR teamplay = id)",
            array_merge($charparams, [$courseid]),
            'charid, timefinished DESC, id DESC',
            'id, charid, sceneid, ending, attempt, timefinished'
        );
        $today = [];
        $recent = [];
        foreach ($plays as $play) {
            $scene = $scenes[(int) $play->sceneid] ?? null;
            if (!$scene || !isset($members[$play->charid]) || $members[$play->charid]->status === 'paused') {
                continue;
            }
            $good = in_array((string) $play->ending, $scene['best'], true);
            if ((int) $play->timefinished >= $since) {
                $key = $play->charid . ':' . $play->sceneid;
                $today[$key] = ['charid' => (int) $play->charid, 'title' => $scene['title'],
                    'tries' => ($today[$key]['tries'] ?? 0) + 1, 'good' => ($today[$key]['good'] ?? false) || $good];
            }
            if (count($recent[$play->charid] ?? []) < self::RECENT) {
                $recent[$play->charid][] = ['good' => $good, 'first' => (int) $play->attempt === 1];
            }
        }
        foreach ($today as $t) {
            if ($t['tries'] >= self::STUCK && !$t['good']) {
                $stuck[] = self::signal($chars[$t['charid']]->userid, 'stuck', 'signals_stuck_scene', ['name' => $name($t['charid']),
                    'tries' => $t['tries'], 'title' => $t['title']]);
            }
        }
        // The minigames lost again and again today.
        $games = $DB->get_records_select(
            self::T . 'minigame',
            "charid $charsql AND courseid = ? AND timecreated >= ?",
            array_merge($charparams, [$courseid, $since]),
            'id',
            'id, charid, game, success'
        );
        $lost = [];
        foreach ($games as $game) {
            $key = $game->charid . ':' . $game->game;
            $lost[$key] = ['charid' => (int) $game->charid, 'game' => (string) $game->game,
                'tries' => ($lost[$key]['tries'] ?? 0) + 1, 'won' => ($lost[$key]['won'] ?? false) || $game->success];
        }
        foreach ($lost as $l) {
            $member = $members[$l['charid']] ?? null;
            if ($l['tries'] >= self::STUCK && !$l['won'] && $member && $member->status !== 'paused') {
                $title = missions::text($mission->data['games'][$l['game']]['title'] ?? $l['game'], $mission->skin);
                $stuck[] = self::signal($chars[$l['charid']]->userid, 'stuck', 'signals_stuck_game', ['name' => $name($l['charid']),
                    'tries' => $l['tries'], 'title' => $title]);
            }
        }
        // How the step suits each one, from their last plays: most of them not ended well, or all of them well at once.
        $stuckids = array_column($stuck, 'userid');
        foreach ($recent as $charid => $last) {
            $step = (int) $members[$charid]->step;
            // Whoever is stuck today is said once: the step can wait.
            if (count($last) < self::RECENT || in_array((int) $chars[$charid]->userid, $stuckids, true)) {
                continue;
            }
            $notgood = count(array_filter($last, fn($p) => !$p['good']));
            $a = ['name' => $name($charid), 'recent' => self::RECENT, 'notgood' => $notgood,
                'step' => get_string('teach_step_' . max(1, min(4, $step)), 'local_oksigeniatesape')];
            if ($notgood >= self::HARD && $step > 1) {
                $steps[] = self::signal($chars[$charid]->userid, 'hard', 'signals_hard', $a);
            } else if (!$notgood && $step < 4 && count(array_filter($last, fn($p) => $p['first'])) === self::RECENT) {
                $steps[] = self::signal($chars[$charid]->userid, 'easy', 'signals_easy', $a);
            }
        }
        return array_merge($stuck, $steps);
    }

    /**
     * A signal, with its text.
     *
     * @param int|string $userid
     * @param string $kind
     * @param string $string
     * @param array $a
     * @return array
     */
    private static function signal($userid, string $kind, string $string, array $a): array {
        return ['userid' => (int) $userid, 'kind' => $kind, 'text' => get_string($string, 'local_oksigeniatesape', (object) $a)];
    }
}
