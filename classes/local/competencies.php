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

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\course_competency;
use core_competency\evidence;
use core_competency\user_competency_course;

/**
 * The competencies of Moodle in the game. Each mission brings its own framework of Tesapé, installed in the site with
 * the mission; each objective of the mission leaves evidence in a competency, the one of Tesapé by default or any other
 * the teacher chooses (the framework of their school). The scenes leave evidence; the teacher rates.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class competencies {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Whether the site uses competencies.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return api::is_enabled();
    }

    /**
     * The framework of a mission in the site, made from its package the first time.
     *
     * @param \stdClass $mission With data.
     * @return competency_framework|null Null without competencies in the site or without a framework in the mission.
     */
    public static function framework(\stdClass $mission): ?competency_framework {
        global $CFG;
        $info = $mission->data['framework'] ?? null;
        if (!self::enabled() || !$info) {
            return null;
        }
        $existing = competency_framework::get_record(['idnumber' => $info['idnumber']]);
        if ($existing) {
            return $existing;
        }
        require_once($CFG->libdir . '/grade/grade_scale.php');
        $levels = $info['scale']['levels'];
        // A scale of the whole site (no course), as frameworks need.
        $scale = new \grade_scale(['name' => $info['scale']['name'], 'scale' => implode(',', $levels), 'courseid' => 0,
            'userid' => 0, 'description' => '']);
        $scale->insert();
        $configuration = [['scaleid' => (int) $scale->id]];
        foreach ($levels as $i => $level) {
            $position = $i + 1;
            $proficient = in_array($position, $info['scale']['proficient'], true);
            $default = $position === (int) $info['scale']['default'];
            if ($proficient || $default) {
                $configuration[] = ['id' => $position, 'scaledefault' => (int) $default, 'proficient' => (int) $proficient];
            }
        }
        // Made by the plugin, not by a user: the teacher who installs the mission need not manage frameworks of the site.
        $framework = new competency_framework(0, (object) [
            'shortname' => $info['name'],
            'idnumber' => $info['idnumber'],
            'description' => s($info['description']),
            'descriptionformat' => FORMAT_HTML,
            'scaleid' => (int) $scale->id,
            'scaleconfiguration' => json_encode($configuration),
            'contextid' => \context_system::instance()->id,
            'visible' => 1,
        ]);
        $framework->create();
        foreach ($info['competencies'] as $general) {
            $parent = self::make($framework, $general, 0);
            foreach ($general['children'] ?? [] as $child) {
                self::make($framework, $child, (int) $parent->get('id'), $levels);
            }
        }
        return $framework;
    }

    /**
     * One competency of a framework.
     *
     * @param competency_framework $framework
     * @param array $info idnumber, name, description and, for the concrete ones, what is seen at each level.
     * @param int $parentid
     * @param string[] $levels Names of the levels of the scale.
     * @return competency
     */
    private static function make(competency_framework $framework, array $info, int $parentid, array $levels = []): competency {
        $description = '<p>' . s($info['description']) . '</p>';
        if (!empty($info['levels'])) {
            $description .= '<ul>';
            foreach ($info['levels'] as $i => $text) {
                $description .= '<li><strong>' . s($levels[$i] ?? '') . '</strong>: ' . s($text) . '</li>';
            }
            $description .= '</ul>';
        }
        $competency = new competency(0, (object) [
            'shortname' => (isset($info['objective']) ? $info['objective'] . ' · ' : '') . $info['name'],
            'idnumber' => $info['idnumber'],
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'competencyframeworkid' => $framework->get('id'),
            'parentid' => $parentid,
        ]);
        $competency->create();
        return $competency;
    }

    /**
     * The competencies of the objectives of the mission of a course: made the first time, with the framework of the
     * mission, and added to the course.
     *
     * @param int $courseid
     * @return array objective => competency id (0: none).
     */
    public static function links(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || !self::enabled()) {
            return [];
        }
        $key = ['courseid' => $courseid, 'missionid' => $mission->id];
        $links = $DB->get_records_menu(self::T . 'objcomp', $key, '', 'objective, competencyid');
        $missing = array_diff(array_keys($mission->data['objectives'] ?? []), array_keys($links));
        if ($missing) {
            $framework = self::framework($mission);
            foreach ($missing as $code) {
                $competencyid = 0;
                foreach ($mission->data['framework']['competencies'] ?? [] as $general) {
                    foreach ($general['children'] ?? [] as $child) {
                        if (($child['objective'] ?? '') === $code && $framework) {
                            $where = ['idnumber' => $child['idnumber'], 'competencyframeworkid' => $framework->get('id')];
                            $found = competency::get_record($where);
                            $competencyid = $found ? (int) $found->get('id') : 0;
                        }
                    }
                }
                self::set($courseid, $code, $competencyid);
                $links[$code] = $competencyid;
            }
        }
        return $links;
    }

    /**
     * The competency an objective of the mission of a course leaves evidence in; it is added to the course.
     *
     * @param int $courseid
     * @param string $objective
     * @param int $competencyid 0: none.
     */
    public static function set(int $courseid, string $objective, int $competencyid): void {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || !isset($mission->data['objectives'][$objective])) {
            throw new \invalid_parameter_exception('Not an objective of the mission');
        }
        if ($competencyid && !competency::record_exists($competencyid)) {
            throw new \invalid_parameter_exception('Not a competency of the site');
        }
        $key = ['courseid' => $courseid, 'missionid' => $mission->id, 'objective' => $objective];
        $link = $DB->get_record(self::T . 'objcomp', $key);
        if ($link) {
            $DB->update_record(self::T . 'objcomp', (object) ['id' => $link->id, 'competencyid' => $competencyid,
                'timemodified' => time()]);
        } else {
            $DB->insert_record(self::T . 'objcomp', (object) ($key + ['competencyid' => $competencyid, 'timemodified' => time()]));
        }
        $linked = course_competency::record_exists_select('courseid = ? AND competencyid = ?', [$courseid, $competencyid]);
        if ($competencyid && !$linked) {
            $link = new course_competency(0, (object) ['courseid' => $courseid, 'competencyid' => $competencyid,
                'sortorder' => course_competency::count_records(['courseid' => $courseid])]);
            $link->create();
        }
    }

    /**
     * A play of a scene leaves evidence in the competencies of its objectives: what was right of what was asked.
     *
     * @param int $courseid
     * @param int $userid
     * @param \stdClass $scene
     * @param array $objectives Of the play: code => ok, total.
     * @param int $attempt
     */
    public static function evidence(int $courseid, int $userid, \stdClass $scene, array $objectives, int $attempt): void {
        if (!$objectives || !self::enabled()) {
            return;
        }
        $links = self::links($courseid);
        $context = \context_course::instance($courseid);
        $title = (string) ($scene->data['title'] ?? $scene->code);
        $url = (new \moodle_url('/local/oksigeniatesape/play.php', ['id' => $courseid]))->out(false);
        foreach ($objectives as $code => $o) {
            if (empty($links[$code]) || empty($o['total'])) {
                continue;
            }
            $a = ['scene' => $title, 'ok' => (int) $o['ok'], 'total' => (int) $o['total'], 'attempt' => $attempt];
            api::add_evidence(
                $userid,
                $links[$code],
                $context,
                evidence::ACTION_LOG,
                'evidence_scene',
                'local_oksigeniatesape',
                $a,
                false,
                $url
            );
        }
    }

    /**
     * What the teacher sees: the competency of each objective, the ones that can be chosen, and the grid of the class
     * with the share right at the first try and the rating of each student in the course.
     *
     * @param int $courseid
     * @return array enabled, objectives (code, name, competencyid, competency, levels), options (id, name, framework)
     *     and students (userid, fullname, cells: objective, percent, rating).
     */
    public static function view(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        $view = ['enabled' => self::enabled() && (bool) $mission, 'objectives' => [], 'options' => [], 'students' => []];
        if (!$view['enabled']) {
            return $view;
        }
        $links = self::links($courseid);
        $competencies = [];
        foreach (array_filter($links) as $id) {
            $competencies[$id] = new competency($id);
        }
        foreach ($mission->data['objectives'] ?? [] as $code => $name) {
            $id = (int) ($links[$code] ?? 0);
            $levels = [];
            if ($id) {
                $scale = $competencies[$id]->get_scale();
                $levels = array_values($scale->load_items());
            }
            $view['objectives'][] = ['code' => $code, 'name' => missions::text($name, $mission->skin), 'competencyid' => $id,
                'competency' => $id ? format_string($competencies[$id]->get('shortname')) : '', 'levels' => $levels];
        }
        // To choose from: the competencies of the course (also those of the school's own frameworks) and the
        // framework of the mission.
        $options = [];
        foreach (course_competency::list_competencies($courseid) as $competency) {
            $options[$competency->get('id')] = $competency;
        }
        $framework = self::framework($mission);
        if ($framework) {
            foreach (competency::get_records(['competencyframeworkid' => $framework->get('id')]) as $competency) {
                if ($competency->get('parentid')) {
                    $options[$competency->get('id')] = $competency;
                }
            }
        }
        $frameworks = [];
        foreach ($options as $id => $competency) {
            $fid = (int) $competency->get('competencyframeworkid');
            $frameworks[$fid] = $frameworks[$fid] ?? format_string((new competency_framework($fid))->get('shortname'));
            $view['options'][] = ['id' => (int) $id, 'name' => format_string($competency->get('shortname')),
                'framework' => $frameworks[$fid]];
        }
        // The share right at the first try of each scene, and the rating in the course.
        $ratings = [];
        foreach (user_competency_course::get_records(['courseid' => $courseid]) as $uc) {
            $ratings[$uc->get('userid')][$uc->get('competencyid')] = (int) $uc->get('grade');
        }
        foreach (view::students($courseid) as $user) {
            $char = characters::get((int) $user->id);
            $tries = [];
            if ($char) {
                $first = $DB->get_records(self::T . 'sceneattempt', ['charid' => $char->id, 'courseid' => $courseid,
                    'attempt' => 1], '', 'id, objectives');
                foreach ($first as $attempt) {
                    foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                        $tries[$code]['ok'] = ($tries[$code]['ok'] ?? 0) + (int) $o['ok'];
                        $tries[$code]['total'] = ($tries[$code]['total'] ?? 0) + (int) $o['total'];
                    }
                }
            }
            $cells = [];
            foreach ($view['objectives'] as $objective) {
                $o = $tries[$objective['code']] ?? null;
                $cells[] = ['objective' => $objective['code'],
                    'percent' => $o && $o['total'] ? (int) round(100 * $o['ok'] / $o['total']) : -1,
                    'rating' => (int) ($ratings[$user->id][$objective['competencyid']] ?? 0)];
            }
            $view['students'][] = ['userid' => (int) $user->id, 'fullname' => fullname($user), 'cells' => $cells];
        }
        return $view;
    }

    /**
     * The teacher rates a student in the competency of an objective, in the course.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $objective
     * @param int $grade Level of the scale, from 1.
     */
    public static function rate(int $courseid, int $userid, string $objective, int $grade): void {
        $links = self::links($courseid);
        if (empty($links[$objective]) || $grade < 1) {
            throw new \invalid_parameter_exception('Nothing to rate');
        }
        api::grade_competency_in_course($courseid, $userid, $links[$objective], $grade);
    }
}
