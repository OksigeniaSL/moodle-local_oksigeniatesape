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
 * «Today in the valley»: what the mission proposes for the class sessions of the chapter the class is in (the scene to
 * play together, the challenge in the classroom, the materials, the minutes), the last letter of the Embrollo and the
 * mix-up that came up most. So that the teacher does not have to work out each day what comes next.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class today {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Today in the valley, for a course.
     *
     * @param int $courseid
     * @param bool $mixup With the mix-up that came up most (it reads all the plays: for the class screen, and kept for ten
     *     minutes).
     * @return array|null chapter, code, intro, offscreen, sessions, letter (optional), mixup (optional); null without a
     *     mission.
     */
    public static function get(int $courseid, bool $mixup = false): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        if (!$chapter) {
            return null;
        }
        $info = [];
        foreach ($mission->data['chapters'] ?? [] as $meta) {
            $info[$meta['code']] = $meta;
        }
        $meta = $info[$chapter->code] ?? [];
        $scenes = [];
        $summaries = scenes::summaries($mission);
        foreach ($DB->get_records_menu(self::T . 'scene', ['missionid' => $mission->id], '', 'code, id') as $code => $id) {
            $scenes[$code] = ['id' => (int) $id, 'title' => (string) ($summaries[$id]['title'] ?? $code)];
        }
        $sessions = [];
        foreach ($meta['sessions'] ?? [] as $i => $session) {
            $game = (string) ($session['game'] ?? '');
            $sessions[] = [
                'n' => $i + 1,
                'do' => missions::text($session['do'] ?? '', $mission->skin, $mission->variant),
                'materials' => missions::text($session['materials'] ?? '', $mission->skin, $mission->variant),
                'minutes' => (int) ($session['minutes'] ?? 0),
                'sceneid' => $scenes[$session['scene'] ?? '']['id'] ?? 0,
                'scene' => $scenes[$session['scene'] ?? '']['title'] ?? '',
                'game' => $game ? missions::text($mission->data['games'][$game]['title'] ?? $game, $mission->skin) : '',
                'final' => ($session['battle'] ?? '') === 'final',
            ];
        }
        $today = [
            'chapter' => missions::text($meta['name'] ?? $chapter->code, $mission->skin),
            'code' => $chapter->code,
            'intro' => missions::text($meta['intro'] ?? '', $mission->skin, $mission->variant),
            'offscreen' => missions::text($meta['offscreen'] ?? '', $mission->skin, $mission->variant),
            'sessions' => $sessions,
        ];
        // The letter the Embrollo left in the last place whose lantern the class lit.
        $order = 'litat DESC, sortorder DESC';
        $where = 'missionid = ? AND litat > 0';
        $lit = $DB->get_records_select(self::T . 'chapter', $where, [$mission->id], $order, 'id, code', 0, 1);
        $last = $lit ? reset($lit) : null;
        $letter = $last ? self::letter($mission, $last->code) : '';
        if ($letter !== '') {
            $today['letter'] = ['chapter' => missions::text($info[$last->code]['name'] ?? $last->code, $mission->skin),
                'text' => $letter, 'teaser' => self::teaser($mission, $last->code)];
        }
        // While a rematch is pending, the Embrollo boasts in its letter of the day.
        $region = regions::get($courseid);
        $gloat = $mission->data['battle']['lost']['letter'] ?? '';
        if (!empty($region->battlestate) && $gloat) {
            $today['gloat'] = missions::text($gloat, $mission->skin, $mission->variant);
        }
        // What the Embrollo stained, for the class to clean it (or for the teacher to take it as cleaned).
        $today['stains'] = array_values(array_filter(array_map(function ($stain) use ($mission) {
            $view = stains::view($stain, $mission);
            return $view ? ['id' => $view['id'], 'place' => $view['place'], 'title' => $view['title'], 'kind' => $view['kind']] :
                null;
        }, stains::open($courseid))));
        // And the scene the battle lost opens, to play it with the class.
        $lost = (string) ($mission->data['battle']['lost']['scene'] ?? '');
        if (!empty($region->battlestate) && isset($scenes[$lost])) {
            $today['lostscene'] = $scenes[$lost];
        }
        if ($mixup) {
            $cache = \cache::make('local_oksigeniatesape', 'mixups');
            $found = $cache->get($courseid);
            if ($found === false) {
                $context = nextclass::context($courseid);
                $first = $context['mixups'][0] ?? null;
                $found = $first ? ['question' => rtrim(trim((string) $first['question']), '.'),
                    'answer' => (string) $first['answer']] : 0;
                $cache->set($courseid, $found);
            }
            if ($found) {
                $today['mixup'] = $found;
            }
        }
        return $today;
    }

    /**
     * The letter the Embrollo leaves in a place of the mission when the class lights its lantern.
     *
     * @param \stdClass $mission With data.
     * @param string $chapter Code.
     * @return string Empty if the mission has none.
     */
    public static function letter(\stdClass $mission, string $chapter): string {
        $letter = $mission->data['letters'][$chapter] ?? '';
        return $letter ? missions::text($letter, $mission->skin, $mission->variant) : '';
    }

    /**
     * What waits in the next place, told under the letter of a place once the class lit it: «next time…».
     *
     * @param \stdClass $mission
     * @param string $chapter
     * @return string
     */
    public static function teaser(\stdClass $mission, string $chapter): string {
        $teaser = $mission->data['teasers'][$chapter] ?? '';
        return $teaser ? missions::text($teaser, $mission->skin, $mission->variant) : '';
    }
}
