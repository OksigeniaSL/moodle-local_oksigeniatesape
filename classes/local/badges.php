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
 * The badge of Moodle a mission brings: made in the course with the mission, not turned on until the teacher wants it,
 * and given by the game to whoever lights the lanterns of every place. The teacher can give it by hand too.
 *
 * The badge is found by its name in the course: a copy of the course restores its badges with other ids.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class badges {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

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
     * The name of the badge of a mission, in its skin.
     *
     * @param \stdClass $mission With data.
     * @return string Empty if the mission brings no badge.
     */
    public static function name(\stdClass $mission): string {
        $name = $mission->data['badge']['name'] ?? '';
        return trim(missions::text($name, (string) $mission->skin, (string) $mission->variant));
    }

    /**
     * The badge of the mission of a course, if it is there.
     *
     * @param int $courseid
     * @param \stdClass $mission With data.
     * @return int Its id, or 0.
     */
    public static function find(int $courseid, \stdClass $mission): int {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');
        $name = self::name($mission);
        if ($name === '') {
            return 0;
        }
        $where = 'courseid = ? AND type = ? AND name = ?';
        return (int) $DB->get_field_select('badge', 'id', $where, [$courseid, BADGE_TYPE_COURSE, $name], IGNORE_MULTIPLE);
    }

    /**
     * Makes the badge of the mission in its course, not turned on, if the site uses badges and it is not there yet.
     *
     * @param \stdClass $mission With data.
     * @return int Its id, or 0 when there is none.
     */
    public static function make(\stdClass $mission): int {
        global $CFG, $DB, $SITE, $USER;
        require_once($CFG->libdir . '/badgeslib.php');
        $courseid = (int) $mission->courseid;
        $name = self::name($mission);
        if (!self::enabled() || $name === '') {
            return 0;
        }
        $id = self::find($courseid, $mission);
        if ($id) {
            return $id;
        }
        $now = time();
        $author = !empty($USER->id) ? (int) $USER->id : (int) get_admin()->id;
        $text = $mission->data['badge']['description'] ?? $name;
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
            'courseid' => $courseid,
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
        badges_process_badge_image($badge, __DIR__ . '/../../pix/badge.png');
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
}
