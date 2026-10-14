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
 * The report of a class, for the teacher to take wherever they keep their marks (a spreadsheet, a gradebook, a PDF): one row
 * for each student with what they learnt and did in the mission. The game gives no marks: for each objective of the
 * mission, the share right at the first try of each scene. Nothing about behaviour (energy, commitments, powers).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class report {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * The columns of the report: key => heading.
     *
     * @param int $courseid
     * @return array
     */
    public static function columns(int $courseid): array {
        $s = fn(string $key) => get_string('report_' . $key, 'local_oksigeniatesape');
        $columns = ['lastname' => $s('lastname'), 'firstname' => $s('firstname'), 'team' => $s('team'),
            'step' => $s('step'), 'level' => $s('level'), 'renown' => $s('renown'), 'scenes' => $s('scenes'),
            'quests' => $s('quests')];
        $mission = missions::get($courseid);
        foreach ($mission->data['objectives'] ?? [] as $code => $name) {
            $a = ['code' => $code, 'name' => missions::text($name, $mission->skin)];
            $columns['objective:' . $code] = get_string('report_objective', 'local_oksigeniatesape', $a);
        }
        return $columns + ['challenges' => $s('challenges'), 'activities' => $s('activities'), 'pages' => $s('pages'),
            'cards' => $s('cards')];
    }

    /**
     * The report as a PDF, laid out to be read: landscape, short headings and a legend of the objectives.
     *
     * @param \stdClass $course
     * @return string The PDF.
     */
    public static function pdf(\stdClass $course): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');
        $columns = self::columns((int) $course->id);
        $mission = missions::get((int) $course->id);
        $heads = [];
        $legend = [];
        foreach (array_keys($columns) as $key) {
            if (strpos($key, 'objective:') === 0) {
                $code = substr($key, strlen('objective:'));
                $heads[] = $code;
                $name = missions::text($mission->data['objectives'][$code] ?? '', $mission->skin);
                $legend[] = '<b>' . s($code) . '</b> ' . s($name);
            } else {
                $heads[] = get_string('report_short_' . $key, 'local_oksigeniatesape');
            }
        }
        $html = '<h1>' . s(get_string('teach_report', 'local_oksigeniatesape')) . ' · ' . s(format_string($course->fullname)) .
            '</h1><p>' . s(userdate(time(), get_string('strftimedate', 'langconfig'))) . ($mission ? ' · ' .
            s(missions::text($mission->data['title'] ?? $mission->code, $mission->skin)) : '') . '</p><p style="font-size:8pt">' .
            s(get_string('report_pdf_legend', 'local_oksigeniatesape')) . '<br>' . implode(' · ', $legend) . '</p>' .
            '<table border="1" cellpadding="3" style="font-size:8pt"><thead><tr style="background-color:#e8edf2;font-weight:bold">';
        // Names and teams wider; numbers narrow, sharing what is left.
        $wide = ['lastname' => 10, 'firstname' => 8, 'team' => 10];
        $narrow = (100 - array_sum($wide)) / max(1, count($columns) - count($wide));
        foreach (array_keys($columns) as $i => $key) {
            $html .= '<th width="' . round($wide[$key] ?? $narrow, 2) . '%">' . s($heads[$i]) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach (self::rows((int) $course->id) as $row) {
            $html .= '<tr>';
            foreach (array_values($row) as $i => $cell) {
                $key = array_keys($columns)[$i];
                $html .= '<td width="' . round($wide[$key] ?? $narrow, 2) . '%">' . s((string) $cell) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        $pdf = new \pdf('L', 'mm', 'A4');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetFont('freesans', '', 10);
        $pdf->AddPage();
        $pdf->writeHTML($html);
        return $pdf->Output('', 'S');
    }

    /**
     * The rows of the report, one for each student, in the order of the columns.
     *
     * @param int $courseid
     * @return array[]
     */
    public static function rows(int $courseid): array {
        global $DB;
        $columns = array_keys(self::columns($courseid));
        $mission = missions::get($courseid);
        $region = regions::get($courseid);
        $scenes = $mission ? $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], '', 'id, quest') : [];
        $challenges = $DB->get_fieldset_select(self::T . 'challenge', 'id', 'courseid = ? AND cmid = 0', [$courseid]);
        $tracked = $DB->get_fieldset_select(self::T . 'challenge', 'cmid', 'courseid = ? AND cmid > 0', [$courseid]);
        $rows = [];
        foreach (view::students($courseid) as $user) {
            $row = array_fill_keys($columns, '');
            $row['lastname'] = $user->lastname;
            $row['firstname'] = $user->firstname;
            $groups = groups_get_all_groups($courseid, $user->id, (int) ($region->groupingid ?? 0));
            $row['team'] = $groups ? format_string(reset($groups)->name) : '';
            if ($tracked) {
                [$insql, $params] = $DB->get_in_or_equal($tracked);
                $row['activities'] = $DB->count_records_select(
                    'course_modules_completion',
                    "userid = ? AND completionstate > 0 AND coursemoduleid $insql",
                    array_merge([$user->id], $params)
                );
            }
            $char = characters::get((int) $user->id);
            $member = $char ? $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]) : null;
            if (!$member) {
                $rows[] = array_values($row);
                continue;
            }
            $row['step'] = (int) $member->step;
            $row['level'] = (int) $char->level;
            $row['renown'] = (int) $member->renown;
            // The first try of each scene: what the student knew before the scene told them.
            $objectives = [];
            foreach (scenes::first_tries($courseid, (int) $char->id) as $attempt) {
                foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                    $objectives[$code]['ok'] = ($objectives[$code]['ok'] ?? 0) + (int) $o['ok'];
                    $objectives[$code]['total'] = ($objectives[$code]['total'] ?? 0) + (int) $o['total'];
                }
            }
            foreach ($objectives as $code => $o) {
                if (array_key_exists('objective:' . $code, $row) && $o['total'] > 0) {
                    $row['objective:' . $code] = (int) round(100 * $o['ok'] / $o['total']);
                }
            }
            $done = $mission ? $DB->get_fieldset_select(
                self::T . 'found',
                'code',
                "charid = ? AND missionid = ? AND kind = 'scene'",
                [$char->id, $mission->id]
            ) : [];
            $codes = $mission ? $DB->get_records_menu(self::T . 'scene', ['missionid' => $mission->id], '', 'code, id') : [];
            $row['scenes'] = 0;
            $row['quests'] = 0;
            foreach ($done as $code) {
                $quest = !empty($scenes[$codes[$code] ?? 0]->quest);
                $row[$quest ? 'quests' : 'scenes']++;
            }
            if ($challenges) {
                [$insql, $params] = $DB->get_in_or_equal($challenges);
                $row['challenges'] = $DB->count_records_select(
                    self::T . 'validation',
                    "charid = ? AND status IN ('done', 'merit') AND challengeid $insql",
                    array_merge([$char->id], $params)
                );
            }
            $row['pages'] = $DB->count_records(self::T . 'daily', ['charid' => $char->id, 'courseid' => $courseid,
                'success' => 1]);
            $album = album::for_student($courseid, (int) $user->id);
            $row['cards'] = $album ? count(array_filter($album['cards'], fn($c) => $c['mine'])) : '';
            $rows[] = array_values($row);
        }
        return $rows;
    }
}
