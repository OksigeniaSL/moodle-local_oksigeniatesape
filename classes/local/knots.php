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
 * The knots of the battle by turns: in each turn the Embrollo lets one go, with the minion that carries it, says what
 * it claims and which team it goes for, and the class undoes it with a lazada, a question of the mission. They come in
 * the mission (battle.knots), in the order of its chapters.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class knots {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** The engines of a lane the battle plays today (classify, errors and order come with the tablet client, 2.3). */
    public const ENGINES = ['choice'];

    /** Candles of the tower, and knots of each size of battle, when the mission does not say. */
    public const CANDLES = 5;

    /** @var int[] Knots of a short, a normal and a final battle. */
    public const SIZES = ['short' => 5, 'normal' => 8, 'final' => 10];

    /**
     * The knots of a mission that can be played in a course: each with the questions the course uses (an objective
     * the teacher left without competency takes its questions out) and its lanes, one for each team, with their engine.
     *
     * @param \stdClass $mission With data.
     * @param string[]|null $engines The engines that can be played (all of them: null).
     * @return array List of knots: code, name, claim, laugh, chapter, objective, weakness, why, questions (ids), lanes.
     */
    public static function usable(\stdClass $mission, ?array $engines = self::ENGINES): array {
        $ids = array_column(questions::usable($mission), 'id');
        $list = [];
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            $mine = array_values(array_intersect((array) ($knot['questions'] ?? []), $ids));
            // Its lanes, or else each of its questions is a lane to choose; a question the course does not use, out.
            $lanes = [];
            foreach ($knot['lanes'] ?? array_map(fn($q) => ['engine' => 'choice', 'question' => $q], $mine) as $lane) {
                $engine = (string) ($lane['engine'] ?? '');
                $playable = $engines === null || in_array($engine, $engines, true);
                if (!$playable || ($engine === 'choice' && !in_array($lane['question'] ?? '', $ids, true))) {
                    continue;
                }
                $lanes[] = $lane;
            }
            if ($lanes && !empty($knot['code'])) {
                $list[] = ['code' => (string) $knot['code'], 'name' => (string) ($knot['name'] ?? ''),
                    'claim' => (string) ($knot['claim'] ?? ''), 'laugh' => (string) ($knot['laugh'] ?? ''),
                    'chapter' => (int) ($knot['chapter'] ?? 0), 'objective' => (string) ($knot['objective'] ?? ''),
                    'weakness' => (string) ($knot['weakness'] ?? ''), 'why' => (string) ($knot['why'] ?? ''),
                    'questions' => array_values(array_filter(array_column($lanes, 'question'))), 'lanes' => $lanes];
            }
        }
        return $list;
    }

    /**
     * The lane of each team in a turn: one each, in order; if there are more teams than lanes, two share one. From one
     * turn to the next the lanes go round, so that a team does not always get the same engine.
     *
     * @param array $knot As usable() gives it.
     * @param int[] $teamids
     * @param int $turn From 0.
     * @return array Team id => lane.
     */
    public static function lanes(array $knot, array $teamids, int $turn = 0): array {
        $lanes = $knot['lanes'] ?? [];
        if (!$lanes) {
            return [];
        }
        $out = [];
        foreach (array_values($teamids) as $i => $teamid) {
            $out[(int) $teamid] = $lanes[($i + $turn) % count($lanes)];
        }
        return $out;
    }

    /**
     * The size of the battle: its candles and how many knots each size has, from the mission or the default ones.
     *
     * @param \stdClass $mission With data.
     * @return array candles, sizes (short, normal, final), phases (frayed, last: percent of threads left).
     */
    public static function rules(\stdClass $mission): array {
        $battle = $mission->data['battle'] ?? [];
        return [
            'candles' => max(1, (int) ($battle['candles'] ?? self::CANDLES)),
            'sizes' => array_map('intval', array_merge(self::SIZES, (array) ($battle['sizes'] ?? []))),
            'phases' => ['frayed' => (int) ($battle['phases']['frayed'] ?? 60), 'last' => (int) ($battle['phases']['last'] ?? 25)],
        ];
    }

    /**
     * The turns of a battle: as many knots as turns, spread over all of them in their order, each with one of its
     * questions (another one when a knot comes back).
     *
     * @param int $courseid
     * @param int $count Turns.
     * @param bool $final The final battle: the knots of the whole mission.
     * @return array List of [knot code, question id]; empty if the mission has no knots.
     */
    public static function plan(int $courseid, int $count, bool $final = false): array {
        global $DB;
        $mission = missions::get($courseid);
        $knots = $mission ? self::usable($mission) : [];
        if (!$knots || $count < 1) {
            return [];
        }
        // The knots of the places the class has reached: up to the one after the furthest it lit; the final, all of them.
        if (!$final) {
            $lit = $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ? AND litat > 0', [$mission->id]);
            $codes = array_column($mission->data['chapters'] ?? [], 'code');
            $reached = 1;
            foreach ($lit as $code) {
                $reached = max($reached, (int) array_search($code, $codes, true) + 2);
            }
            $near = array_values(array_filter($knots, fn($k) => !$k['chapter'] || $k['chapter'] <= $reached));
            $knots = $near ?: $knots;
        }
        $plan = [];
        $seen = [];
        for ($turn = 0; $turn < $count; $turn++) {
            $knot = $knots[intdiv($turn * count($knots), $count)];
            $again = $seen[$knot['code']] ?? 0;
            $seen[$knot['code']] = $again + 1;
            $plan[] = [$knot['code'], $knot['questions'][$again % count($knot['questions'])]];
        }
        return $plan;
    }

    /**
     * A knot, with its texts in the story of the mission.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @return array|null name, claim, laugh, weakness, why.
     */
    public static function get(\stdClass $mission, string $code): ?array {
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if (($knot['code'] ?? '') === $code) {
                return ['code' => $code, 'name' => missions::text($knot['name'] ?? '', $mission->skin),
                    'claim' => missions::text($knot['claim'] ?? '', $mission->skin),
                    'laugh' => missions::text($knot['laugh'] ?? '', $mission->skin),
                    'weakness' => missions::text($knot['weakness'] ?? '', $mission->skin),
                    'why' => missions::text($knot['why'] ?? '', $mission->skin)];
            }
        }
        return null;
    }
}
