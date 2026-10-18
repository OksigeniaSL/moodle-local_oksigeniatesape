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
 * Regions: the game in a course, with the settings its teacher chooses.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class regions {
    /** @var string Table of the regions. */
    private const TABLE = 'local_oksigeniatesape_region';

    /** @var string[] Families of role names. */
    public const FAMILIES = ['animals', 'dinosaurs', 'storyheroes', 'epic', 'elements', 'nomagic'];

    /** @var array The touches with a reason a course starts with: language string, what it gives and how much. */
    public const REASONS = [
        ['touch_team', 'saber', 10],
        ['touch_check', 'saber', 10],
        ['touch_question', 'saber', 5],
        ['touch_explain', 'saber', 5],
        ['touch_mention', 'gems', 1],
        ['touch_breathe', 'breath', 1],
    ];

    /** @var int[] What a touch with a reason gives at most, by what it gives. */
    public const REASON_MAX = ['saber' => 50, 'gems' => 3, 'breath' => 2];

    /** @var int How many touches with a reason a course keeps. */
    public const REASONS_MAX = 12;

    /** @var array Values of the automatic rules when the teacher has not changed them. */
    public const RULES = [
        // Saber for completing an activity that is not marked as a challenge (0: only challenges count).
        'completion' => 10,
    ];

    /**
     * The region of a course, if the game was ever set up there.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get(int $courseid): ?\stdClass {
        global $DB;
        // Read once in each request: many parts of the world of a student ask for it.
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'regions');
        $kept = $cache->get($courseid);
        if ($kept === false) {
            $kept = $DB->get_record(self::TABLE, ['courseid' => $courseid]) ?: 0;
            $cache->set($courseid, $kept);
        }
        return $kept ? clone $kept : null;
    }

    /**
     * Forgets the regions read in this request: one changed.
     */
    public static function forget(): void {
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'regions')->purge();
    }

    /**
     * Whether the game is on in a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function enabled(int $courseid): bool {
        $region = self::get($courseid);
        return $region && $region->enabled;
    }

    /**
     * A time zone of the settings as PHP knows it: 99 is the one of the site.
     *
     * @param string $zone
     * @return \DateTimeZone
     */
    public static function zone(string $zone): \DateTimeZone {
        if ($zone === '' || $zone === '99') {
            return \core_date::get_server_timezone_object();
        }
        return new \DateTimeZone(\core_date::normalise_timezone($zone));
    }

    /**
     * Turns the game on or off in a course, or changes its settings. What is not given keeps its value.
     *
     * @param int $courseid
     * @param array $settings Any of enabled, family, groupingid, weeklycap, dailymanualcap, dailyenergylosscap,
     *     leaderboard, nicknames, timezone (99: the one of the site), rules (an array), portal (the cover of the course
     *     page), hideacts (the activities of the mission only inside the valley), quiet (no sound on the devices of the
     *     students), devices (how the class plays: with a tablet each, or without devices), devicemusic (music on
     *     those devices: 0 never, 1 only in battles, 2 always), mischief (what the Embrollo does every day: off, calm or
     *     naughty) and look (the theme of the class: night, day, or empty for the one of the world).
     * @param int|null $now
     * @return \stdClass
     */
    public static function save(int $courseid, array $settings, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $region = self::get($courseid) ?? (object) [
            'courseid' => $courseid,
            'enabled' => 0,
            'family' => 'storyheroes',
            'timecreated' => $now,
        ];
        foreach (['enabled', 'leaderboard', 'quiet', 'devices'] as $flag) {
            if (array_key_exists($flag, $settings)) {
                $region->$flag = $settings[$flag] ? 1 : 0;
            }
        }
        if (array_key_exists('devicemusic', $settings)) {
            $region->devicemusic = max(0, min(2, (int) $settings['devicemusic']));
        }
        if (array_key_exists('setup', $settings)) {
            $region->setup = max(0, min(6, (int) $settings['setup']));
        }
        if (array_key_exists('reasons', $settings)) {
            // Null: back to those of the plugin.
            $region->reasons = is_array($settings['reasons']) ? json_encode(self::clean_reasons($settings['reasons'])) : null;
        }
        if (array_key_exists('mischief', $settings)) {
            if (!in_array($settings['mischief'], stains::MODES, true)) {
                throw new \invalid_parameter_exception('Unknown mischief: ' . $settings['mischief']);
            }
            $region->mischief = $settings['mischief'];
        }
        if (array_key_exists('family', $settings)) {
            if (!in_array($settings['family'], self::FAMILIES, true)) {
                throw new \invalid_parameter_exception('Unknown family: ' . $settings['family']);
            }
            $region->family = $settings['family'];
        }
        $regroup = array_key_exists('groupingid', $settings) && (int) $settings['groupingid'] !== (int) ($region->groupingid ?? 0);
        foreach (['groupingid', 'weeklycap', 'dailymanualcap', 'dailyenergylosscap'] as $number) {
            if (array_key_exists($number, $settings)) {
                $region->$number = max(0, (int) $settings[$number]);
            }
        }
        if (array_key_exists('timezone', $settings)) {
            $zone = (string) $settings['timezone'];
            if ($zone !== '99' && !array_key_exists($zone, \core_date::get_list_of_timezones())) {
                throw new \invalid_parameter_exception('Unknown time zone: ' . $zone);
            }
            $region->timezone = $zone;
        }
        if (array_key_exists('nicknames', $settings)) {
            $region->nicknames = max(0, min(2, (int) $settings['nicknames']));
        }
        if (array_key_exists('look', $settings)) {
            if (!in_array($settings['look'], themes::LOOKS, true)) {
                throw new \invalid_parameter_exception('Unknown theme: ' . $settings['look']);
            }
            $region->look = $settings['look'];
        }
        if (array_key_exists('portal', $settings)) {
            if (!in_array($settings['portal'], portal::MODES, true)) {
                throw new \invalid_parameter_exception('Unknown cover: ' . $settings['portal']);
            }
            $region->portal = $settings['portal'];
        }
        $inside = null;
        if (array_key_exists('hideacts', $settings) && (int) (bool) $settings['hideacts'] !== (int) ($region->hideacts ?? 0)) {
            $inside = (bool) $settings['hideacts'];
            $region->hideacts = (int) $inside;
        }
        $grades = false;
        if (array_key_exists('gradebook', $settings) && (int) (bool) $settings['gradebook'] !== (int) ($region->gradebook ?? 0)) {
            $region->gradebook = (int) (bool) $settings['gradebook'];
            $grades = (bool) $settings['gradebook'];
        }
        if (array_key_exists('rules', $settings)) {
            $rules = [];
            foreach (self::RULES as $key => $default) {
                $rules[$key] = max(0, (int) ($settings['rules'][$key] ?? $default));
            }
            $region->rules = json_encode($rules);
        }
        $region->timemodified = $now;
        if ($inside !== null) {
            // The activities of the mission go inside the valley, or back to the course page.
            portal::keep_inside($courseid, $inside);
        }
        if (empty($region->id)) {
            $region->id = $DB->insert_record(self::TABLE, $region);
        } else {
            $DB->update_record(self::TABLE, $region);
        }
        self::forget();
        if ($grades) {
            // Turned on: the ratings already given go to the gradebook too.
            gradebook::sync($courseid);
        }
        if ($regroup) {
            activities::regroup($courseid, (int) $region->groupingid);
        }
        return $DB->get_record(self::TABLE, ['id' => $region->id], '*', MUST_EXIST);
    }

    /**
     * The value of an automatic rule in a region.
     *
     * @param \stdClass $region
     * @param string $key One of the keys of RULES.
     * @return int
     */
    public static function rule(\stdClass $region, string $key): int {
        $rules = json_decode((string) $region->rules, true) ?: [];
        return (int) ($rules[$key] ?? self::RULES[$key]);
    }

    /**
     * The touches with a reason of a course: its own, or those the plugin brings, in the language of the teacher.
     *
     * @param \stdClass|null $region
     * @return array Each one: text, resource (saber, gems or breath) and amount.
     */
    public static function reasons(?\stdClass $region): array {
        $own = $region && !empty($region->reasons) ? json_decode($region->reasons, true) : null;
        if (is_array($own)) {
            return self::clean_reasons($own);
        }
        return array_map(fn($r) => ['text' => get_string($r[0], 'local_oksigeniatesape'), 'resource' => $r[1],
            'amount' => $r[2]], self::REASONS);
    }

    /**
     * Only touches that make sense: with a text, something that can be given, and no more than its top.
     *
     * @param array $list
     * @return array
     */
    public static function clean_reasons(array $list): array {
        $clean = [];
        foreach ($list as $reason) {
            $text = trim(clean_param((string) ($reason['text'] ?? ''), PARAM_TEXT));
            $resource = (string) ($reason['resource'] ?? '');
            $amount = (int) ($reason['amount'] ?? 0);
            if ($text === '' || !isset(self::REASON_MAX[$resource]) || $amount < 1) {
                continue;
            }
            $clean[] = ['text' => \core_text::substr($text, 0, 60), 'resource' => $resource,
                'amount' => min($amount, self::REASON_MAX[$resource])];
        }
        return array_slice($clean, 0, self::REASONS_MAX);
    }
}
