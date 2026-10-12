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
 * Missions: the packages that come with the plugin, and a mission installed in a course with its chapters, scenes,
 * items and a classroom challenge per chapter.
 *
 * A package is a folder in missions/ with mission.json (chapters, pages of the book, items, texts) and scenes/*.json.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class missions {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Saber of the classroom challenge made for each chapter. */
    public const CHAPTER_CHALLENGE_SABER = 20;

    /**
     * The packages that come with the plugin.
     *
     * @return array code => mission.json, decoded.
     */
    public static function packages(): array {
        global $CFG;
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $packages = [];
        foreach (glob($CFG->dirroot . '/local/oksigeniatesape/missions/*/mission.json') as $file) {
            $mission = json_decode(file_get_contents($file), true);
            if (is_array($mission) && !empty($mission['code'])) {
                $packages[$mission['code']] = $mission;
            }
        }
        ksort($packages);
        $cache = $packages;
        return $packages;
    }

    /**
     * The scenes of a package.
     *
     * @param string $code
     * @return array Code of the scene => scene.
     */
    public static function package_scenes(string $code): array {
        global $CFG;
        if (!preg_match('/^[a-z0-9]+$/', $code)) {
            throw new \invalid_parameter_exception('Unknown mission');
        }
        $scenes = [];
        foreach (glob($CFG->dirroot . '/local/oksigeniatesape/missions/' . $code . '/scenes/*.json') as $file) {
            $scene = json_decode(file_get_contents($file), true);
            if (is_array($scene) && !empty($scene['id'])) {
                $scenes[$scene['id']] = $scene;
            }
        }
        return $scenes;
    }

    /**
     * Installs a mission of a package in a course, or brings it up to date (changing the skin or the local examples
     * keeps everything the students did).
     *
     * @param int $courseid
     * @param string $code
     * @param string $skin
     * @param string $variant
     * @param int|null $now
     * @param bool $activities Also make the Moodle activities of the chapters that do not have them yet, each chapter in
     *     a new section of the course.
     * @return \stdClass The mission.
     */
    public static function install(
        int $courseid,
        string $code,
        string $skin,
        string $variant,
        ?int $now = null,
        bool $activities = false
    ): \stdClass {
        global $DB;
        $packages = self::packages();
        if (!isset($packages[$code])) {
            throw new \invalid_parameter_exception('Unknown mission: ' . $code);
        }
        $package = $packages[$code];
        if (!isset($package['skins'][$skin]) || !isset($package['variants'][$variant])) {
            throw new \invalid_parameter_exception('Unknown skin or local examples');
        }
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $mission = $DB->get_record(self::T . 'mission', ['courseid' => $courseid, 'code' => $code]);
        $definition = $package;
        unset($definition['scenes']);
        $record = (object) [
            'courseid' => $courseid,
            'code' => $code,
            'origin' => 'catalog',
            'skin' => $skin,
            'variant' => $variant,
            'version' => (int) ($package['version'] ?? 1),
            'definition' => json_encode($definition),
            'timemodified' => $now,
        ];
        if ($mission) {
            $record->id = $mission->id;
            $DB->update_record(self::T . 'mission', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::T . 'mission', $record);
        }
        $chapterids = [];
        foreach ($package['chapters'] as $i => $chapter) {
            $existing = $DB->get_record(self::T . 'chapter', ['missionid' => $record->id, 'code' => $chapter['code']]);
            if ($existing) {
                $DB->update_record(self::T . 'chapter', (object) ['id' => $existing->id, 'sortorder' => $i,
                    'timemodified' => $now]);
                $chapterids[$chapter['code']] = (int) $existing->id;
            } else {
                $chapterids[$chapter['code']] = (int) $DB->insert_record(self::T . 'chapter', (object) [
                    'missionid' => $record->id,
                    'code' => $chapter['code'],
                    'sortorder' => $i,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                if (!empty($chapter['offscreen'])) {
                    classroom::save($courseid, [
                        'name' => $chapter['offscreen'],
                        'saber' => self::CHAPTER_CHALLENGE_SABER,
                        'chapterid' => $chapterids[$chapter['code']],
                    ], $now);
                }
            }
        }
        foreach (self::package_scenes($code) as $scenecode => $scene) {
            $data = (object) [
                'missionid' => $record->id,
                'chapterid' => $chapterids[$scene['chapter']] ?? 0,
                'code' => $scenecode,
                'step' => (int) ($scene['step'] ?? 0),
                'quest' => empty($scene['quest']) ? 0 : 1,
                'version' => (int) ($package['version'] ?? 1),
                'definition' => json_encode($scene),
                'timemodified' => $now,
            ];
            $existing = $DB->get_record(self::T . 'scene', ['missionid' => $record->id, 'code' => $scenecode]);
            if ($existing) {
                $data->id = $existing->id;
                $DB->update_record(self::T . 'scene', $data);
            } else {
                $data->timecreated = $now;
                $DB->insert_record(self::T . 'scene', $data);
            }
        }
        foreach ($package['items'] ?? [] as $itemcode => $item) {
            self::item_id($itemcode, $item['name'] ?? $itemcode, $item['description'] ?? null, $code);
        }
        if ($activities) {
            $course = get_course($courseid);
            foreach ($package['chapters'] as $chapter) {
                $place = $DB->get_record(self::T . 'chapter', ['id' => $chapterids[$chapter['code']]], '*', MUST_EXIST);
                if (!$place->sectionid && !empty($chapter['activities'])) {
                    activities::make_for_chapter($course, $place, $chapter, $skin, $now, $variant);
                }
            }
        }
        $transaction->allow_commit();
        // The framework of the mission in the site, and its competencies in the course.
        competencies::links($courseid);
        if ($activities && !empty(regions::get($courseid)->hideacts)) {
            // The course keeps the activities of the mission inside the valley: the new ones too.
            portal::keep_inside($courseid, true);
        }
        return $DB->get_record(self::T . 'mission', ['id' => $record->id], '*', MUST_EXIST);
    }

    /**
     * The mission of a course: the last one installed.
     *
     * @param int $courseid
     * @return \stdClass|null With the definition decoded in data.
     */
    public static function get(int $courseid): ?\stdClass {
        global $DB;
        $missions = $DB->get_records(self::T . 'mission', ['courseid' => $courseid], 'id DESC', '*', 0, 1);
        $mission = reset($missions);
        if (!$mission) {
            return null;
        }
        // A newer package came with the plugin: bring the mission up to date, keeping what the students did. Once:
        // the first requests after an upgrade come all at the same time.
        $package = self::packages()[$mission->code] ?? null;
        if ($mission->origin === 'catalog' && $package && (int) ($package['version'] ?? 1) > (int) $mission->version) {
            $factory = \core\lock\lock_config::get_lock_factory('local_oksigeniatesape');
            $lock = $factory->get_lock('mission-' . $courseid, 30);
            try {
                $fresh = $DB->get_record(self::T . 'mission', ['id' => $mission->id]);
                if ($fresh && (int) ($package['version'] ?? 1) > (int) $fresh->version) {
                    $mission = self::install($courseid, $mission->code, $mission->skin, $mission->variant);
                } else if ($fresh) {
                    $mission = $fresh;
                }
            } finally {
                if ($lock) {
                    $lock->release();
                }
            }
        }
        $mission->data = json_decode((string) $mission->definition, true) ?: [];
        return $mission;
    }

    /**
     * The expeditions a course made before the current one: the missions installed earlier, newest first. They stay,
     * with their lanterns, as a map the class can visit.
     *
     * @param int $courseid
     * @return \stdClass[] Missions, with data.
     */
    public static function past(int $courseid): array {
        global $DB;
        $missions = array_values($DB->get_records(self::T . 'mission', ['courseid' => $courseid], 'id DESC'));
        array_shift($missions);
        foreach ($missions as $mission) {
            $mission->data = json_decode((string) $mission->definition, true) ?: [];
        }
        return $missions;
    }

    /**
     * One of the expeditions of a course, current or past.
     *
     * @param int $courseid
     * @param int $missionid
     * @return \stdClass The mission, with data.
     */
    public static function expedition(int $courseid, int $missionid): \stdClass {
        global $DB;
        $mission = $DB->get_record(self::T . 'mission', ['id' => $missionid, 'courseid' => $courseid], '*', MUST_EXIST);
        $mission->data = json_decode((string) $mission->definition, true) ?: [];
        return $mission;
    }

    /**
     * Whether some chapters of the mission of a course bring Moodle activities that are not in the course yet.
     *
     * @param \stdClass $mission With data.
     * @return bool
     */
    public static function activities_missing(\stdClass $mission): bool {
        global $DB;
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            if (
                !empty($chapter['activities']) && !$DB->get_field(self::T . 'chapter', 'sectionid', ['missionid' => $mission->id,
                    'code' => $chapter['code']])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * The id of an item by its code, making it if it is not there yet.
     *
     * @param string $code
     * @param string|null $name
     * @param string|null $description
     * @param string|null $mission
     * @return int
     */
    public static function item_id(string $code, ?string $name = null, ?string $description = null, ?string $mission = null): int {
        global $DB;
        $id = $DB->get_field(self::T . 'item', 'id', ['code' => $code]);
        if ($id) {
            return (int) $id;
        }
        return (int) $DB->insert_record(self::T . 'item', (object) [
            'code' => $code,
            'name' => $name ?? $code,
            'description' => $description,
            'mission' => $mission,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * A text of a mission in the skin of the course: texts may be one string, one per skin, or one per skin and place
     * (key «skin.variant», for the voice of the place: «valley.py» speaks with «ustedes»).
     *
     * @param mixed $text
     * @param string $skin
     * @param string $variant The local examples (es, py).
     * @return string
     */
    public static function text($text, string $skin, string $variant = ''): string {
        if (is_array($text)) {
            // The voice of the place first (valley.py speaks with «ustedes»), then the story, then whatever there is.
            return (string) ($text[$skin . '.' . $variant] ?? $text[$skin] ?? reset($text));
        }
        return (string) $text;
    }
}
