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
 * The ascent: a rite once in a mission, when the student has lit the six lanterns of its places or their class has won
 * the final battle. The hero chooses to stay in their role or to try another one, keeping their level, without the
 * time a change of role has to wait otherwise. Out of the rite, the teacher changes roles as always.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ascents {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Why the ascent is open for a student now, or null: they have not reached it, or they made it already.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return string|null lanterns (the six lit) or final (the class won the final battle).
     */
    public static function pending(\stdClass $char, int $courseid): ?string {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $done = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'ascent'];
        if ($DB->record_exists(self::T . 'found', $done)) {
            return null;
        }
        $places = count($mission->data['chapters'] ?? []);
        $lit = $DB->count_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'lantern']);
        if ($places && $lit >= $places) {
            return 'lanterns';
        }
        return self::final_won($courseid) ? 'final' : null;
    }

    /**
     * Whether the class has won the final battle of its mission (not a rehearsal).
     *
     * @param int $courseid
     * @return bool
     */
    private static function final_won(int $courseid): bool {
        global $DB;
        $battles = $DB->get_records(self::T . 'live', ['courseid' => $courseid, 'kind' => 'battle'], '', 'id, state');
        foreach ($battles as $live) {
            $state = json_decode((string) $live->state, true) ?: [];
            if (($state['outcome'] ?? '') === 'won' && !empty($state['final']) && empty($state['rehearsal'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * The rite: the hero stays in their role or takes another one, keeping their level. Once in a mission.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param string $role The role chosen: the same, to stay.
     * @param int|null $now
     * @return \stdClass The character.
     */
    public static function ascend(\stdClass $char, int $courseid, string $role, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!self::pending($char, $courseid)) {
            throw new \moodle_exception('noascent', 'local_oksigeniatesape');
        }
        if ($role !== (string) $char->role) {
            $char = characters::change_role($char, $role, true, $now);
        }
        $mission = missions::get($courseid);
        $DB->insert_record(self::T . 'found', ['charid' => $char->id, 'courseid' => $courseid, 'missionid' => $mission->id,
            'kind' => 'ascent', 'code' => $role, 'timecreated' => $now]);
        return $char;
    }
}
