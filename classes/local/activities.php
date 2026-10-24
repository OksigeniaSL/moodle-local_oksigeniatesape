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
 * Moodle activities in the game: any activity can be a challenge (with its knowledge, gems, steps, item and chapter,
 * set in its own settings form), and those in the section of a chapter appear in its place on the map. A mission can
 * bring its own activities (forums, assignments, a database), made in a section per chapter when it is installed.
 *
 * Completing the activity is what counts: the observer gives what the challenge gives.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activities {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Modules a mission can make. */
    public const MODULES = ['forum', 'assign', 'data'];

    /** @var int Most knowledge an activity can give. */
    public const MAX_SABER = 200;

    /** @var int Most gems an activity can give. */
    public const MAX_GEMS = 50;

    /**
     * The challenge of an activity in a game, if it is one.
     *
     * @param int $cmid
     * @param int $courseid The game: in a course with more than one, an activity may be a challenge in each.
     * @return \stdClass|null
     */
    public static function challenge(int $cmid, int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::T . 'challenge', ['cmid' => $cmid, 'courseid' => $courseid]) ?: null;
    }

    /**
     * The settings of an activity from its form in Moodle: for the game of the course and, in a course with more than
     * one game, for each game more (with its own place of the same code, if it has one).
     *
     * @param int $courseid
     * @param int $cmid
     * @param array $data As for save().
     */
    public static function save_in_course(int $courseid, int $cmid, array $data): void {
        global $DB;
        $challenge = self::save($courseid, $cmid, $data);
        $code = $challenge->chapterid ? (string) $DB->get_field(self::T . 'chapter', 'code', ['id' => $challenge->chapterid]) : '';
        foreach (array_slice(games::keys($courseid), 1) as $key) {
            $mission = missions::get($key);
            $data['chapterid'] = $code !== '' && $mission ? (int) $DB->get_field(
                self::T . 'chapter',
                'id',
                ['missionid' => $mission->id, 'code' => $code]
            ) : 0;
            self::save($key, $cmid, $data);
        }
    }

    /**
     * Marks an activity as a challenge, or changes it.
     *
     * @param int $courseid
     * @param int $cmid
     * @param array $data Any of saber, gems, steps, teamwork, itemid, chapterid, objectives.
     * @param int|null $now
     * @return \stdClass
     */
    public static function save(int $courseid, int $cmid, array $data, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $challenge = self::challenge($cmid, $courseid) ?? (object) ['cmid' => $cmid, 'courseid' => $courseid, 'kind' => 'activity',
            'saber' => 20, 'timecreated' => $now];
        if (array_key_exists('saber', $data)) {
            $challenge->saber = max(0, min(self::MAX_SABER, (int) $data['saber']));
        }
        if (array_key_exists('gems', $data)) {
            $challenge->gems = max(0, min(self::MAX_GEMS, (int) $data['gems']));
        }
        foreach (['itemid', 'chapterid'] as $number) {
            if (array_key_exists($number, $data)) {
                $challenge->$number = max(0, (int) $data[$number]);
            }
        }
        if (array_key_exists('teamwork', $data)) {
            $challenge->teamwork = $data['teamwork'] ? 1 : 0;
        }
        if (array_key_exists('steps', $data)) {
            $steps = array_unique(array_filter(array_map('intval', (array) $data['steps']), fn($s) => $s >= 1 && $s <= 4));
            sort($steps);
            $challenge->steps = $steps ? implode(',', $steps) : '1,2,3,4';
        }
        if (array_key_exists('objectives', $data)) {
            $challenge->objectives = \core_text::substr(implode(',', (array) $data['objectives']), 0, 255);
        }
        $challenge->timemodified = $now;
        if (empty($challenge->id)) {
            $challenge->id = $DB->insert_record(self::T . 'challenge', $challenge);
        } else {
            $DB->update_record(self::T . 'challenge', $challenge);
        }
        return $DB->get_record(self::T . 'challenge', ['id' => $challenge->id], '*', MUST_EXIST);
    }

    /**
     * An activity stops being a challenge. What it gave stays in the log.
     *
     * @param int $cmid
     */
    public static function remove(int $cmid): void {
        global $DB;
        $DB->delete_records(self::T . 'challenge', ['cmid' => $cmid]);
    }

    /**
     * The team activities of a course follow the teams of the game: when the teams change, their team submissions
     * use the new grouping, so that nobody is in two teams for Moodle.
     *
     * @param int $courseid
     * @param int $groupingid
     * @return int Activities changed.
     */
    public static function regroup(int $courseid, int $groupingid): int {
        global $DB;
        // In a course with more than one game, its activities are shared: the teams of all the games, in one grouping.
        $keys = games::keys(games::course($courseid));
        if (count($keys) > 1) {
            $groupingid = teams::all_teams(games::course($courseid));
        }
        [$insql, $params] = $DB->get_in_or_equal(count($keys) > 1 ? $keys : [$courseid]);
        $sql = "SELECT DISTINCT a.id
                  FROM {" . self::T . "challenge} c
                  JOIN {course_modules} cm ON cm.id = c.cmid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                  JOIN {assign} a ON a.id = cm.instance
                 WHERE c.courseid $insql AND c.teamwork = 1 AND a.teamsubmission = 1
                       AND a.teamsubmissiongroupingid <> ?";
        $ids = $DB->get_fieldset_sql($sql, array_merge($params, [$groupingid]));
        foreach ($ids as $id) {
            $DB->set_field('assign', 'teamsubmissiongroupingid', $groupingid, ['id' => $id]);
        }
        return count($ids);
    }

    /**
     * The activities a student sees in the game: the challenges of their step and the activities in the section of
     * each chapter, with whether they have completed them.
     *
     * @param int $courseid
     * @param int $userid
     * @param \stdClass $member
     * @return array
     */
    public static function for_student(int $courseid, int $userid, \stdClass $member): array {
        global $DB;
        $modinfo = get_fast_modinfo(games::course($courseid), $userid);
        $course = $modinfo->get_course();
        $completion = new \completion_info($course);
        $region = regions::get($courseid);
        $rule = $region ? regions::rule($region, 'completion') : regions::RULES['completion'];
        $challenges = [];
        foreach ($DB->get_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$courseid]) as $challenge) {
            $challenges[$challenge->cmid] = $challenge;
        }
        $chapterof = [];
        $mission = missions::get($courseid);
        if ($mission) {
            foreach ($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id]) as $chapter) {
                if ($chapter->sectionid) {
                    $chapterof[$chapter->sectionid] = (int) $chapter->id;
                }
            }
        }
        $list = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->url || $cm->deletioninprogress) {
                continue;
            }
            $challenge = $challenges[$cm->id] ?? null;
            $chapterid = $challenge && $challenge->chapterid ? (int) $challenge->chapterid : ($chapterof[$cm->section] ?? 0);
            if (!$challenge && !$chapterid) {
                continue;
            }
            if ($challenge && !in_array((string) $member->step, explode(',', $challenge->steps), true)) {
                continue;
            }
            $tracked = $completion->is_enabled($cm) != COMPLETION_TRACKING_NONE;
            $state = $tracked ? (int) $completion->get_data($cm, false, $userid)->completionstate : 0;
            $list[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'icon' => $cm->get_icon_url()->out(false),
                'url' => $cm->url->out(false),
                'chapterid' => $chapterid,
                'challenge' => (bool) $challenge,
                'saber' => $tracked ? ($challenge ? (int) $challenge->saber : $rule) : 0,
                'gems' => $challenge && $tracked ? (int) $challenge->gems : 0,
                'teamwork' => $challenge ? (bool) $challenge->teamwork : false,
                'tracked' => $tracked,
                'done' => in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true),
            ];
        }
        return $list;
    }

    /**
     * The activities that are challenges in a course, for the teacher: how many students have completed each.
     *
     * @param int $courseid
     * @param int[] $userids The students.
     * @return array
     */
    public static function for_teacher(int $courseid, array $userids): array {
        global $DB;
        $modinfo = get_fast_modinfo(games::course($courseid));
        $list = [];
        foreach ($DB->get_records_select(self::T . 'challenge', 'courseid = ? AND cmid > 0', [$courseid], 'id') as $challenge) {
            $cm = $modinfo->cms[$challenge->cmid] ?? null;
            if (!$cm) {
                continue;
            }
            $done = 0;
            if ($userids) {
                [$insql, $params] = $DB->get_in_or_equal($userids);
                $done = $DB->count_records_select(
                    'course_modules_completion',
                    "coursemoduleid = ? AND userid $insql AND completionstate IN (?, ?)",
                    array_merge([$cm->id], $params, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS])
                );
            }
            $list[] = [
                'cmid' => (int) $cm->id,
                'name' => $cm->get_formatted_name(),
                'modname' => $cm->modname,
                'icon' => $cm->get_icon_url()->out(false),
                'url' => (new \moodle_url('/course/modedit.php', ['update' => $cm->id]))->out(false),
                'saber' => (int) $challenge->saber,
                'gems' => (int) $challenge->gems,
                'steps' => $challenge->steps,
                'teamwork' => (bool) $challenge->teamwork,
                'tracked' => $cm->completion != COMPLETION_TRACKING_NONE,
                'done' => $done,
            ];
        }
        return $list;
    }

    /**
     * Makes the activities of a chapter of a mission in a new section of the course, named as the chapter, and marks
     * them as challenges of the chapter.
     *
     * @param \stdClass $course
     * @param \stdClass $chapter The chapter of the mission in the course.
     * @param array $info The chapter in the package.
     * @param string $skin
     * @param int $now
     * @param string $variant The local examples, for the voice of the texts.
     * @param int $key The game, if it is one more of the course.
     * @return int The id of the section.
     */
    public static function make_for_chapter(
        \stdClass $course,
        \stdClass $chapter,
        array $info,
        string $skin,
        int $now,
        string $variant = '',
        int $key = 0
    ): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/data/lib.php');
        if (!empty($CFG->enablecompletion) && empty($course->enablecompletion)) {
            // Completing the activities is what counts in the game.
            $DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
            $course->enablecompletion = 1;
            rebuild_course_cache($course->id, true);
        }
        $section = course_create_section($course);
        course_update_section($course, $section, ['name' => missions::text($info['name'] ?? $chapter->code, $skin)]);
        $key = $key ?: (int) $course->id;
        $region = regions::get($key);
        // In a course with more than one game, the teams of all of them.
        $shared = count(games::keys((int) $course->id)) > 1;
        foreach ($info['activities'] ?? [] as $activity) {
            if (!in_array($activity['module'] ?? '', self::MODULES, true)) {
                continue;
            }
            $groupingid = $shared ? teams::all_teams((int) $course->id) : (int) ($region->groupingid ?? 0);
            $moduleinfo = self::moduleinfo($course, (int) $section->section, $activity, $skin, $groupingid, $variant);
            $moduleinfo = add_moduleinfo($moduleinfo, $course);
            if ($activity['module'] === 'data') {
                self::data_fields((int) $moduleinfo->instance, $course, $activity['fields'] ?? []);
            }
            self::save($key, (int) $moduleinfo->coursemodule, [
                'saber' => (int) ($activity['saber'] ?? 20),
                'teamwork' => !empty($activity['teamwork']),
                'chapterid' => (int) $chapter->id,
                'objectives' => $activity['objectives'] ?? [],
            ], $now);
        }
        $DB->set_field(self::T . 'chapter', 'sectionid', $section->id, ['id' => $chapter->id]);
        return (int) $section->id;
    }

    /**
     * What Moodle needs to make an activity of a mission, with its completion set so that it counts in the game.
     *
     * @param \stdClass $course
     * @param int $section Number of the section.
     * @param array $activity
     * @param string $skin
     * @param int $groupingid The grouping of the teams, for team submissions.
     * @param string $variant The local examples, for the voice of the texts.
     * @return \stdClass
     */
    private static function moduleinfo(
        \stdClass $course,
        int $section,
        array $activity,
        string $skin,
        int $groupingid,
        string $variant = ''
    ): \stdClass {
        global $DB;
        $module = $activity['module'];
        $intro = missions::text($activity['intro'] ?? '', $skin, $variant);
        $info = [
            'modulename' => $module,
            'module' => (int) $DB->get_field('modules', 'id', ['name' => $module], MUST_EXIST),
            'course' => (int) $course->id,
            'section' => $section,
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'name' => \core_text::substr(missions::text($activity['name'], $skin), 0, 255),
            'intro' => $intro === '' ? '' : '<p>' . s($intro) . '</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 0,
            'cmidnumber' => '',
            'groupmode' => NOGROUPS,
            'groupingid' => 0,
            'availability' => null,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 0,
            'completionexpected' => 0,
            'completionpassgrade' => 0,
        ];
        if ($module === 'forum') {
            $info += ['type' => 'general', 'assessed' => 0, 'scale' => 0, 'grade_forum' => 0,
                'forcesubscribe' => 0, 'completionposts' => 1];
        } else if ($module === 'assign') {
            $team = !empty($activity['teamwork']);
            $info += [
                'alwaysshowdescription' => 1,
                'submissiondrafts' => 0,
                'requiresubmissionstatement' => 0,
                'sendnotifications' => 0,
                'sendlatenotifications' => 0,
                'sendstudentnotifications' => 1,
                'duedate' => 0,
                'cutoffdate' => 0,
                'gradingduedate' => 0,
                'allowsubmissionsfromdate' => 0,
                'grade' => 0,
                'teamsubmission' => $team ? 1 : 0,
                'requireallteammemberssubmit' => 0,
                'teamsubmissiongroupingid' => $team ? $groupingid : 0,
                'preventsubmissionnotingroup' => 0,
                'blindmarking' => 0,
                'hidegrader' => 0,
                'attemptreopenmethod' => 'untilpass',
                'maxattempts' => -1,
                'markingworkflow' => 0,
                'markingallocation' => 0,
                'markinganonymous' => 0,
                'activityformat' => 0,
                'timelimit' => 0,
                'submissionattachments' => 0,
                'completionsubmit' => 1,
                'assignsubmission_onlinetext_enabled' => 1,
                'assignsubmission_file_enabled' => 1,
                'assignsubmission_file_maxfiles' => 10,
                'assignsubmission_file_maxsizebytes' => 0,
                'assignsubmission_file_filetypes' => 'image,document',
                'assignfeedback_comments_enabled' => 1,
            ];
        } else if ($module === 'data') {
            $info += ['assessed' => 0, 'scale' => 0, 'approval' => 0, 'comments' => 1, 'completionentries' => 1];
        }
        return (object) $info;
    }

    /**
     * The fields of a database of a mission.
     *
     * @param int $dataid
     * @param \stdClass $course
     * @param array $fields name, type (text, textarea, picture, menu) and options for menus.
     */
    private static function data_fields(int $dataid, \stdClass $course, array $fields): void {
        global $DB;
        $data = $DB->get_record('data', ['id' => $dataid], '*', MUST_EXIST);
        foreach ($fields as $field) {
            $type = in_array($field['type'] ?? '', ['text', 'textarea', 'picture', 'menu'], true) ? $field['type'] : 'text';
            $record = (object) [
                'dataid' => $data->id,
                'type' => $type,
                'name' => \core_text::substr((string) $field['name'], 0, 255),
                'description' => (string) $field['name'],
                'required' => 0,
                'param1' => '',
                'param2' => '',
                'param3' => '',
            ];
            if ($type === 'menu') {
                $record->param1 = implode("\n", $field['options'] ?? []);
            } else if ($type === 'textarea') {
                $record->param2 = 60;
                $record->param3 = 8;
                $record->param4 = 1;
                $record->param5 = 0;
            } else if ($type === 'picture') {
                $record->param1 = '';
                $record->param3 = 0;
            }
            $instance = data_get_field($record, $data);
            $instance->insert_field();
        }
    }
}
