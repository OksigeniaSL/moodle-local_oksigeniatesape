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
    /**
     * The knots of a mission that can be played in a course: each with the questions the course uses (an objective
     * the teacher left without competency takes its questions out).
     *
     * @param \stdClass $mission With data.
     * @return array List of knots: code, name, claim, laugh, questions (ids).
     */
    public static function usable(\stdClass $mission): array {
        $ids = array_column(questions::usable($mission), 'id');
        $list = [];
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            $mine = array_values(array_intersect((array) ($knot['questions'] ?? []), $ids));
            if ($mine && !empty($knot['code'])) {
                $list[] = ['code' => (string) $knot['code'], 'name' => (string) ($knot['name'] ?? ''),
                    'claim' => (string) ($knot['claim'] ?? ''), 'laugh' => (string) ($knot['laugh'] ?? ''), 'questions' => $mine];
            }
        }
        return $list;
    }

    /**
     * The turns of a battle: as many knots as turns, spread over all of them in their order, each with one of its
     * questions (another one when a knot comes back).
     *
     * @param int $courseid
     * @param int $count Turns.
     * @return array List of [knot code, question id]; empty if the mission has no knots.
     */
    public static function plan(int $courseid, int $count): array {
        $mission = missions::get($courseid);
        $knots = $mission ? self::usable($mission) : [];
        if (!$knots || $count < 1) {
            return [];
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
     * @return array|null name, claim, laugh.
     */
    public static function get(\stdClass $mission, string $code): ?array {
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if (($knot['code'] ?? '') === $code) {
                return ['code' => $code, 'name' => missions::text($knot['name'] ?? '', $mission->skin),
                    'claim' => missions::text($knot['claim'] ?? '', $mission->skin),
                    'laugh' => missions::text($knot['laugh'] ?? '', $mission->skin)];
            }
        }
        return null;
    }
}
