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
 * The way into the game from Moodle: the cover of the course page (a big door with the map of the mission, a card, the
 * students going straight into the valley, or only the menu, as each teacher chooses), the activities of the mission
 * kept inside the valley, and «my world», the courses where one plays or teaches.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class portal {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Covers a teacher can choose. */
    public const MODES = ['door', 'card', 'direct', 'menu'];

    /**
     * Who someone is in a course for the game.
     *
     * @param int $courseid
     * @return string teacher, student or empty.
     */
    public static function role(int $courseid): string {
        if (!isloggedin() || isguestuser()) {
            return '';
        }
        $context = games::context($courseid);
        if (has_capability('local/oksigeniatesape:award', $context)) {
            return 'teacher';
        }
        return has_capability('local/oksigeniatesape:play', $context) ? 'student' : '';
    }

    /**
     * Whether a student opening the course goes straight into the valley: where the teacher chose so, and always for
     * who comes from another platform (LTI), for whom the game is the tool.
     *
     * @param int $courseid
     * @return bool
     */
    public static function goes_straight_in(int $courseid): bool {
        global $USER;
        // A course: the game of the student in it.
        $key = $courseid > 0 && !empty($USER->id) ? games::key($courseid, (int) $USER->id) : $courseid;
        $region = regions::get($key);
        return $region && $region->enabled && self::role($key) === 'student' &&
            ($region->portal === 'direct' || self::from_lti(games::course($key), (int) $USER->id));
    }

    /**
     * Whether someone is in a course only through «Publish as LTI tool»: they come from another platform.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    public static function from_lti(int $courseid, int $userid): bool {
        global $DB;
        if (!$userid) {
            return false;
        }
        $sql = 'SELECT e.enrol
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = ? AND e.courseid = ? AND ue.status = ? AND e.status = ?';
        $methods = $DB->get_fieldset_sql($sql, [$userid, $courseid, ENROL_USER_ACTIVE, ENROL_INSTANCE_ENABLED]);
        return $methods && !array_diff($methods, ['lti']);
    }

    /**
     * The game from another platform (LTI 1.3), for the teachers of a course: whether the site can publish courses as
     * LTI tools, how many times this course is published, and the pages of Moodle to publish it and to see what the other
     * platform needs.
     *
     * @param int $courseid
     * @return array site, published, manageurl, addurl.
     */
    public static function lti(int $courseid): array {
        global $DB;
        $context = \context_course::instance($courseid);
        $site = enrol_is_enabled('lti');
        $published = $site ? $DB->count_records_sql(
            'SELECT COUNT(1)
               FROM {enrol_lti_tools} t
               JOIN {enrol} e ON e.id = t.enrolid
              WHERE e.courseid = ? AND e.enrol = ? AND e.status = ? AND t.contextid = ? AND t.ltiversion = ?',
            [$courseid, 'lti', ENROL_INSTANCE_ENABLED, $context->id, 'LTI-1p3']
        ) : 0;
        $manage = $published && has_capability('moodle/course:enrolreview', $context);
        $add = $site && has_capability('enrol/lti:config', $context);
        return [
            'site' => $site,
            'published' => $published,
            'manageurl' => $manage ? (new \moodle_url('/enrol/lti/index.php', ['courseid' => $courseid]))->out(false) : '',
            'addurl' => $add ? (new \moodle_url('/enrol/editinstance.php', ['type' => 'lti', 'courseid' => $courseid]))->out(false)
                : '',
        ];
    }

    /**
     * The cover of the course page for the current user, as HTML; empty where there is none.
     *
     * @param \stdClass $course
     * @return string
     */
    public static function cover(\stdClass $course): string {
        global $USER;
        $role = self::role((int) $course->id);
        // A student sees the game they play in: the course, or the game more of their group.
        $key = $role === 'student' ? games::key((int) $course->id, (int) $USER->id) : (int) $course->id;
        $region = regions::get($key);
        $enabled = $region && $region->enabled;
        if (!$role || ($role === 'student' && !$enabled)) {
            return '';
        }
        $mode = $region->portal ?? 'door';
        if ($role === 'student' && in_array($mode, ['menu', 'direct'], true)) {
            return '';
        }
        if ($role === 'teacher' && $mode === 'menu' && $enabled) {
            return '';
        }
        $mission = $enabled ? missions::get($key) : null;
        $data = self::data($course, $mission, $role, (int) $USER->id, $key);
        $big = $mode !== 'card';
        $style = $data['image'] ? ' style="background-image:url(\'' . s($data['image']) . '\')"' : '';
        $buttons = '';
        foreach ($data['buttons'] as [$url, $label, $main]) {
            $buttons .= \html_writer::link($url, s($label), ['class' => 'btn ' . ($main ? 'btn-primary' : 'btn-secondary')]);
        }
        return '<section id="tesape-portal" class="tesape-portal ' . ($big ? 'tesape-door' : 'tesape-card') . '">' .
            '<div class="tesape-portal-art"' . $style . '></div><div class="tesape-portal-body">' .
            '<div class="tesape-portal-kicker">' . s($data['kicker']) . '</div><h2>' . s($data['title']) . '</h2>' .
            ($data['line'] ? '<p>' . s($data['line']) . '</p>' : '') . ($data['note'] ? '<p class="tesape-portal-note">' .
            s($data['note']) . '</p>' : '') . '<div class="tesape-portal-actions">' . $buttons . '</div></div></section>';
    }

    /**
     * What a cover shows.
     *
     * @param \stdClass $course
     * @param \stdClass|null $mission
     * @param string $role teacher or student.
     * @param int $userid
     * @param int $key The game: the course, or a game more in it.
     * @return array kicker, title, line, note, image and buttons (url, label, main).
     */
    public static function data(\stdClass $course, ?\stdClass $mission, string $role, int $userid, int $key = 0): array {
        global $DB;
        $key = $key ?: (int) $course->id;
        $play = new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $key]);
        $teach = new \moodle_url('/local/oksigeniatesape/teach.php', ['id' => $key]);
        $board = new \moodle_url('/local/oksigeniatesape/board.php', ['id' => $key]);
        $view = ['kicker' => get_string('portal_kicker', 'local_oksigeniatesape'), 'title' => games::name($key),
            'line' => '', 'note' => '', 'image' => '', 'buttons' => []];
        if (!regions::enabled($key)) {
            // Only teachers see this: the game is not on yet in the course.
            $view['line'] = get_string('portal_off', 'local_oksigeniatesape');
            $view['buttons'][] = [$teach, get_string('portal_turnon', 'local_oksigeniatesape'), true];
            return $view;
        }
        if ($mission) {
            $view['title'] = missions::text($mission->data['title'] ?? $mission->code, $mission->skin);
            $chapter = pacts::chapter($mission);
            $lit = $DB->count_records_select(self::T . 'chapter', 'missionid = ? AND litat > 0', [$mission->id]);
            $total = $DB->count_records(self::T . 'chapter', ['missionid' => $mission->id]);
            $name = '';
            foreach ($mission->data['chapters'] ?? [] as $info) {
                if ($chapter && $info['code'] === $chapter->code) {
                    $name = missions::text($info['name'] ?? $chapter->code, $mission->skin);
                }
            }
            $view['line'] = get_string('portal_line', 'local_oksigeniatesape', ['place' => $name, 'lit' => $lit,
                'total' => $total]);
            $files = art::manifest()['files'] ?? [];
            $view['image'] = $files['map.' . $mission->code . '.' . $mission->skin] ?? ($files['map.' . $mission->code] ?? '');
        }
        if ($role === 'teacher') {
            $view['buttons'][] = [$teach, get_string('portal_teach', 'local_oksigeniatesape'), true];
            $view['buttons'][] = [$board, get_string('portal_board', 'local_oksigeniatesape'), false];
            if ((regions::get($key)->portal ?? '') === 'direct') {
                $view['note'] = get_string('portal_direct_note', 'local_oksigeniatesape');
            }
            return $view;
        }
        $view['buttons'][] = [$play, get_string('portal_play', 'local_oksigeniatesape'), true];
        $char = characters::get($userid);
        if (!$char) {
            $view['note'] = get_string('portal_newcharacter', 'local_oksigeniatesape');
        } else {
            $member = $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $key]);
            $page = $member ? daily::today($char, $member) : null;
            if ($page && !$page['answered']) {
                $creature = (string) ((json_decode((string) $char->creature, true) ?: [])['name'] ?? '');
                $view['note'] = get_string('portal_hungry', 'local_oksigeniatesape', $creature);
            }
        }
        return $view;
    }

    /**
     * Keeps the activities of the mission inside the valley (available, but not on the course page), or shows them on
     * the course page again. Hidden sections hold them, so the course page stays clean for students.
     *
     * @param int $courseid
     * @param bool $inside
     */
    public static function keep_inside(int $courseid, bool $inside): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        if ($inside && empty($CFG->allowstealth)) {
            throw new \moodle_exception('nostealth', 'local_oksigeniatesape');
        }
        $mission = missions::get($courseid);
        if (!$mission) {
            return;
        }
        $sectionids = $DB->get_fieldset_select(self::T . 'chapter', 'sectionid', 'missionid = ? AND sectionid > 0', [$mission->id]);
        foreach ($sectionids as $sectionid) {
            $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => games::course($courseid)]);
            if (!$section) {
                continue;
            }
            if (method_exists(\core_courseformat\local\sectionactions::class, 'set_visibility')) {
                // Moodle 5.2 and later.
                $info = get_fast_modinfo(games::course($courseid))->get_section_info_by_id((int) $sectionid);
                \core_courseformat\formatactions::section(games::course($courseid))->set_visibility($info, !$inside);
            } else {
                set_section_visible(games::course($courseid), $section->section, $inside ? 0 : 1);
            }
            foreach (array_filter(explode(',', (string) $section->sequence)) as $cmid) {
                set_coursemodule_visible((int) $cmid, 1, $inside ? 0 : 1);
            }
        }
    }

    /**
     * The courses where someone plays or teaches the game, for «my world».
     *
     * @param int $userid
     * @return array id, name, role (student or teacher), mission (title), line, image, url and, for teachers, board.
     */
    public static function world(int $userid): array {
        $list = [];
        foreach (enrol_get_users_courses($userid, true, 'id, fullname, shortname') as $course) {
            $role = self::role((int) $course->id);
            // A student, the game they play in; a teacher, every game of the course.
            $keys = $role === 'student' ? [games::key((int) $course->id, $userid)] : games::keys((int) $course->id);
            foreach ($role ? $keys : [] as $key) {
                if (!regions::enabled($key)) {
                    continue;
                }
                $mission = missions::get($key);
                $data = self::data($course, $mission, $role, $userid, $key);
                $list[] = [
                    'id' => $key,
                    'name' => games::name($key),
                    'role' => $role,
                    'mission' => $data['title'],
                    'line' => $data['line'],
                    'note' => $data['note'],
                    'image' => $data['image'],
                    'buttons' => $data['buttons'],
                ];
            }
        }
        return $list;
    }

    /**
     * Whether «my world» has something for someone: the entry of the game in the top bar, remembered a while.
     *
     * @param int $userid
     * @return bool
     */
    public static function has_world(int $userid): bool {
        global $DB, $SESSION;
        $key = 'local_oksigeniatesape_world';
        $known = $SESSION->$key ?? null;
        if (is_array($known) && $known['time'] > time() - 10 * MINSECS && $known['user'] === $userid) {
            return $known['has'];
        }
        $courses = [];
        foreach (array_keys(enrol_get_users_courses($userid, true, 'id')) as $courseid) {
            // The game of each course and its games more.
            $courses = array_merge($courses, games::keys((int) $courseid));
        }
        $has = false;
        if ($courses) {
            [$insql, $params] = $DB->get_in_or_equal($courses);
            $has = $DB->record_exists_select(self::T . 'region', "enabled = 1 AND courseid $insql", $params);
        }
        $SESSION->$key = ['time' => time(), 'user' => $userid, 'has' => $has];
        return $has;
    }
}
