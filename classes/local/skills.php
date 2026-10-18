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

use core_competency\user_competency_course;

/**
 * Named skills: what the hero has learnt to do, one for each objective of the mission («Distinguish the living from the
 * non-living»). A skill lights when the scenes show the student masters its objective (enough answers, and they came
 * well), or when the teacher rates them proficient in its competency. Not a mark: no number, and only the student and
 * their teachers see it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class skills {
    /** @var int Answers of an objective, at the first try of its scenes, before the scenes can light its skill. */
    public const MIN = 3;

    /** @var float How well they have to come (the score of the competencies, from 0 to 1). */
    public const SCORE = 0.7;

    /**
     * The skills of a student in a course: each objective of the mission, with its name and whether it is lit. None if
     * the course turns them off.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return array Of code, name and lit.
     */
    public static function of(\stdClass $char, int $courseid): array {
        $mission = missions::get($courseid);
        $region = regions::get($courseid);
        if (!$mission || ($region && regions::rule($region, 'skills') < 1)) {
            return [];
        }
        // How the answers came at the first try of each scene, by objective.
        $tries = [];
        foreach (scenes::first_tries($courseid, (int) $char->id) as $attempt) {
            foreach (json_decode((string) $attempt->objectives, true) ?: [] as $code => $o) {
                foreach (competencies::how($o) + ['total' => (int) ($o['total'] ?? 0)] as $key => $count) {
                    $tries[$code][$key] = ($tries[$code][$key] ?? 0) + $count;
                }
            }
        }
        // And what the teacher rated proficient in the course.
        $proficient = [];
        $links = competencies::links($courseid);
        if (array_filter($links)) {
            $records = user_competency_course::get_records(['courseid' => $courseid, 'userid' => (int) $char->userid]);
            foreach ($records as $uc) {
                if ($uc->get('proficiency')) {
                    $proficient[(int) $uc->get('competencyid')] = true;
                }
            }
        }
        $skills = [];
        foreach ($mission->data['objectives'] ?? [] as $code => $name) {
            $o = $tries[$code] ?? [];
            $skills[] = [
                'code' => (string) $code,
                'name' => missions::text($mission->data['skills'][$code] ?? $name, $mission->skin),
                'lit' => !empty($proficient[(int) ($links[$code] ?? 0)]) || self::shown($o),
            ];
        }
        return $skills;
    }

    /**
     * Whether the scenes show an objective is mastered: enough answers, and they came well.
     *
     * @param array $o total, and first, helped, after and twice.
     * @return bool
     */
    public static function shown(array $o): bool {
        return (int) ($o['total'] ?? 0) >= self::MIN && competencies::score($o) >= self::SCORE;
    }
}
