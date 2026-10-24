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
 * A campaign of several courses: the Natural Science of 5th A and its Language, for instance. Each course plays its
 * mission, and the lanterns of all of them are seen together, on the class screen and on the map of the students: the
 * expedition is one. A teacher makes the campaign from their course and gives its code to the teachers of the others.
 * Nothing of anybody goes from one course to another: only the names of the courses and their lanterns.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class campaigns {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string The letters of a code: none that can be taken for another (no O, 0, I nor 1). */
    private const LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Makes a campaign from a course, which goes in it.
     *
     * @param int $courseid
     * @param string $name
     * @param int|null $now
     * @return string Its code, for the teachers of the other courses.
     */
    public static function create(int $courseid, string $name, ?int $now = null): string {
        global $DB;
        $region = self::region($courseid);
        $name = \core_text::substr(trim(clean_param($name, PARAM_TEXT)), 0, 100);
        if ($name === '') {
            throw new \invalid_parameter_exception('A campaign needs a name');
        }
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::LETTERS[random_int(0, strlen(self::LETTERS) - 1)];
            }
        } while ($DB->record_exists(self::T . 'campaign', ['code' => $code]));
        $now = $now ?? time();
        $id = $DB->insert_record(self::T . 'campaign', (object) ['code' => $code, 'name' => $name, 'timecreated' => $now,
            'timemodified' => $now]);
        self::move($region, (int) $id);
        return $code;
    }

    /**
     * A course goes into a campaign by its code.
     *
     * @param int $courseid
     * @param string $code
     */
    public static function join(int $courseid, string $code): void {
        global $DB;
        $region = self::region($courseid);
        $campaign = $DB->get_record(self::T . 'campaign', ['code' => \core_text::strtoupper(trim($code))]);
        if (!$campaign) {
            throw new \moodle_exception('nocampaign', 'local_oksigeniatesape');
        }
        self::move($region, (int) $campaign->id);
    }

    /**
     * A course leaves its campaign; the campaign goes when no course is left in it.
     *
     * @param int $courseid
     */
    public static function leave(int $courseid): void {
        self::move(self::region($courseid), 0);
    }

    /**
     * The campaign of a course: its name, its courses with their lanterns, and all the lanterns together; and its code,
     * for teachers. Null outside a campaign.
     *
     * @param int $courseid
     * @param bool $teacher
     * @return array|null name, code, regions (name, lit, total, here), lit, total.
     */
    public static function view(int $courseid, bool $teacher = false): ?array {
        global $DB;
        $region = regions::get($courseid);
        $campaign = $region && !empty($region->campaignid) ?
            $DB->get_record(self::T . 'campaign', ['id' => $region->campaignid]) : null;
        if (!$campaign) {
            return null;
        }
        $list = [];
        foreach ($DB->get_fieldset_select(self::T . 'region', 'courseid', 'campaignid = ? AND enabled = 1', [$campaign->id]) as $key) {
            $key = (int) $key;
            try {
                $name = games::name($key);
            } catch (\Throwable $e) {
                // A course no longer there.
                continue;
            }
            $mission = missions::get($key);
            $lit = $total = 0;
            if ($mission) {
                // A place the teacher skipped counts neither as lit nor as one more.
                $total = $DB->count_records(self::T . 'chapter', ['missionid' => $mission->id, 'skippedat' => 0]);
                $lit = $DB->count_records_select(
                    self::T . 'chapter',
                    'missionid = ? AND litat > 0 AND skippedat = 0',
                    [$mission->id]
                );
            }
            $list[] = ['name' => $name, 'lit' => $lit, 'total' => $total, 'here' => $key === $courseid];
        }
        usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
        return ['name' => format_string($campaign->name), 'code' => $teacher ? (string) $campaign->code : '', 'regions' => $list,
            'lit' => array_sum(array_column($list, 'lit')), 'total' => array_sum(array_column($list, 'total'))];
    }

    /**
     * The region of a course, which must be there.
     *
     * @param int $courseid
     * @return \stdClass
     */
    private static function region(int $courseid): \stdClass {
        $region = regions::get($courseid);
        if (!$region) {
            throw new \moodle_exception('nocampaign', 'local_oksigeniatesape');
        }
        return $region;
    }

    /**
     * A region goes into a campaign (0: out of any); the one it leaves goes if nobody is left in it.
     *
     * @param \stdClass $region
     * @param int $campaignid
     */
    private static function move(\stdClass $region, int $campaignid): void {
        global $DB;
        $old = (int) ($region->campaignid ?? 0);
        $DB->set_field(self::T . 'region', 'campaignid', $campaignid, ['id' => $region->id]);
        regions::forget();
        if ($old && $old !== $campaignid && !$DB->record_exists(self::T . 'region', ['campaignid' => $old])) {
            $DB->delete_records(self::T . 'campaign', ['id' => $old]);
        }
    }
}
