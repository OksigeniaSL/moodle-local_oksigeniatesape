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
 * The team plays a classmate made for a student on their device: they count at once, and the teacher can take one back
 * if the student was not there.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class teamplay {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int The latest ones shown for each student. */
    private const SHOWN = 10;

    /**
     * The team plays made for the students of a course by a teammate, newest first, for their teachers.
     *
     * @param int $courseid
     * @return array userid => list of [id, scene (its title), by (the user who played it), time, decisions (taken in
     *     turn, 0 if the play does not say who took them), decided (how many of them the student took)].
     */
    public static function for_teacher(int $courseid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT a.id, c.userid, a.sceneid, a.timefinished, a.path, pc.userid AS byuserid
               FROM {' . self::T . 'sceneattempt} a
               JOIN {' . self::T . 'char} c ON c.id = a.charid
               JOIN {' . self::T . 'sceneattempt} p ON p.id = a.teamplay
               JOIN {' . self::T . 'char} pc ON pc.id = p.charid
              WHERE a.courseid = ? AND a.teamplay > 0 AND a.teamplay <> a.id
           ORDER BY a.timefinished DESC, a.id DESC',
            [$courseid],
            0,
            500
        );
        $mission = $rows ? missions::get($courseid) : null;
        $titles = $mission ? scenes::summaries($mission) : [];
        $list = [];
        foreach ($rows as $row) {
            $userid = (int) $row->userid;
            if (count($list[$userid] ?? []) >= self::SHOWN) {
                continue;
            }
            // The decisions of the play and how many of them the student took, when the play says who took each.
            $decisions = [];
            foreach (json_decode((string) $row->path, true)['events'] ?? [] as $event) {
                if (isset($event['by'], $event['n'])) {
                    $decisions[$event['n']] = (int) $event['by'];
                }
            }
            $list[$userid][] = ['id' => (int) $row->id, 'scene' => (string) ($titles[$row->sceneid]['title'] ?? ''),
                'by' => (int) $row->byuserid, 'time' => (int) $row->timefinished, 'decisions' => count($decisions),
                'decided' => count(array_filter($decisions, fn($by) => $by === $userid))];
        }
        return $list;
    }

    /**
     * Takes back a team play made for a student by a teammate: the play, its evidence in the competencies, the saber and
     * gems it gave, and the scene done if they have not played it themselves. What else it gave (items, pages, the
     * creature) stays: it was given by the game.
     *
     * @param int $courseid
     * @param int $attemptid The play of the student, not the one of who played it.
     * @param int $actorid The teacher.
     * @param int|null $now
     */
    public static function annul(int $courseid, int $attemptid, int $actorid, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $attempt = $DB->get_record(self::T . 'sceneattempt', ['id' => $attemptid, 'courseid' => $courseid], '*', MUST_EXIST);
        if (!(int) $attempt->teamplay || (int) $attempt->teamplay === (int) $attempt->id) {
            // Only the play a teammate made for them; their own plays are theirs.
            throw new \moodle_exception('notateamplay', 'local_oksigeniatesape');
        }
        $char = $DB->get_record(self::T . 'char', ['id' => $attempt->charid], '*', MUST_EXIST);
        $scene = $DB->get_record(self::T . 'scene', ['id' => $attempt->sceneid], '*', MUST_EXIST);
        $transaction = $DB->start_delegated_transaction();
        $where = "charid = ? AND courseid = ? AND reason = 'scene' AND refid = ? AND timecreated = ? AND undoneby = 0
                  AND resource IN ('saber', 'gems')";
        $params = [$char->id, $courseid, $scene->id, $attempt->timefinished];
        foreach ($DB->get_records_select(self::T . 'ledger', $where, $params, 'id') as $entry) {
            engine::undo((int) $entry->id, $actorid, $now);
        }
        $DB->delete_records(self::T . 'sceneattempt', ['id' => $attempt->id]);
        if (!$DB->record_exists(self::T . 'sceneattempt', ['charid' => $char->id, 'sceneid' => $scene->id])) {
            $DB->delete_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $scene->missionid,
                'kind' => 'scene', 'code' => $scene->code]);
        }
        $transaction->allow_commit();
        competencies::forget_team_evidence($courseid, (int) $char->userid, (int) $attempt->attempt, (int) $attempt->timefinished);
    }
}
