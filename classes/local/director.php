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
 * The teacher as the director of the game: «What do they do?». When a team or the class does something the story can
 * remember, the teacher writes it in a line and gives one of the marks of the mission (the heron is a friend of the
 * class): each one who plays gets it, with a gem that tells them why, and the scenes and the memory of the places read it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class director {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Gems that tell each one who gets a mark why. */
    public const GEMS = 1;

    /**
     * The marks the mission of a course lets the teacher give, in its story.
     *
     * @param int $courseid
     * @return array Each: flag, text.
     */
    public static function marks(int $courseid): array {
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        return array_values(array_map(fn($x) => ['flag' => (string) $x['flag'], 'text' => missions::text(
            $x['text'] ?? '',
            (string) $mission->skin,
            (string) $mission->variant
        )], array_filter($mission->data['director'] ?? [], fn($x) => !empty($x['flag']))));
    }

    /**
     * Gives a mark to a team (or to the whole class): each one who plays gets it, if they did not have it, and a gem with
     * what the teacher wrote, which they see on their device.
     *
     * @param int $courseid
     * @param int $groupid The team; 0: the whole class.
     * @param string $flag
     * @param string $note What they did, in a line.
     * @param int $actorid
     * @param int|null $now
     * @return int How many got it.
     */
    public static function give(int $courseid, int $groupid, string $flag, string $note, int $actorid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        if (!$mission || !in_array($flag, array_column(self::marks($courseid), 'flag'), true)) {
            throw new \invalid_parameter_exception('Not a mark of the mission: ' . $flag);
        }
        $note = \core_text::substr(trim(clean_param($note, PARAM_TEXT)), 0, 255);
        if ($note === '') {
            throw new \moodle_exception('directornote', 'local_oksigeniatesape');
        }
        if ($groupid) {
            $members = teams::members($courseid, $groupid);
        } else {
            $members = $DB->get_records_select(self::T . 'member', "courseid = ? AND leftat = 0 AND status <> 'paused'", [$courseid]);
        }
        $given = 0;
        foreach ($members as $member) {
            $where = ['charid' => $member->charid, 'missionid' => $mission->id, 'kind' => 'flag', 'code' => $flag];
            if (!$DB->record_exists(self::T . 'found', $where)) {
                $DB->insert_record(self::T . 'found', (object) ($where + ['courseid' => $courseid, 'timecreated' => $now]));
            }
            $entry = engine::gems($member, self::GEMS, 'manual', $actorid, 0, 0, $now);
            if ($entry) {
                $DB->set_field(self::T . 'ledger', 'note', $note, ['id' => $entry->id]);
            }
            $given++;
        }
        return $given;
    }
}
