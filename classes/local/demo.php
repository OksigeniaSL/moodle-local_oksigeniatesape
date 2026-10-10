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
 * A made-up class to try the game on a development site: a course, a teacher, students with invented names, teams,
 * characters and, if asked, some weeks of progress. Nobody in it exists.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class demo {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string Prefix of the demo usernames, so that they are easy to find and remove. */
    public const USERNAME_PREFIX = 'tesapedemo';

    /** @var array[] Invented first names with the form of the role name each one would pick. */
    private const FIRST_NAMES = [
        ['Lucía', 'f'], ['Mateo', 'm'], ['Sofía', 'f'], ['Hugo', 'm'], ['Martina', 'f'], ['Leo', 'm'], ['Valentina', 'f'],
        ['Thiago', 'm'], ['Paula', 'f'], ['Daniel', 'm'], ['Camila', 'f'], ['Álvaro', 'm'], ['Abril', 'f'], ['Bruno', 'm'],
        ['Jimena', 'f'], ['Santiago', 'm'], ['Noa', 'f'], ['Gael', 'm'], ['Irene', 'f'], ['Joaquín', 'm'], ['Celeste', 'f'],
        ['Tomás', 'm'], ['Aitana', 'f'], ['Iker', 'm'], ['Rocío', 'f'], ['Benjamín', 'm'], ['Elena', 'f'], ['Gonzalo', 'm'],
        ['Mía', 'f'], ['Rodrigo', 'm'],
    ];

    /** @var string[] Invented surnames. */
    private const SURNAMES = ['Arrieta', 'Benítez', 'Cabral', 'Duarte', 'Escobar', 'Ferreira', 'Galeano', 'Herrera',
        'Insfrán', 'Jara', 'Ledesma', 'Medina', 'Núñez', 'Ortiz', 'Paredes', 'Quintana', 'Ramírez', 'Sosa', 'Torres',
        'Valdez', 'Zárate'];

    /** @var string[] Team names. */
    private const TEAMS = ['Owls', 'Herons', 'Jaguars', 'Toucans', 'Lynxes', 'Foxes', 'Hawks', 'Otters'];

    /** @var string[] The four roles, in the order teams are filled. */
    private const ROLES = ['protector', 'healer', 'sage', 'scout'];

    /** @var string[] Lineages of the fantasy families. */
    private const LINEAGES = ['human', 'elf', 'dwarf', 'fairy'];

    /**
     * Makes the class.
     *
     * @param string $shortname Short name of the new course.
     * @param int $students How many students (up to 30).
     * @param int $teamsize Students per team.
     * @param string $family Family of role names.
     * @param int $weeks Weeks of made-up progress (0: none).
     * @param int $seed Seed of the random choices, to get the same class again.
     * @return \stdClass The course, the teacher and the students.
     */
    public static function create_class(
        string $shortname,
        int $students = 25,
        int $teamsize = 5,
        string $family = 'storyheroes',
        int $weeks = 0,
        int $seed = 1
    ): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/lib/enrollib.php');

        if ($DB->record_exists('course', ['shortname' => $shortname])) {
            throw new \moodle_exception('shortnametaken', 'error', '', $shortname);
        }
        $students = max(1, min(count(self::FIRST_NAMES), $students));
        $teamsize = max(2, $teamsize);
        $now = time();

        // Every random choice first, so that Moodle's own use of the generator does not change the class.
        mt_srand($seed);
        $names = self::FIRST_NAMES;
        self::shuffle($names);
        $fantasy = in_array($family, ['storyheroes', 'epic'], true);
        $plan = [];
        for ($i = 0; $i < $students; $i++) {
            $plan[] = (object) [
                'firstname' => $names[$i][0],
                'nameform' => $names[$i][1],
                'lastname' => self::SURNAMES[mt_rand(0, count(self::SURNAMES) - 1)],
                'lineage' => $fantasy ? self::LINEAGES[mt_rand(0, count(self::LINEAGES) - 1)] : null,
                // Most start on the core step; a few on first steps, further or expert.
                'step' => [1, 2, 2, 2, 2, 2, 3, 3, 4][mt_rand(0, 8)],
                'weeks' => self::plan_weeks($weeks),
                'gems' => mt_rand(0, 3) * 5,
            ];
        }

        $course = create_course((object) [
            'fullname' => 'Tesapé demo: living things',
            'shortname' => $shortname,
            'category' => \core_course_category::get_default()->id,
            'format' => 'topics',
            'numsections' => 6,
            'enablecompletion' => 1,
        ]);
        $DB->insert_record(self::T . 'region', (object) [
            'courseid' => $course->id,
            'enabled' => 1,
            'family' => $family,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $tag = self::USERNAME_PREFIX . $course->id;
        $teacher = self::make_user($tag . 't', 'Teacher', 'Demo');
        self::enrol($course->id, $teacher->id, 'editingteacher');

        $result = (object) ['course' => $course, 'teacher' => $teacher, 'students' => [], 'teams' => []];
        $teams = (int) ceil($students / $teamsize);
        for ($t = 0; $t < $teams; $t++) {
            $groupid = groups_create_group((object) ['courseid' => $course->id, 'name' => self::TEAMS[$t % count(self::TEAMS)]]);
            $DB->insert_record(self::T . 'team', (object) [
                'groupid' => $groupid,
                'courseid' => $course->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $result->teams[] = $groupid;
        }

        foreach ($plan as $i => $student) {
            $user = self::make_user(sprintf('%s%02d', $tag, $i + 1), $student->firstname, $student->lastname);
            self::enrol($course->id, $user->id, 'student');
            groups_add_member($result->teams[$i % $teams], $user->id);
            // Teams are filled in turns, so each one gets the four roles.
            $charid = $DB->insert_record(self::T . 'char', (object) [
                'userid' => $user->id,
                'role' => self::ROLES[intdiv($i, $teams) % count(self::ROLES)],
                'nameform' => $student->nameform,
                'lineage' => $student->lineage,
                'gems' => $student->gems,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record(self::T . 'member', (object) [
                'charid' => $charid,
                'courseid' => $course->id,
                'step' => $student->step,
                'lastregen' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            self::make_progress($charid, $course->id, $student->weeks, $now);
            $result->students[] = $user;
        }
        return $result;
    }

    /**
     * The saber a student will get in each of some weeks: one to six gains a week, of 15 to 30 each, as scenes do.
     *
     * @param int $weeks
     * @return array[] For each week, a list of [amount, reason].
     */
    private static function plan_weeks(int $weeks): array {
        $plan = [];
        for ($w = 0; $w < $weeks; $w++) {
            $gains = [];
            for ($g = mt_rand(1, 6); $g > 0; $g--) {
                $gains[] = [5 * mt_rand(3, 6), mt_rand(0, 1) ? 'scene' : 'completion'];
            }
            $plan[] = $gains;
        }
        return $plan;
    }

    /**
     * Writes the planned weeks of saber in the log, with the weekly cap of the course, and sums them up.
     *
     * @param int $charid
     * @param int $courseid
     * @param array[] $weeks From plan_weeks().
     * @param int $now
     */
    private static function make_progress(int $charid, int $courseid, array $weeks, int $now): void {
        global $DB;
        if (!$weeks) {
            return;
        }
        $cap = (int) $DB->get_field(self::T . 'region', 'weeklycap', ['courseid' => $courseid]);
        $total = 0;
        foreach ($weeks as $w => $gains) {
            $earned = 0;
            $start = $now - (count($weeks) - $w) * WEEKSECS;
            foreach ($gains as $g => [$amount, $reason]) {
                $split = progress::apply_cap($amount, $earned, $cap);
                $DB->insert_record(self::T . 'ledger', (object) [
                    'charid' => $charid,
                    'courseid' => $courseid,
                    'resource' => 'saber',
                    'amount' => $split['granted'],
                    'reason' => $reason,
                    'capped' => $split['capped'],
                    'timecreated' => $start + $g * DAYSECS,
                ]);
                $earned += $split['granted'];
            }
            $total += $earned;
        }
        $DB->set_field(self::T . 'member', 'renown', $total, ['charid' => $charid, 'courseid' => $courseid]);
        $DB->update_record(self::T . 'char', (object) [
            'id' => $charid,
            'saber' => $total,
            'level' => progress::level_for_saber($total),
        ]);
    }

    /**
     * A made-up user who cannot log in until someone sets their password, and gets no email.
     *
     * @param string $username
     * @param string $firstname
     * @param string $lastname
     * @return \stdClass
     */
    private static function make_user(string $username, string $firstname, string $lastname): \stdClass {
        global $CFG, $DB;
        $id = user_create_user((object) [
            'username' => $username,
            'auth' => 'manual',
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $username . '@example.com',
            'emailstop' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'confirmed' => 1,
        ], false, false);
        return $DB->get_record('user', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Enrols a user with the manual method.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $archetype
     */
    private static function enrol(int $courseid, int $userid, string $archetype): void {
        $roles = get_archetype_roles($archetype);
        enrol_try_internal_enrol($courseid, $userid, reset($roles)->id);
    }

    /**
     * Shuffles with the seeded generator, so that a seed always gives the same class.
     *
     * @param array $items
     */
    private static function shuffle(array &$items): void {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }
}
