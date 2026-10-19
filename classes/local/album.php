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
 * The album of the field guide: each entry of the field guide of the class (a Moodle database of the mission) made by
 * the student or by their team is a card (once approved, if the guide asks for approval); the card is golden if the
 * entry says what place the species takes in a food chain. It reads the database: nothing is kept apart.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class album {
    /**
     * The album of a student in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null cards (name, group, golden, mine), class (entries of the whole class) and cmid of the guide;
     *     null if the course has no field guide in the game.
     */
    public static function for_student(int $courseid, int $userid): ?array {
        global $DB;
        $guide = self::guide($courseid);
        if (!$guide) {
            return null;
        }
        $users = [$userid];
        $team = teams::of($courseid, $userid);
        if ($team) {
            $users = array_unique(array_merge($users, array_map('intval', array_keys(groups_get_members($team->id, 'u.id')))));
        }
        // The species is the first text field; its group, the first menu; its place in a food chain, the second menu.
        $fields = $DB->get_records('data_fields', ['dataid' => $guide->instance], 'id', 'id, type');
        $texts = array_keys(array_filter($fields, fn($f) => $f->type === 'text'));
        $menus = array_keys(array_filter($fields, fn($f) => $f->type === 'menu'));
        [$insql, $params] = $DB->get_in_or_equal($users);
        // Where the guide asks for approval, only approved entries count; otherwise Moodle does not use the mark.
        $approved = $guide->approval ? ' AND approved = 1' : '';
        $approvedonly = $guide->approval ? ['approved' => 1] : [];
        $records = $DB->get_records_select(
            'data_records',
            "dataid = ?$approved AND userid $insql",
            array_merge([$guide->instance], $params),
            'timecreated, id',
            'id, userid'
        );
        $cards = [];
        if ($records) {
            [$inrec, $recparams] = $DB->get_in_or_equal(array_keys($records));
            $contents = [];
            $rows = $DB->get_records_select('data_content', "recordid $inrec", $recparams, '', 'id, recordid, fieldid, content');
            foreach ($rows as $c) {
                $contents[$c->recordid][$c->fieldid] = trim((string) $c->content);
            }
            foreach ($records as $record) {
                $content = $contents[$record->id] ?? [];
                $name = isset($texts[0]) ? ($content[$texts[0]] ?? '') : '';
                if ($name === '') {
                    continue;
                }
                $cards[] = [
                    'name' => format_string($name),
                    'group' => isset($menus[0]) ? format_string($content[$menus[0]] ?? '') : '',
                    'golden' => isset($menus[1]) && ($content[$menus[1]] ?? '') !== '',
                    'mine' => (int) $record->userid === $userid,
                ];
            }
        }
        return [
            'cards' => $cards,
            'class' => $DB->count_records('data_records', ['dataid' => $guide->instance] + $approvedonly),
            'cmid' => (int) $guide->id,
        ];
    }

    /**
     * The field guide of the class in a course: the first database of the course that is a challenge of the game.
     *
     * @param int $courseid
     * @return \stdClass|null id (its cmid), instance and approval; null if there is none.
     */
    public static function guide(int $courseid): ?\stdClass {
        global $DB;
        $guides = $DB->get_records_sql(
            "SELECT cm.id, cm.instance, d.approval
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'data'
               JOIN {data} d ON d.id = cm.instance
               JOIN {local_oksigeniatesape_challenge} c ON c.cmid = cm.id
              WHERE cm.course = ? AND cm.deletioninprogress = 0
           ORDER BY cm.id",
            [$courseid]
        );
        return $guides ? reset($guides) : null;
    }
}
