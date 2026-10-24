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
 * The badges of Moodle a mission brings: made in the course with the mission, not turned on until the teacher wants
 * them. The one of the mission goes to whoever lights the lanterns of every place; «Explorer of knowledge», where the
 * mission has a shop of the naturalist, to whoever earns three seals of curiosity. The teacher can give them by hand.
 *
 * The badge is found by its name in the course: a copy of the course restores its badges with other ids.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class badges {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Seals of curiosity for the badge «Explorer of knowledge». */
    public const EXPLORER_SEALS = 3;

    /**
     * Whether the site uses badges in courses.
     *
     * @return bool
     */
    public static function enabled(): bool {
        global $CFG;
        return !empty($CFG->enablebadges) && !empty($CFG->badges_allowcoursebadges);
    }

    /**
     * The name of a badge of a mission, in its skin.
     *
     * @param \stdClass $mission With data.
     * @param string $which mission, or explorer (that of the shop of the naturalist).
     * @return string Empty if the mission brings no such badge.
     */
    public static function name(\stdClass $mission, string $which = 'mission'): string {
        if ($which === 'explorer') {
            return empty($mission->data['curiosities']) ? '' : get_string('badge_explorer', 'local_oksigeniatesape');
        }
        $name = $mission->data['badge']['name'] ?? '';
        return trim(missions::text($name, (string) $mission->skin, (string) $mission->variant));
    }

    /**
     * The badge of the mission of a course, if it is there.
     *
     * @param int $courseid
     * @param \stdClass $mission With data.
     * @param string $which mission or explorer.
     * @return int Its id, or 0.
     */
    public static function find(int $courseid, \stdClass $mission, string $which = 'mission'): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');
        $name = self::name($mission, $which);
        if ($name === '') {
            return 0;
        }
        $where = 'courseid = ? AND type = ? AND name = ?';
        return (int) $DB->get_field_select('badge', 'id', $where, [games::course($courseid), BADGE_TYPE_COURSE, $name], IGNORE_MULTIPLE);
    }

    /**
     * Makes the badge of the mission in its course, not turned on, if the site uses badges and it is not there yet.
     *
     * @param \stdClass $mission With data.
     * @param string $which mission or explorer.
     * @return int Its id, or 0 when there is none.
     */
    public static function make(\stdClass $mission, string $which = 'mission'): int {
        global $CFG, $DB, $SITE, $USER;
        require_once($CFG->libdir . '/badgeslib.php');
        $courseid = (int) $mission->courseid;
        $name = self::name($mission, $which);
        if (!self::enabled() || $name === '') {
            return 0;
        }
        $id = self::find($courseid, $mission, $which);
        if ($id) {
            return $id;
        }
        $now = time();
        // Made by who sets up the game in the course; when the mission is brought up to date in the request of a student,
        // by the site.
        $context = games::context($courseid);
        $setup = !empty($USER->id) && has_capability('local/oksigeniatesape:manageregion', $context);
        $author = $setup ? (int) $USER->id : (int) get_admin()->id;
        $text = $which === 'explorer' ? get_string('badge_explorer_description', 'local_oksigeniatesape') :
            ($mission->data['badge']['description'] ?? $name);
        $description = missions::text($text, (string) $mission->skin, (string) $mission->variant);
        $id = (int) $DB->insert_record('badge', (object) [
            'name' => $name,
            'description' => $description,
            'timecreated' => $now,
            'timemodified' => $now,
            'usercreated' => $author,
            'usermodified' => $author,
            'issuername' => format_string($SITE->fullname),
            'issuerurl' => $CFG->wwwroot,
            'issuercontact' => '',
            'expiredate' => null,
            'expireperiod' => null,
            'type' => BADGE_TYPE_COURSE,
            'courseid' => games::course($courseid),
            'messagesubject' => get_string('badge_subject', 'local_oksigeniatesape'),
            'message' => get_string('badge_message', 'local_oksigeniatesape', $name),
            'attachment' => 1,
            'notification' => 0,
            'status' => BADGE_STATUS_INACTIVE,
            'version' => '1',
            'language' => (string) ($mission->data['lang'] ?? current_language()),
            'imagecaption' => $name,
        ]);
        $badge = new \core_badges\badge($id);
        // Moodle deletes the file it is given: a copy, never the medallion of the plugin.
        $copy = make_request_directory() . '/badge.png';
        copy(__DIR__ . '/../../pix/badge.png', $copy);
        badges_process_badge_image($badge, $copy);
        // Given by the game, or by the teacher by hand.
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_OVERALL, 'badgeid' => $id])
            ->save(['agg' => BADGE_CRITERIA_AGGREGATION_ANY]);
        $roles = ['agg' => BADGE_CRITERIA_AGGREGATION_ANY];
        foreach (['editingteacher', 'teacher'] as $archetype) {
            foreach (get_archetype_roles($archetype) as $role) {
                $roles['role_' . $role->id] = $role->id;
            }
        }
        \award_criteria::build(['criteriatype' => BADGE_CRITERIA_TYPE_MANUAL, 'badgeid' => $id])->save($roles);
        return $id;
    }

    /**
     * The badge of the mission of a course, just turned on, goes at once to every student who has already lit the
     * lanterns of every place. Never in the way of the teacher.
     *
     * @param int $courseid
     * @param int $badgeid The badge turned on: nothing happens if it is not the one of the mission.
     */
    public static function check_course(int $courseid, int $badgeid): void {
        global $DB;
        try {
            $mission = missions::get($courseid);
            $which = !$mission ? '' : (self::find($courseid, $mission) === $badgeid ? 'mission' :
                (self::find($courseid, $mission, 'explorer') === $badgeid ? 'explorer' : ''));
            if ($which === '') {
                return;
            }
            $sql = 'SELECT c.userid
                      FROM {' . self::T . 'member} m
                      JOIN {' . self::T . 'char} c ON c.id = m.charid
                     WHERE m.courseid = ? AND m.leftat = 0';
            foreach ($DB->get_fieldset_sql($sql, [$courseid]) as $userid) {
                if ($which === 'mission') {
                    self::check($courseid, (int) $userid);
                } else {
                    self::check_explorer($courseid, (int) $userid);
                }
            }
        } catch (\Throwable $e) {
            debugging('Tesapé could not give the badge of the mission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Gives «Explorer of knowledge» to a student with three seals of curiosity in a course, if the teacher turned it on.
     * Never in the way of the game.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function check_explorer(int $courseid, int $userid): void {
        global $CFG;
        if (!self::enabled()) {
            return;
        }
        try {
            require_once($CFG->libdir . '/badgeslib.php');
            $mission = missions::get($courseid);
            $char = characters::get($userid);
            $id = $mission && $char ? self::find($courseid, $mission, 'explorer') : 0;
            if (!$id) {
                return;
            }
            $badge = new \core_badges\badge($id);
            if (
                $badge->is_active() && !$badge->is_issued($userid) &&
                    count(curiosities::done($courseid, (int) $char->id)) >= self::EXPLORER_SEALS
            ) {
                $badge->issue($userid);
            }
        } catch (\Throwable $e) {
            debugging('Tesapé could not give the badge of the explorer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Gives the badge of the mission of a course to a student who has lit the lanterns of every place, if the teacher
     * turned it on. Never in the way of the game.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function check(int $courseid, int $userid): void {
        global $CFG, $DB;
        if (!self::enabled()) {
            return;
        }
        try {
            require_once($CFG->libdir . '/badgeslib.php');
            $mission = missions::get($courseid);
            $char = characters::get($userid);
            $id = $mission && $char ? self::find($courseid, $mission) : 0;
            if (!$id) {
                return;
            }
            $badge = new \core_badges\badge($id);
            if (!$badge->is_active() || $badge->is_issued($userid)) {
                return;
            }
            $places = $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$mission->id]);
            $where = 'charid = ? AND missionid = ? AND kind = ?';
            $lit = $DB->get_fieldset_select(self::T . 'found', 'code', $where, [$char->id, $mission->id, 'lantern']);
            if ($places && !array_diff($places, $lit)) {
                $badge->issue($userid);
            }
        } catch (\Throwable $e) {
            debugging('Tesapé could not give the badge of the mission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * The badge of the mission of a course for a student, when the teacher turned it on: whether they have it, to
     * celebrate it in the game. Null without a badge on.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null name, issued, image and url.
     */
    public static function for_student(int $courseid, int $userid): ?array {
        global $CFG;
        if (!self::enabled()) {
            return null;
        }
        try {
            require_once($CFG->libdir . '/badgeslib.php');
            $mission = missions::get($courseid);
            $id = $mission ? self::find($courseid, $mission) : 0;
            if (!$id) {
                return null;
            }
            $badge = new \core_badges\badge($id);
            if (!$badge->is_active()) {
                return null;
            }
            $context = $badge->get_context()->id;
            $image = \moodle_url::make_pluginfile_url($context, 'badges', 'badgeimage', $badge->id, '/', 'f3', false);
            return [
                'name' => self::name($mission),
                'issued' => $badge->is_issued($userid),
                'image' => $image->out(false),
                'url' => (new \moodle_url('/badges/mybadges.php'))->out(false),
            ];
        } catch (\Throwable $e) {
            debugging('Tesapé could not read the badge of the mission: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
