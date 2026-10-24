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
 * More than one game in a course: 5th A and 5th B in the same course of Moodle, each with its world, its teams, its
 * class screen and its report. The game of the course is the course itself, as always; each game more is for the
 * students of a group of the course, and its data say, where they say course, its key: minus its id. So the game
 * keeps its key everywhere, and only what asks Moodle (the context, the groups, the badges) goes to its course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class games {
    /** @var string The table of the games more. */
    private const TABLE = 'local_oksigeniatesape_game';

    /**
     * The games more of a course, by id.
     *
     * @param int $courseid
     * @return \stdClass[]
     */
    public static function of_course(int $courseid): array {
        global $DB;
        // Read once in each request: each call of the game asks it.
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'games');
        $kept = $cache->get('c' . $courseid);
        if ($kept === false) {
            $kept = $DB->get_records(self::TABLE, ['courseid' => $courseid], 'id');
            $cache->set('c' . $courseid, $kept);
        }
        return $kept;
    }

    /**
     * A game more, by its key; null for the game of a course.
     *
     * @param int $key
     * @return \stdClass|null
     */
    public static function get(int $key): ?\stdClass {
        global $DB;
        if ($key >= 0) {
            return null;
        }
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'games');
        $kept = $cache->get('g' . -$key);
        if ($kept === false) {
            $kept = $DB->get_record(self::TABLE, ['id' => -$key]) ?: 0;
            $cache->set('g' . -$key, $kept);
        }
        return $kept ?: null;
    }

    /**
     * The course of a game.
     *
     * @param int $key
     * @return int
     */
    public static function course(int $key): int {
        if ($key >= 0) {
            return $key;
        }
        $game = self::get($key);
        if (!$game) {
            throw new \moodle_exception('invalidcourseid');
        }
        return (int) $game->courseid;
    }

    /**
     * The context of the course of a game.
     *
     * @param int $key
     * @return \context_course
     */
    public static function context(int $key): \context_course {
        return \context_course::instance(self::course($key));
    }

    /**
     * The keys of the games of a course: its own and those more.
     *
     * @param int $courseid
     * @return int[]
     */
    public static function keys(int $courseid): array {
        return array_merge([$courseid], array_map(fn($id) => -(int) $id, array_keys(self::of_course($courseid))));
    }

    /**
     * Where a student plays in a course: the first game more whose group they are in, or the game of the course.
     *
     * @param int $courseid
     * @param int $userid
     * @return int The key.
     */
    public static function key(int $courseid, int $userid): int {
        foreach (self::of_course($courseid) as $game) {
            if (groups_is_member((int) $game->groupid, $userid)) {
                return -(int) $game->id;
            }
        }
        return $courseid;
    }

    /**
     * The name of a game: that of its course, and of the game more.
     *
     * @param int $key
     * @return string
     */
    public static function name(int $key): string {
        $course = get_course(self::course($key));
        $name = format_string($course->fullname, true, ['context' => self::context($key)]);
        $game = self::get($key);
        return $game ? $name . ' · ' . format_string($game->name) : $name;
    }

    /**
     * Of the students who can play in a course, those of a game: those of its group for a game more; for the game of
     * the course, those who are in no game more.
     *
     * @param int $key
     * @param \stdClass[] $students Of the course, by id.
     * @return \stdClass[]
     */
    public static function filter(int $key, array $students): array {
        $game = self::get($key);
        if ($game) {
            $members = groups_get_members((int) $game->groupid, 'u.id');
            return array_intersect_key($students, $members);
        }
        foreach (self::of_course($key) as $other) {
            $students = array_diff_key($students, groups_get_members((int) $other->groupid, 'u.id'));
        }
        return $students;
    }

    /**
     * Whether a user can come into a game: who teaches in the course, always; a student, only into their own game.
     *
     * @param int $key
     * @param int $userid
     * @return bool
     */
    public static function can_enter(int $key, int $userid): bool {
        $courseid = self::course($key);
        if (
            has_any_capability(['local/oksigeniatesape:manageregion', 'local/oksigeniatesape:award',
                'local/oksigeniatesape:runclassscreen'], \context_course::instance($courseid), $userid)
        ) {
            return true;
        }
        return self::key($courseid, $userid) === $key;
    }

    /**
     * Checks that a user can come into a game, and gives the context of its course.
     *
     * @param int $key
     * @param int|null $userid The user now, by default.
     * @return \context_course
     */
    public static function require_enter(int $key, ?int $userid = null): \context_course {
        global $USER;
        $context = self::context($key);
        if (!self::can_enter($key, $userid ?? (int) $USER->id)) {
            throw new \required_capability_exception($context, 'local/oksigeniatesape:play', 'nopermissions', '');
        }
        return $context;
    }

    /**
     * Opens a game more in a course, for the students of one of its groups.
     *
     * @param int $courseid
     * @param int $groupid
     * @param string $name Empty: that of the group.
     * @param int|null $now
     * @return int Its key.
     */
    public static function create(int $courseid, int $groupid, string $name = '', ?int $now = null): int {
        global $DB;
        $group = groups_get_group($groupid);
        if (!$group || (int) $group->courseid !== $courseid) {
            throw new \invalid_parameter_exception('Not a group of the course: ' . $groupid);
        }
        foreach (self::of_course($courseid) as $game) {
            if ((int) $game->groupid === $groupid) {
                throw new \moodle_exception('gamegroupused', 'local_oksigeniatesape');
            }
        }
        $now = $now ?? time();
        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 255);
        $id = $DB->insert_record(self::TABLE, (object) ['courseid' => $courseid, 'groupid' => $groupid,
            'name' => $name !== '' ? $name : $group->name, 'timecreated' => $now, 'timemodified' => $now]);
        self::forget();
        // The teams of the game of the course were all its groups: now one of them is a class, so they go, as they are,
        // to a grouping of their own.
        $region = regions::get($courseid);
        if ($region && !(int) $region->groupingid) {
            teams::keep_as_teams($courseid, array_map(fn($g) => (int) $g->groupid, self::of_course($courseid)));
        }
        return -(int) $id;
    }

    /**
     * Closes a game more: all it had goes with it (its world, its teams in the game, what its students won there).
     * Its students go back to the game of the course.
     *
     * @param int $key
     */
    public static function delete(int $key): void {
        global $DB;
        if (!self::get($key)) {
            return;
        }
        \local_oksigeniatesape\observer::clear_game($key);
        $DB->delete_records(self::TABLE, ['id' => -$key]);
        self::forget();
    }

    /**
     * The groups of a course a game more can be for: those not used by a game yet nor teams of the game.
     *
     * @param int $courseid
     * @return \stdClass[] id, name.
     */
    public static function free_groups(int $courseid): array {
        $used = array_map(fn($g) => (int) $g->groupid, self::of_course($courseid));
        // The teams of every game are groups too: they are not classes.
        foreach (self::keys($courseid) as $key) {
            $region = regions::get($key);
            if ($region && $region->groupingid) {
                $used = array_merge($used, array_keys(groups_get_all_groups($courseid, 0, (int) $region->groupingid, 'g.id')));
            }
        }
        return array_values(array_filter(
            groups_get_all_groups($courseid, 0, 0, 'g.id, g.name'),
            fn($g) => !in_array((int) $g->id, $used, true)
        ));
    }

    /**
     * The games of a course for its teachers: its own and those more, with the group of each.
     *
     * @param int $courseid
     * @return array key, name, group (0 for the game of the course).
     */
    public static function menu(int $courseid): array {
        $out = [['key' => $courseid, 'name' => '', 'group' => 0]];
        foreach (self::of_course($courseid) as $game) {
            $out[] = ['key' => -(int) $game->id, 'name' => format_string($game->name), 'group' => (int) $game->groupid];
        }
        return $out;
    }

    /**
     * A course as a game: the same course, with the key of the game and its name, for what is made for one game.
     *
     * @param \stdClass $course
     * @param int $key
     * @return \stdClass
     */
    public static function as_course(\stdClass $course, int $key): \stdClass {
        $game = clone $course;
        $game->id = $key;
        $game->fullname = self::name($key);
        return $game;
    }

    /**
     * The key of a game for a cache of simple keys (letters, numbers and _): a game more, g and its id.
     *
     * @param int $key
     * @return string
     */
    public static function cache_key(int $key): string {
        return $key < 0 ? 'g' . -$key : (string) $key;
    }

    /**
     * Forgets the games read in this request: one changed.
     */
    public static function forget(): void {
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'games')->purge();
    }
}
