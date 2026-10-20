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
 * The return of who has been away: the student who comes back after many days (ill, travelling, in hospital) and the
 * one who arrives with the expedition already on its way. The game welcomes them with what the class did meanwhile and
 * a short way to catch up: in each place the class has lit and they have not done, the first scene of their step. No one
 * is left behind, and no one has to do it all.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class welcome {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Days away that make a return. */
    public const AWAY_DAYS = 7;

    /** @var int The most scenes of the short way. */
    private const PATH = 4;

    /** @var int Seconds between two notes of when the student came in. */
    private const NOTE = 600;

    /**
     * The welcome of a student who comes back or arrives late, or null if there is nothing to tell.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param \stdClass $member With lastseen as it was before this visit.
     * @param array $world The world of the student, with its mission and team.
     * @param int $now
     * @return array|null kind (back or new), days away, lanterns lit by the class meanwhile, path (scenes to catch up)
     *     and team (the names of the teammates).
     */
    public static function for_student(int $courseid, \stdClass $char, \stdClass $member, array $world, int $now): ?array {
        global $DB;
        $mission = $world['mission'] ?? null;
        $record = missions::get($courseid);
        if (!$mission || !$record) {
            return null;
        }
        $since = (int) ($member->lastseen ?? 0);
        $lit = array_filter($mission['chapters'], fn($c) => $c['lit']);
        $back = $since && $now - $since >= self::AWAY_DAYS * DAYSECS;
        $new = !$since && $lit && !$DB->record_exists(self::T . 'sceneattempt', ['charid' => $char->id, 'courseid' => $courseid]);
        if (!$back && !$new) {
            return null;
        }
        $where = 'missionid = ? AND litat > ?';
        $codes = $DB->get_fieldset_select(self::T . 'chapter', 'code', $where, [$record->id, $new ? 0 : $since]);
        $meanwhile = array_filter($mission['chapters'], fn($c) => in_array($c['code'], $codes, true));
        $lanterns = array_values(array_map(fn($c) => $c['name'], $meanwhile));
        $path = [];
        foreach ($lit as $chapter) {
            if ($chapter['done'] || count($path) >= self::PATH) {
                continue;
            }
            foreach ($chapter['scenes'] as $scene) {
                if (!$scene['done']) {
                    $path[] = ['id' => $scene['id'], 'title' => $scene['title'], 'place' => $chapter['name']];
                    break;
                }
            }
        }
        if ($back && !$lanterns && !$path) {
            // Away, but nothing happened that they missed.
            return null;
        }
        $team = array_values(array_map(fn($m) => $m['name'], array_filter($world['team']['members'] ?? [], fn($m) => !$m['me'])));
        return ['kind' => $new ? 'new' : 'back', 'days' => $new ? 0 : intdiv($now - $since, DAYSECS),
            'lanterns' => $lanterns, 'path' => $path, 'team' => $team];
    }

    /**
     * Notes that the student came into the game now (not more often than every ten minutes).
     *
     * @param \stdClass $member
     * @param int $now
     */
    public static function seen(\stdClass $member, int $now): void {
        global $DB;
        if ($now - (int) ($member->lastseen ?? 0) > self::NOTE) {
            $DB->set_field(self::T . 'member', 'lastseen', $now, ['id' => $member->id]);
        }
    }

    /**
     * The students of a course who have been away a long time, or who follow the expedition from far, for the teacher.
     *
     * @param int $courseid
     * @param int $now
     * @return array userid => [days away (0: not away), remote].
     */
    public static function away(int $courseid, int $now): array {
        global $DB;
        $rows = $DB->get_records_sql(
            'SELECT c.userid, m.lastseen, m.remote
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'char} c ON c.id = m.charid
              WHERE m.courseid = ? AND (m.remote > 0 OR (m.lastseen > 0 AND m.lastseen < ?))',
            [$courseid, $now - self::AWAY_DAYS * DAYSECS]
        );
        $away = [];
        foreach ($rows as $row) {
            $days = $row->lastseen ? intdiv($now - (int) $row->lastseen, DAYSECS) : 0;
            $away[(int) $row->userid] = ['days' => $days >= self::AWAY_DAYS ? $days : 0, 'remote' => (bool) $row->remote];
        }
        return $away;
    }
}
