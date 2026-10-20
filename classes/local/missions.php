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
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class missions {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Saber of the classroom challenge made for each chapter. */
    public const CHAPTER_CHALLENGE_SABER = 20;

    /** @var string Folder of the site data where the missions uploaded to the site are kept. */
    public const SITE_FOLDER = 'local_oksigeniatesape/missions';

    /** @var int Largest package of a mission that can be uploaded (bytes, unpacked). */
    public const MAX_PACKAGE = 20 * 1024 * 1024;

    /**
     * The packages of missions on the site: those that come with the plugin and those uploaded to the site (a newer
     * version of a mission of the plugin, or one from the catalog). Of each mission, its newest version.
     *
     * @param bool $fresh Read them again (after an upload).
     * @return array code => mission.json, decoded.
     */
    public static function packages(bool $fresh = false): array {
        return array_map(fn($entry) => $entry['mission'], self::index($fresh));
    }

    /**
     * Where the newest package of a mission came from.
     *
     * @param string $code
     * @return string plugin, site, or empty if there is none.
     */
    public static function package_origin(string $code): string {
        return (string) (self::index()[$code]['origin'] ?? '');
    }

    /** @var array The licences a mission can have, by their SPDX code: how they are named and where they are read. */
    private const RIGHTS = [
        'CC-BY-4.0' => ['CC BY 4.0', 'https://creativecommons.org/licenses/by/4.0/'],
        'CC-BY-SA-4.0' => ['CC BY-SA 4.0', 'https://creativecommons.org/licenses/by-sa/4.0/'],
        'CC-BY-NC-SA-4.0' => ['CC BY-NC-SA 4.0', 'https://creativecommons.org/licenses/by-nc-sa/4.0/'],
        'GPL-3.0-or-later' => ['GNU GPL v3', 'https://www.gnu.org/licenses/gpl-3.0.html'],
    ];

    /**
     * The licences of a mission, to choose from (its field rights, in SPDX: «CC-BY-SA-4.0 OR GPL-3.0-or-later»).
     *
     * @param array $mission The mission.json.
     * @return array Of ['name' => ..., 'url' => ...]; the url is empty for a licence it does not know.
     */
    public static function rights(array $mission): array {
        $list = [];
        foreach (preg_split('/\s+OR\s+/', trim((string) ($mission['rights'] ?? ''))) as $code) {
            $code = clean_param(trim($code, " ()"), PARAM_TEXT);
            if ($code !== '') {
                [$name, $url] = self::RIGHTS[$code] ?? [$code, ''];
                $list[] = ['name' => $name, 'url' => $url];
            }
        }
        return $list;
    }

    /**
     * The scenes of a package.
     *
     * @param string $code
     * @return array Code of the scene => scene.
     */
    public static function package_scenes(string $code): array {
        if (!preg_match('/^[a-z0-9]+$/', $code)) {
            throw new \invalid_parameter_exception('Unknown mission');
        }
        $folder = self::index()[$code]['folder'] ?? null;
        $scenes = [];
        foreach ($folder ? (glob($folder . '/scenes/*.json') ?: []) : [] as $file) {
            $scene = json_decode(file_get_contents($file), true);
            if (is_array($scene) && !empty($scene['id'])) {
                $scenes[$scene['id']] = $scene;
            }
        }
        return $scenes;
    }

    /**
     * Whether a zip can be opened: not too many files, and not too big once unpacked (a small zip can hide gigabytes).
     * Looked at before unpacking anything.
     *
     * @param string $zippath
     * @return bool
     */
    public static function zip_fits(string $zippath): bool {
        $files = get_file_packer('application/zip')->list_files($zippath);
        if (!is_array($files) || count($files) > 1000) {
            return false;
        }
        $size = array_sum(array_map(fn($f) => (int) $f->size, $files));
        return $size <= 2 * self::MAX_PACKAGE;
    }

    /**
     * Keeps on the site the package of a mission, from a zip with its mission.json and its scenes (scenes/*.json),
     * at the top of the zip or in one folder. It replaces an older version of the same mission; courses with it are
     * brought up to date when they are opened, keeping what the students did and the changes of the teacher.
     *
     * @param string $zippath
     * @return array The mission.json of the package.
     */
    public static function upload(string $zippath): array {
        global $CFG;
        $tmp = make_request_directory();
        if (!self::zip_fits($zippath) || !get_file_packer('application/zip')->extract_to_pathname($zippath, $tmp)) {
            throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', 'zip');
        }
        $found = glob($tmp . '/mission.json') ?: glob($tmp . '/*/mission.json') ?: [];
        if (count($found) !== 1) {
            throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', 'mission.json');
        }
        $root = dirname($found[0]);
        $size = 0;
        foreach (array_merge([$found[0]], glob($root . '/scenes/*.json') ?: []) as $file) {
            $size += filesize($file);
        }
        if ($size > self::MAX_PACKAGE) {
            throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', 'size');
        }
        $mission = json_decode((string) file_get_contents($found[0]), true);
        $code = (string) ($mission['code'] ?? '');
        $version = (int) ($mission['version'] ?? 0);
        if (
            !is_array($mission) || !preg_match('/^[a-z0-9]{2,40}$/', $code) || $version < 1 || empty($mission['title'])
            || empty($mission['chapters']) || !is_array($mission['chapters'])
        ) {
            throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', 'mission.json');
        }
        $scenes = [];
        foreach (glob($root . '/scenes/*.json') ?: [] as $file) {
            $scene = json_decode((string) file_get_contents($file), true);
            $id = (string) ($scene['id'] ?? '');
            if (!is_array($scene) || !preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id) || empty($scene['nodes'])) {
                throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', basename($file));
            }
            $scenes[$id] = $file;
        }
        if (!$scenes) {
            throw new \moodle_exception('missionbad', 'local_oksigeniatesape', '', 'scenes');
        }
        $current = self::packages(true)[$code] ?? null;
        if ($current && (int) ($current['version'] ?? 1) > $version) {
            throw new \moodle_exception('missionolder', 'local_oksigeniatesape', '', (int) $current['version']);
        }
        $folder = $CFG->dataroot . '/' . self::SITE_FOLDER . '/' . $code;
        if (is_dir($folder)) {
            remove_dir($folder);
        }
        make_writable_directory($folder . '/scenes');
        copy($found[0], $folder . '/mission.json');
        foreach ($scenes as $id => $file) {
            copy($file, $folder . '/scenes/' . $id . '.json');
        }
        self::index(true);
        return $mission;
    }

    /**
     * The newest package of each mission, with where it is.
     *
     * @param bool $fresh
     * @return array code => folder, origin (plugin or site), mission.
     */
    private static function index(bool $fresh = false): array {
        global $CFG;
        // Read once a request; the cache of the request, not a static, so that tests do not see packages of another.
        $cache = \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'packages');
        $index = $fresh ? false : $cache->get('index');
        if ($index !== false) {
            return $index;
        }
        $folders = ['plugin' => $CFG->dirroot . '/local/oksigeniatesape/missions', 'site' => $CFG->dataroot . '/' .
            self::SITE_FOLDER];
        // Read from the disk only when a package changed: the files and their times make the key.
        $files = [];
        foreach ($folders as $origin => $folder) {
            foreach (glob($folder . '/*/mission.json') ?: [] as $file) {
                $files[] = [$origin, $file, (int) @filemtime($file)];
            }
        }
        $kept = \cache::make('local_oksigeniatesape', 'packages');
        $key = md5(json_encode([get_config('local_oksigeniatesape', 'version'), $files]));
        $index = $kept->get($key);
        if ($index !== false) {
            $cache->set('index', $index);
            return $index;
        }
        $index = [];
        foreach ($files as [$origin, $file]) {
            $mission = json_decode((string) file_get_contents($file), true);
            if (!is_array($mission) || empty($mission['code'])) {
                continue;
            }
            $code = (string) $mission['code'];
            // A package uploaded to the site counts only if it is newer than the one of the plugin.
            if (isset($index[$code]) && (int) ($index[$code]['mission']['version'] ?? 1) >= (int) ($mission['version'] ?? 1)) {
                continue;
            }
            $index[$code] = ['folder' => dirname($file), 'origin' => $origin, 'mission' => $mission];
        }
        ksort($index);
        $cache->set('index', $index);
        $kept->set($key, $index);
        return $index;
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
        self::forget();
        // A mission of the site, or one the teacher of the course made.
        $own = authoring::is_own($code);
        $package = $own ? authoring::package($courseid, $code) : (self::packages()[$code] ?? null);
        if (!$package) {
            throw new \invalid_parameter_exception('Unknown mission: ' . $code);
        }
        if (!isset($package['skins'][$skin]) || !isset($package['variants'][$variant])) {
            throw new \invalid_parameter_exception('Unknown skin or local examples');
        }
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        // The first mission of a course takes the students straight into the valley, unless the teacher chose a cover.
        $first = !$DB->record_exists(self::T . 'mission', ['courseid' => $courseid]);
        $region = $first ? regions::get($courseid) : null;
        if ($region && $region->portal === 'door') {
            regions::save($courseid, ['portal' => 'direct']);
        }
        $mission = $DB->get_record(self::T . 'mission', ['courseid' => $courseid, 'code' => $code]);
        $definition = $package;
        unset($definition['scenes']);
        $record = (object) [
            'courseid' => $courseid,
            'code' => $code,
            'origin' => $own ? 'own' : 'catalog',
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
                // The task of the place gets its parts if it had none (the teacher may have written their own).
                if (!empty($chapter['offscreenparts'])) {
                    $task = $DB->get_record(self::T . 'challenge', ['courseid' => $courseid, 'chapterid' => $existing->id,
                        'cmid' => 0], '*', IGNORE_MULTIPLE);
                    if ($task && !classroom::parts($task)) {
                        classroom::save($courseid, ['id' => $task->id, 'parts' => $chapter['offscreenparts']], $now);
                    }
                }
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
                        'parts' => $chapter['offscreenparts'] ?? [],
                    ], $now);
                }
            }
        }
        foreach ($own ? authoring::scenes($courseid, $code) : self::package_scenes($code) as $scenecode => $scene) {
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
        // The framework of the mission in the site, and its competencies in the course; never in the way of the game.
        try {
            competencies::links($courseid);
        } catch (\Throwable $e) {
            debugging('Tesapé could not link the competencies of the mission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // The badge of the mission in the course, not turned on; never in the way of the game either.
        try {
            $fresh = $DB->get_record(self::T . 'mission', ['id' => $record->id], '*', MUST_EXIST);
            $fresh->data = json_decode((string) $fresh->definition, true) ?: [];
            badges::make($fresh);
        } catch (\Throwable $e) {
            debugging('Tesapé could not make the badge of the mission: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        if ($activities && !empty(regions::get($courseid)->hideacts)) {
            // The course keeps the activities of the mission inside the valley: the new ones too.
            portal::keep_inside($courseid, true);
        }
        self::forget();
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
        // Read once in each request: many parts of the world of a student ask for it.
        $kept = self::cache()->get($courseid);
        if ($kept !== false) {
            return $kept ? clone $kept : null;
        }
        $missions = $DB->get_records(self::T . 'mission', ['courseid' => $courseid], 'id DESC', '*', 0, 1);
        $mission = reset($missions);
        if (!$mission) {
            self::cache()->set($courseid, 0);
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
        $mission->data = edits::apply(
            json_decode((string) $mission->definition, true) ?: [],
            $courseid,
            (string) $mission->code
        );
        self::cache()->set($courseid, $mission);
        return clone $mission;
    }

    /**
     * The missions read in this request.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'missions');
    }

    /**
     * Forgets the missions read in this request: one was installed or changed.
     */
    public static function forget(): void {
        self::cache()->purge();
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
            $mission->data = edits::apply(
                json_decode((string) $mission->definition, true) ?: [],
                $courseid,
                (string) $mission->code
            );
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
        $mission->data = edits::apply(
            json_decode((string) $mission->definition, true) ?: [],
            $courseid,
            (string) $mission->code
        );
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
