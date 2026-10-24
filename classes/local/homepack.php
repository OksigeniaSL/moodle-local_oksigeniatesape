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
 * The pack for home: the place where the class is now, on paper, for the student who follows it without a connection.
 * For each scene of their step, the story up to the first decision and then every decision, with boxes to mark, lines to
 * write the group of each thing or the order of the steps; then the words of the glossary that come up, and what to talk
 * about at home. Back in class, the student plays the scenes knowing what they chose. Nothing is kept.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class homepack {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Decisions with options to mark. */
    private const PICK = ['choice', 'use', 'justify', 'tend', 'find'];

    /**
     * The pack of a student: the place where the class is now and its scenes for their step, or null without a mission.
     *
     * @param int $courseid
     * @param int $userid
     * @return array|null name, place, intro, scenes (title, whatif, story, decisions (prompt, kind, items, groups)),
     *     words (word, text), home.
     */
    public static function pack(int $courseid, int $userid): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        if (!$chapter) {
            return null;
        }
        $skin = (string) $mission->skin;
        $variant = (string) $mission->variant;
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $char = characters::get($userid);
        $member = $char ? $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]) : null;
        $info = [];
        foreach ($mission->data['chapters'] ?? [] as $c) {
            if ((string) $c['code'] === (string) $chapter->code) {
                $info = $c;
            }
        }
        $creature = $char ? (json_decode((string) $char->creature, true)['name'] ?? '') : '';
        $names = [
            '{{creature}}' => $creature !== '' ? $creature : get_string('app_creature', 'local_oksigeniatesape'),
            '{{name}}' => $char && $char->nickname ? $char->nickname : $user->firstname,
        ];
        $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id, 'chapterid' => $chapter->id], 'code');
        $out = [
            'name' => $user->firstname,
            'place' => missions::text($info['name'] ?? $chapter->code, $skin, $variant),
            'intro' => missions::text($info['intro'] ?? '', $skin, $variant),
            'scenes' => [],
            'words' => [],
            'home' => array_values(array_filter(array_map(
                fn($t) => missions::text($t, $skin, $variant),
                (array) ($info['home'] ?? [])
            ))),
        ];
        $all = '';
        foreach (scenes::for_step($scenes, (int) ($member->step ?? scenes::CORE_STEP)) as $scene) {
            $def = edits::scene($scene, $mission);
            $text = fn($t) => trim(strtr(edits::resolve($t ?? '', $skin, $variant, $def['locals'] ?? []), $names));
            $walked = self::walk($def, $text, $char ? (string) $char->role : '', $char ? characters::nature($char) : []);
            $out['scenes'][] = ['title' => $text($def['title'] ?? $scene->code), 'whatif' => $text($def['whatif'] ?? '')] + $walked;
            $all .= ' ' . implode(' ', $walked['story']) . ' ' . implode(' ', array_map(fn($d) => $d['prompt'] . ' ' .
                implode(' ', $d['items']), $walked['decisions']));
        }
        // The words of the glossary that come up in the pack, with what they mean.
        $all = \core_text::strtolower($all);
        foreach ($mission->data['glossary'] ?? [] as $word) {
            foreach ((array) ($word['forms'] ?? []) as $form) {
                $form = \core_text::strtolower((string) $form);
                if (preg_match('/(?<![\p{L}])' . preg_quote($form, '/') . '(?![\p{L}])/u', $all)) {
                    $out['words'][] = ['word' => $form, 'text' => missions::text($word['text'] ?? '', $skin, $variant)];
                    break;
                }
            }
        }
        return $out;
    }

    /**
     * A scene read along its main way: the story up to the first decision, then each decision met, until its end. Of the
     * options, those the student would see alone: those of their role and their words, not those that need a thing
     * carried or something done before.
     *
     * @param array $def
     * @param callable $text
     * @param string $role
     * @param string[] $natures
     * @return array story (texts), decisions (prompt, kind (pick, sort, order), items, groups).
     */
    private static function walk(array $def, callable $text, string $role, array $natures): array {
        $nodes = $def['nodes'] ?? [];
        $label = fn($x) => $text($x['label'] ?? $x['text'] ?? '');
        $theirs = function ($option) use ($role, $natures): bool {
            $needs = (array) ($option['requires'] ?? []);
            if (!empty($option['item']) || isset($needs['item']) || isset($needs['flag'])) {
                return false;
            }
            $as = $needs['role'] ?? $needs['teamrole'] ?? null;
            return ($as === null || $as === $role) && (!isset($needs['nature']) || in_array($needs['nature'], $natures, true));
        };
        $story = [];
        $decisions = [];
        $id = $def['start'] ?? array_key_first($nodes);
        $seen = [];
        while ($id !== null && isset($nodes[$id]) && !isset($seen[$id])) {
            $seen[$id] = true;
            $node = $nodes[$id];
            $type = (string) ($node['type'] ?? '');
            if ($type === 'ending') {
                break;
            }
            if ($type === 'story' && !$decisions) {
                $story[] = $text($node['text'] ?? '');
            } else if (in_array($type, self::PICK, true)) {
                $list = array_filter($node['options'] ?? $node['items'] ?? $node['spots'] ?? [], $theirs);
                $decisions[] = ['prompt' => $text($node['prompt'] ?? ''), 'kind' => 'pick',
                    'items' => array_values(array_filter(array_map($label, $list))), 'groups' => []];
            } else if ($type === 'classify') {
                $decisions[] = ['prompt' => $text($node['prompt'] ?? ''), 'kind' => 'sort',
                    'items' => array_values(array_filter(array_map($label, $node['items'] ?? []))),
                    'groups' => array_values(array_filter(array_map($label, $node['bins'] ?? [])))];
            } else if ($type === 'order' || $type === 'path') {
                // The steps, not in their order: putting them in order is the task.
                $steps = array_values(array_filter(array_map($label, $node['steps'] ?? [])));
                \core_collator::asort($steps);
                $decisions[] = ['prompt' => $text($node['prompt'] ?? ''), 'kind' => 'order', 'items' => array_values($steps),
                    'groups' => []];
            }
            // Along the main way: the next node, the else of a branch, or where the first option or state leads.
            $id = $type === 'branch' ? ($node['else'] ?? null) : ($node['next'] ?? $node['options'][0]['next'] ??
                $node['states'][0]['next'] ?? null);
        }
        return ['story' => array_values(array_filter($story)), 'decisions' => $decisions];
    }

    /**
     * The pack of a student as a PDF, to print.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return string The PDF.
     */
    public static function pdf(\stdClass $course, int $userid): string {
        $pack = self::pack((int) $course->id, $userid);
        $str = fn(string $key, $a = null) => s(get_string('homepack_' . $key, 'local_oksigeniatesape', $a));
        // A box to mark, drawn: the fonts of the PDF have no sign for it.
        $box = fn(string $item) => '<tr><td width="4mm" style="border:1px solid #444"></td><td width="164mm">' . s($item) .
            '</td></tr>';
        $html = '';
        if (!$pack) {
            $html = '<p>' . $str('none') . '</p>';
        } else {
            $html = '<h1 style="font-size:18pt">' . $str('title', $pack['place']) . '</h1><p style="color:#555">' .
                s(format_string($course->fullname)) . ' · ' . s($pack['name']) . '</p><p>' . $str('help') . '</p>' .
                ($pack['intro'] !== '' ? '<p><i>' . s($pack['intro']) . '</i></p>' : '');
            foreach ($pack['scenes'] as $i => $scene) {
                $html .= '<h2 style="font-size:14pt;color:#2b4a63">' . ($i + 1) . '. ' . s($scene['title']) . '</h2>' .
                    ($scene['whatif'] !== '' ? '<p><i>' . s($scene['whatif']) . '</i></p>' : '');
                foreach ($scene['story'] as $piece) {
                    $html .= '<p>' . s($piece) . '</p>';
                }
                foreach ($scene['decisions'] as $d) {
                    $html .= '<p><b>' . s($d['prompt']) . '</b></p>';
                    if ($d['kind'] === 'sort') {
                        $html .= '<p style="font-size:9pt">' . $str('sort', implode(' · ', $d['groups'])) . '</p><table ' .
                            'cellpadding="4">' . implode('', array_map(fn($item) => '<tr><td width="45%">' . s($item) .
                            '</td><td width="55%">______________________</td></tr>', $d['items'])) . '</table>';
                    } else if ($d['kind'] === 'order') {
                        $html .= '<p style="font-size:9pt">' . $str('order') . '</p>' . implode('', array_map(fn($item) =>
                            '<p>____ ' . s($item) . '</p>', $d['items']));
                    } else {
                        $html .= '<table cellspacing="5" cellpadding="1">' . implode('', array_map($box, $d['items'])) . '</table>';
                    }
                }
            }
            if ($pack['words']) {
                $html .= '<h2 style="font-size:14pt;color:#2b4a63">' . $str('words') . '</h2>' . implode('', array_map(fn($w) =>
                    '<p><b>' . s(\core_text::strtoupper(\core_text::substr($w['word'], 0, 1)) . \core_text::substr($w['word'], 1)) .
                    ':</b> ' . s($w['text']) . '</p>', $pack['words']));
            }
            if ($pack['home']) {
                $html .= '<h2 style="font-size:14pt;color:#2b4a63">' . $str('home') . '</h2>' . implode('', array_map(fn($q) =>
                    '<p>' . s($q) . '</p>', $pack['home']));
            }
            $html .= '<p style="font-size:9pt;color:#555">' . $str('back') . '</p>';
        }
        $pdf = paper::pdf();
        $pdf->SetMargins(16, 14, 16);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $pdf->writeHTML($html);
        return $pdf->Output('', 'S');
    }
}
