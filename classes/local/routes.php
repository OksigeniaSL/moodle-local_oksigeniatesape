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
 * The route of the team: two ways into each place, with the same scenes and another arrival. The scout of the team
 * chooses it (anyone, if the team has no scout), once a place, while the class is in it; then the place tells the team how
 * it arrived, and the second way lists the games and the quests of the place the other way round.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class routes {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * The ways a team chose: place => route.
     *
     * @param int $groupid
     * @return array
     */
    public static function chosen(int $groupid): array {
        global $DB;
        return json_decode((string) $DB->get_field(self::T . 'team', 'routes', ['groupid' => $groupid]), true) ?: [];
    }

    /**
     * Whether a student can choose the way of their team: they are its scout, or the team has none that plays.
     *
     * @param int $courseid
     * @param int $groupid
     * @param \stdClass $char
     * @return bool
     */
    public static function chooser(int $courseid, int $groupid, \stdClass $char): bool {
        global $DB;
        if ($char->role === 'scout') {
            return true;
        }
        $charids = array_map(fn($m) => (int) $m->charid, teams::members($courseid, $groupid));
        if (!$charids) {
            return true;
        }
        [$insql, $params] = $DB->get_in_or_equal($charids);
        return !$DB->record_exists_select(self::T . 'char', "id $insql AND role = 'scout'", $params);
    }

    /**
     * The scout (or anyone, without one) chooses the way of the team into the place where the class is now, once.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param int|null $now
     * @return array The ways of the team: place => route.
     */
    public static function choose(int $courseid, int $userid, string $code, ?int $now = null): array {
        global $DB;
        $team = teams::of($courseid, $userid);
        $char = characters::get($userid);
        $mission = missions::get($courseid);
        $place = $mission ? pacts::chapter($mission) : null;
        $meta = [];
        foreach ($mission ? $mission->data['chapters'] ?? [] : [] as $chapter) {
            if ($place && (string) $chapter['code'] === (string) $place->code) {
                $meta = $chapter;
            }
        }
        if (!$team || !$char || !in_array($code, array_column($meta['routes'] ?? [], 'code'), true)) {
            throw new \invalid_parameter_exception('Not a way into the place');
        }
        if (!self::chooser($courseid, (int) $team->id, $char)) {
            throw new \moodle_exception('routescout', 'local_oksigeniatesape');
        }
        reserve::row($courseid, (int) $team->id, $now);
        $chosen = self::chosen((int) $team->id);
        if (isset($chosen[$place->code])) {
            throw new \moodle_exception('routechosen', 'local_oksigeniatesape');
        }
        $chosen[$place->code] = $code;
        $DB->set_field(self::T . 'team', 'routes', json_encode($chosen), ['groupid' => $team->id]);
        return $chosen;
    }

    /**
     * The way of a team into a place, for the student: the two ways and, once chosen, how the team arrived.
     *
     * @param \stdClass $mission
     * @param array $meta The place in the mission.
     * @param string $chosen The way the team chose there (empty: none yet).
     * @param bool $now Whether the class is in this place now.
     * @param bool $chooser Whether the student can choose.
     * @return array|null routes (code, name), chosen, arrival, now, chooser; null without ways or without a team.
     */
    public static function view(\stdClass $mission, array $meta, string $chosen, bool $now, bool $chooser): ?array {
        if (empty($meta['routes']) || ($chosen === '' && !$now)) {
            return null;
        }
        $text = fn($t) => missions::text($t ?? '', (string) $mission->skin, (string) $mission->variant);
        $arrival = '';
        foreach ($meta['routes'] as $route) {
            if ((string) $route['code'] === $chosen) {
                $arrival = $text($route['arrival']);
            }
        }
        return [
            'routes' => array_map(fn($r) => ['code' => (string) $r['code'], 'name' => $text($r['name'])], $meta['routes']),
            'chosen' => $chosen,
            'arrival' => $arrival,
            'now' => $now,
            'chooser' => $chooser,
        ];
    }
}
