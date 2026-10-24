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
 * The summary for the family of a student: what their hero lived in the mission, what they are learning in words (not
 * numbers), the living things they met, what to talk about at home and what comes next. Nothing that compares them with
 * anybody: no marks, no gems, no position in the class, no adaptations. Made when the teacher asks for it, never kept.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class families {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Most living things named in a summary: the rest, counted. */
    private const BEINGS = 8;

    /**
     * The summary of a student of a course with a mission, or null if the course has none.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null name, hero, nature, mission, places (name, lit), learning (name, how), beings (names), more,
     *     home (questions), pacts, next.
     */
    public static function summary(int $courseid, int $userid): ?array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $skin = (string) $mission->skin;
        $variant = (string) $mission->variant;
        $text = fn($t) => missions::text($t ?? '', $skin, $variant);
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $char = characters::get($userid);
        $out = [
            'name' => $user->firstname,
            'hero' => $char && $char->nickname ? (string) $char->nickname : '',
            'nature' => '',
            'mission' => $text($mission->data['title'] ?? $mission->code),
            'places' => [],
            'learning' => [],
            'beings' => [],
            'more' => 0,
            'home' => [],
            'pacts' => [],
            'next' => '',
        ];
        if ($char) {
            $form = $char->nameform === 'f' ? 'f' : 'm';
            $words = array_map(
                fn($w) => get_string('app_natureword_' . $w . '_' . $form, 'local_oksigeniatesape'),
                characters::nature($char)
            );
            $both = count($words) > 1 ? ['a' => $words[0], 'b' => $words[1]] : null;
            $out['nature'] = $both ? get_string('app_nature_and', 'local_oksigeniatesape', $both) : (string) ($words[0] ?? '');
        }
        // The places of the expedition: those the class lit (not those it passed by), and the one it is in now.
        $records = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], '', 'code, litat, skippedat');
        $now = pacts::chapter($mission);
        $here = $now ? (string) $now->code : '';
        $seen = false;
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            $code = (string) $chapter['code'];
            $lit = !empty($records[$code]->litat) && empty($records[$code]->skippedat);
            $out['places'][] = ['name' => $text($chapter['name'] ?? $code), 'lit' => $lit, 'now' => $code === $here && !$lit];
            if ($code === $here) {
                $out['home'] = array_values(array_filter(array_map($text, (array) ($chapter['home'] ?? []))));
                $out['pacts'] = array_values(array_filter(array_map($text, (array) ($chapter['pacts'] ?? []))));
                $seen = true;
            } else if ($seen && empty($records[$code]->litat) && $out['next'] === '') {
                $out['next'] = $text($chapter['name'] ?? $code);
            }
        }
        if (!$char) {
            return $out;
        }
        // What they are learning: from the first try of each scene, in words.
        $objectives = [];
        foreach (scenes::first_tries($courseid, (int) $char->id) as $attempt) {
            foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                $objectives[$code]['ok'] = ($objectives[$code]['ok'] ?? 0) + (int) ($o['ok'] ?? 0);
                $objectives[$code]['total'] = ($objectives[$code]['total'] ?? 0) + (int) ($o['total'] ?? 0);
            }
        }
        foreach ($mission->data['objectives'] ?? [] as $code => $name) {
            $o = $objectives[$code] ?? ['ok' => 0, 'total' => 0];
            if ($o['total'] > 0) {
                $out['learning'][] = ['name' => $text($name), 'how' => self::how($o['ok'] / $o['total'])];
            }
        }
        // The living things they met, by name.
        $names = [];
        foreach ($mission->data['encounters'] ?? [] as $being) {
            $names[(string) ($being['id'] ?? '')] = $text($being['name'] ?? '');
        }
        $found = array_values(array_filter(array_map(fn($c) => $names[$c] ?? '', $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            "charid = ? AND missionid = ? AND kind = 'being'",
            [$char->id, $mission->id]
        ))));
        $out['beings'] = array_slice($found, 0, self::BEINGS);
        $out['more'] = max(0, count($found) - self::BEINGS);
        return $out;
    }

    /**
     * How an objective is going, in words: the share right at the first try never reaches the family as a number.
     *
     * @param float $share Between 0 and 1.
     * @return string
     */
    public static function how(float $share): string {
        $key = $share >= 0.8 ? 'clear' : ($share >= 0.5 ? 'good' : 'working');
        return get_string('family_how_' . $key, 'local_oksigeniatesape');
    }

    /**
     * The summaries of some students (all of them if none is given) as a PDF, one page each, to print or send.
     *
     * @param \stdClass $course
     * @param int[] $userids
     * @return string The PDF.
     */
    public static function pdf(\stdClass $course, array $userids = []): string {
        $students = view::students((int) $course->id);
        if ($userids) {
            $students = array_filter($students, fn($u) => in_array((int) $u->id, $userids, true));
        }
        $pdf = paper::pdf();
        $pdf->SetMargins(16, 14, 16);
        $pdf->SetAutoPageBreak(true, 14);
        $date = userdate(time(), get_string('strftimedate', 'langconfig'));
        foreach ($students as $user) {
            $summary = self::summary((int) $course->id, (int) $user->id);
            if (!$summary) {
                continue;
            }
            $pdf->AddPage();
            $pdf->writeHTML(self::page($summary, format_string($course->fullname), $date));
        }
        if (!$pdf->getNumPages()) {
            $pdf->AddPage();
            $pdf->writeHTML('<p>' . s(get_string('family_none', 'local_oksigeniatesape')) . '</p>');
        }
        return $pdf->Output('', 'S');
    }

    /**
     * One page of the summaries.
     *
     * @param array $s A summary.
     * @param string $course
     * @param string $date
     * @return string HTML for the PDF.
     */
    private static function page(array $s, string $course, string $date): string {
        $str = fn(string $key, $a = null) => s(get_string('family_' . $key, 'local_oksigeniatesape', $a));
        $h2 = fn(string $key) => '<h2 style="font-size:13pt;color:#2b4a63">' . $str($key) . '</h2>';
        $list = fn(array $items) => '<ul>' . implode('', array_map(fn($i) => '<li>' . $i . '</li>', $items)) . '</ul>';
        $html = '<h1 style="font-size:18pt">' . $str('title', $s['name']) . '</h1><p style="color:#555">' . s($course) . ' · ' .
            s($s['mission']) . ' · ' . s($date) . '</p><p>' . $str('what') . '</p>';
        if ($s['hero'] !== '' || $s['nature'] !== '') {
            $html .= '<p>' . ($s['hero'] !== '' ? $str('hero', $s['hero']) . ' ' : '') .
                ($s['nature'] !== '' ? $str('nature', $s['nature']) : '') . '</p>';
        }
        $html .= $h2('places') . $list(array_map(fn($p) => s($p['name']) . ($p['lit'] ? ' · ' . $str('lit') : ($p['now'] ?
            ' · <i>' . $str('now') . '</i>' : '')), $s['places']));
        if ($s['learning']) {
            $html .= $h2('learning') . $list(array_map(fn($l) => s($l['name']) . ': <b>' . s($l['how']) . '</b>', $s['learning']));
        }
        if ($s['beings']) {
            $html .= $h2('beings') . '<p>' . s(implode(', ', $s['beings'])) . ($s['more'] ? ' ' . $str('more', $s['more']) : '') .
                '</p>';
        }
        if ($s['home']) {
            $html .= $h2('home') . '<p>' . $str('home_help') . '</p>' . $list(array_map('s', $s['home']));
        }
        if ($s['pacts']) {
            $html .= $h2('pacts') . $list(array_map('s', $s['pacts']));
        }
        if ($s['next'] !== '') {
            $html .= $h2('next') . '<p>' . $str('next_place', $s['next']) . '</p>';
        }
        return $html . $h2('teacher') . '<p>' . str_repeat('<br>', 4) . '</p><p style="font-size:9pt;color:#555">' .
            $str('fair') . '</p>';
    }
}
