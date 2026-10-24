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
 * The class screen as a game board: the fog of the Embrollo over the places the class has forgotten, and the token of
 * each team in the place where it works. The fog covers a place the class has opened when, in the last try of each
 * student at each of its scenes, less than this share was right; it goes away by itself when the class gets it right
 * again (playing its scenes again, for instance). With the fog over three places or more, the Embrollo grows: a battle by
 * turns has a knot more. Nothing is kept: it is read from the tries.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class fog {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var float Below this share right, a place is in the fog. */
    public const SHARE = 0.6;

    /** @var int Answers a place needs before the fog can cover it. */
    public const ENOUGH = 5;

    /** @var int Places in the fog from which the Embrollo grows. */
    public const GROWS = 3;

    /**
     * The places of the mission of a course in the fog: those opened (lit, or the place of today) where the last tries of
     * the class got less than SHARE right.
     *
     * @param int $courseid
     * @return string[] Codes of the places.
     */
    public static function places(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        $now = pacts::chapter($mission);
        $rows = $DB->get_records_sql(
            'SELECT a.id, a.charid, a.sceneid, a.objectives, s.chapterid
               FROM {' . self::T . 'sceneattempt} a
               JOIN {' . self::T . 'scene} s ON s.id = a.sceneid
               JOIN {' . self::T . 'member} m ON m.charid = a.charid AND m.courseid = a.courseid
              WHERE a.courseid = ? AND s.missionid = ? AND m.leftat = 0
           ORDER BY a.id',
            [$courseid, $mission->id]
        );
        // The last try of each student at each scene.
        $last = [];
        foreach ($rows as $row) {
            $last[$row->charid . ':' . $row->sceneid] = $row;
        }
        $sums = [];
        foreach ($last as $row) {
            foreach (json_decode((string) $row->objectives, true) ?: [] as $o) {
                $sums[$row->chapterid]['ok'] = ($sums[$row->chapterid]['ok'] ?? 0) + (int) ($o['ok'] ?? 0);
                $sums[$row->chapterid]['total'] = ($sums[$row->chapterid]['total'] ?? 0) + (int) ($o['total'] ?? 0);
            }
        }
        $out = [];
        foreach ($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder') as $chapter) {
            $open = $chapter->litat || ($now && (int) $now->id === (int) $chapter->id);
            $sum = $sums[$chapter->id] ?? ['ok' => 0, 'total' => 0];
            if ($open && $sum['total'] >= self::ENOUGH && $sum['ok'] < self::SHARE * $sum['total']) {
                $out[] = (string) $chapter->code;
            }
        }
        return $out;
    }

    /**
     * The token of each team on the board: the place where most of its members played last.
     *
     * @param int $courseid
     * @return array Each: teamid, team (name), place (code).
     */
    public static function tokens(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        $codes = $DB->get_records_menu(self::T . 'chapter', ['missionid' => $mission->id], '', 'id, code');
        $grouping = (int) (regions::get($courseid)->groupingid ?? 0);
        $out = [];
        foreach (groups_get_all_groups(games::course($courseid), 0, $grouping) as $group) {
            $userids = array_map(fn($m) => (int) $m->userid, teams::members($courseid, (int) $group->id));
            $at = array_count_values(view::team_places($courseid, (int) $mission->id, $userids));
            if (!$at) {
                continue;
            }
            arsort($at);
            $out[] = ['teamid' => (int) $group->id, 'team' => format_string($group->name),
                'place' => (string) ($codes[array_key_first($at)] ?? '')];
        }
        return $out;
    }
}
