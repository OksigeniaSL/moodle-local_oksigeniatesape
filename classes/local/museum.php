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
 * The museum of the class in the Library: the entries of the field guide of the whole class (once approved, if the
 * guide asks for approval) fill the shelves, one shelf for each group of living things of the guide, one spine for each
 * entry. A shelf with three entries or more has a light of its own. It reads the database of the guide, as the album
 * does: nothing is kept apart, and no entry says who made it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class museum {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Entries a shelf needs for its light. */
    public const LIT = 3;

    /** @var int Most entries shown on a shelf, the newest. */
    public const MAX = 24;

    /**
     * The museum of a course.
     *
     * @param int $courseid
     * @return array|null shelves (group, lit, books: name, golden, looks, lives, chain) and entries of the class; null if
     *     the course has no field guide in the game.
     */
    public static function of_course(int $courseid): ?array {
        global $DB;
        $guide = album::guide($courseid);
        if (!$guide) {
            return null;
        }
        // The species is the first text field and where it lives the second; how it looks, the first long text; its
        // group, the first menu; its place in a food chain, the second menu.
        $fields = $DB->get_records('data_fields', ['dataid' => $guide->instance], 'id', 'id, type, param1');
        $of = fn($type) => array_values(array_filter($fields, fn($f) => $f->type === $type));
        [$texts, $longs, $menus] = [$of('text'), $of('textarea'), $of('menu')];
        $shelves = [];
        foreach (isset($menus[0]) ? preg_split('/\R/', trim((string) $menus[0]->param1)) : [] as $group) {
            if (trim($group) !== '') {
                $shelves[trim($group)] = ['group' => format_string(trim($group)), 'lit' => false, 'books' => []];
            }
        }
        $where = ['dataid' => $guide->instance] + ($guide->approval ? ['approved' => 1] : []);
        $records = $DB->get_records('data_records', $where, 'timecreated DESC, id DESC', 'id');
        $contents = [];
        if ($records) {
            [$inrec, $params] = $DB->get_in_or_equal(array_keys($records));
            foreach ($DB->get_records_select('data_content', "recordid $inrec", $params, '', 'id, recordid, fieldid, content') as $c) {
                $contents[$c->recordid][$c->fieldid] = trim((string) $c->content);
            }
        }
        $value = fn(array $content, array $list, int $i) => isset($list[$i]) ? ($content[$list[$i]->id] ?? '') : '';
        $count = 0;
        foreach ($records as $record) {
            $content = $contents[$record->id] ?? [];
            $name = $value($content, $texts, 0);
            if ($name === '') {
                continue;
            }
            $count++;
            $group = $value($content, $menus, 0);
            if (!isset($shelves[$group])) {
                $shelves[$group] = ['group' => format_string($group), 'lit' => false, 'books' => []];
            }
            if (count($shelves[$group]['books']) >= self::MAX) {
                continue;
            }
            $chain = $value($content, $menus, 1);
            $shelves[$group]['books'][] = [
                'name' => format_string($name),
                'golden' => $chain !== '',
                'looks' => shorten_text(format_string(strip_tags($value($content, $longs, 0))), 220),
                'lives' => shorten_text(format_string($value($content, $texts, 1)), 120),
                'chain' => format_string($chain),
            ];
        }
        foreach ($shelves as $key => $shelf) {
            $shelves[$key]['lit'] = count($shelf['books']) >= self::LIT;
            // Oldest first on the shelf, as they came.
            $shelves[$key]['books'] = array_reverse($shelf['books']);
        }
        return ['shelves' => array_values($shelves), 'entries' => $count];
    }
    /**
     * The libraries of other regions of the site that a student of a course can visit: those whose teachers share them,
     * with the game on and some book in their shelves. The work of a class, without people.
     *
     * @param int $courseid
     * @return array Each: courseid, name.
     */
    public static function shared(int $courseid): array {
        global $DB;
        $like = $DB->sql_like('rules', ':rule');
        $regions = $DB->get_records_select(
            self::T . 'region',
            "enabled = 1 AND courseid <> :courseid AND $like",
            ['courseid' => $courseid, 'rule' => '%"library":1%'],
            'courseid',
            'id, courseid, rules'
        );
        $out = [];
        foreach ($regions as $region) {
            $course = $DB->get_record('course', ['id' => $region->courseid], 'id, fullname, visible');
            if ($course && $course->visible && regions::rule($region, 'library') > 0) {
                $out[] = ['courseid' => (int) $course->id, 'name' => format_string(
                    $course->fullname,
                    true,
                    ['context' => \context_course::instance($course->id)]
                )];
            }
        }
        return $out;
    }

    /**
     * The library of another region, for a student of a course: only if its teachers share it.
     *
     * @param int $courseid Of the student.
     * @param int $otherid Of the library.
     * @return array name, museum.
     */
    public static function visit(int $courseid, int $otherid): array {
        if (!in_array($otherid, array_column(self::shared($courseid), 'courseid'), true)) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniatesape');
        }
        $museum = self::of_course($otherid);
        if (!$museum) {
            throw new \moodle_exception('nolibrary', 'local_oksigeniatesape');
        }
        return ['name' => format_string(get_course($otherid)->fullname), 'museum' => $museum];
    }
}
