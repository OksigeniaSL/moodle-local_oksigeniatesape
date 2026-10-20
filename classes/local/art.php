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
 * Art packs: the art of the game (characters, creature, icons, maps) as images, kept in the site and served by
 * Moodle, so that students' browsers only talk to their Moodle. Without a pack, the client draws its own placeholder
 * art.
 *
 * A pack is a zip with pack.json and its images. pack.json has a code, a name, a version and files: a map from keys
 * (character.storyheroes.healer.fairy.f.1, creature.2.feathers, icon.saber, map.livingthings…) to the images in the
 * zip. The client looks for the most precise key first and goes down to the general ones.
 *
 * Packs come in layers (kind in pack.json): the world (its map in layers, sounds, creature, the Embrollo…), a style
 * (the heroes) and a mission (its scenes, album, speakers and maps). The site has one world and any number of styles
 * and missions; each key is taken from the mission first, then the style, then the world, and the client draws what no
 * pack has. So no zip has to carry everything, and each one stays light enough to upload.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class art {
    /** @var string Component of the files. */
    private const COMPONENT = 'local_oksigeniatesape';

    /** @var string File area of the packs. */
    public const FILEAREA = 'art';

    /** @var string[] Images a pack can have (raster only: nothing that can run code). */
    public const EXTENSIONS = ['png', 'webp', 'jpg', 'jpeg'];

    /**
     * @var string[] Sounds a pack can have, under keys that start with «sound.» (the sounds, places and music of a world)
     *     or «voice.» (what the characters say).
     */
    public const SOUNDS = ['mp3', 'ogg', 'm4a'];

    /**
     * @var string[] Data a pack can have, under keys that start with «rig.» or «anim.»: the puppets (the pieces of each
     *     figure in its atlas, the skeleton of each kind of body and its animations). Read as JSON and written again.
     */
    public const DATA = ['json'];

    /** @var int The most a file of data can weigh, in bytes. */
    private const DATAMAX = 262144;

    /** @var string[] The layers of a pack, from the one under to the one on top. */
    public const KINDS = ['world', 'style', 'mission'];

    /** @var array|null The packs read in this request, by code. */
    private static $packs = null;

    /**
     * Installs a pack from a zip and makes it the pack of the site. A pack with the same code is replaced.
     *
     * @param string $zippath
     * @return array The manifest of the pack.
     */
    public static function install(string $zippath): array {
        $context = \context_system::instance();
        $fs = get_file_storage();
        $tmp = make_request_directory();
        if (!get_file_packer('application/zip')->extract_to_pathname($zippath, $tmp)) {
            throw new \moodle_exception('artpackbad', self::COMPONENT, '', 'zip');
        }
        $info = json_decode((string) @file_get_contents($tmp . '/pack.json'), true);
        $code = (string) ($info['code'] ?? '');
        if (!is_array($info) || !preg_match('/^[a-z0-9-]{2,40}$/', $code) || !is_array($info['files'] ?? null)) {
            throw new \moodle_exception('artpackbad', self::COMPONENT, '', 'pack.json');
        }
        $kind = (string) ($info['kind'] ?? 'world');
        if (!in_array($kind, self::KINDS, true)) {
            throw new \moodle_exception('artpackbad', self::COMPONENT, '', 'kind');
        }
        $files = [];
        foreach ($info['files'] as $key => $path) {
            $path = ltrim(clean_param((string) $path, PARAM_PATH), '/');
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $sound = preg_match('/^(sound|voice)\./', (string) $key) === 1;
            $data = $extension === 'json' && preg_match('/^(rig|anim)\./', (string) $key) === 1;
            if (
                !preg_match('/^[a-z0-9_.-]{1,80}$/', (string) $key) || $path === '' || !is_file($tmp . '/' . $path)
                || !in_array($extension, $sound ? self::SOUNDS : ($data ? self::DATA : self::EXTENSIONS), true)
                || ($sound && !self::is_sound($tmp . '/' . $path))
                || ($data && !self::clean_data($tmp . '/' . $path))
            ) {
                continue;
            }
            $files[$key] = $path;
        }
        if (!$files) {
            throw new \moodle_exception('artpackbad', self::COMPONENT, '', 'files');
        }
        foreach ($fs->get_area_files($context->id, self::COMPONENT, self::FILEAREA, 0, 'filepath', true) as $old) {
            if (strpos($old->get_filepath(), '/' . $code . '/') === 0) {
                $old->delete();
            }
        }
        foreach (array_unique($files) as $path) {
            $fs->create_file_from_pathname([
                'contextid' => $context->id,
                'component' => self::COMPONENT,
                'filearea' => self::FILEAREA,
                'itemid' => 0,
                'filepath' => '/' . $code . '/' . (dirname($path) === '.' ? '' : dirname($path) . '/'),
                'filename' => basename($path),
            ], $tmp . '/' . $path);
        }
        $manifest = [
            'code' => $code,
            'name' => clean_param((string) ($info['name'] ?? $code), PARAM_TEXT),
            'version' => max(1, (int) ($info['version'] ?? 1)),
            'kind' => $kind,
            'files' => $files,
            'theme' => themes::clean($info['theme'] ?? null),
        ];
        $fs->create_file_from_string([
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => 0,
            'filepath' => '/' . $code . '/',
            'filename' => 'pack.json',
        ], json_encode($manifest));
        // The world is the one of the site; a style or a mission goes over it, beside those already there.
        $layers = array_values(array_diff(self::layers(), [$code]));
        if ($kind === 'world') {
            set_config('artpack', $code, self::COMPONENT);
        } else {
            $layers[] = $code;
            if ((string) get_config(self::COMPONENT, 'artpack') === $code) {
                set_config('artpack', '', self::COMPONENT);
            }
        }
        set_config('artlayers', implode(',', $layers), self::COMPONENT);
        self::$packs = null;
        return $manifest;
    }

    /**
     * Turns the art packs off: the client goes back to its own drawings.
     */
    public static function off(): void {
        set_config('artpack', '', self::COMPONENT);
        set_config('artlayers', '', self::COMPONENT);
        self::$packs = null;
    }

    /**
     * Takes one pack out of the site, with its files: the world, a style or a mission.
     *
     * @param string $code
     * @return bool Whether it was there.
     */
    public static function remove(string $code): bool {
        $there = (string) get_config(self::COMPONENT, 'artpack') === $code || in_array($code, self::layers(), true);
        if ((string) get_config(self::COMPONENT, 'artpack') === $code) {
            set_config('artpack', '', self::COMPONENT);
        }
        set_config('artlayers', implode(',', array_diff(self::layers(), [$code])), self::COMPONENT);
        $context = \context_system::instance();
        foreach (get_file_storage()->get_area_files($context->id, self::COMPONENT, self::FILEAREA, 0, 'filepath', true) as $old) {
            if (strpos($old->get_filepath(), '/' . $code . '/') === 0) {
                $old->delete();
            }
        }
        self::$packs = null;
        return $there;
    }

    /**
     * The codes of the styles and missions installed over the world, in the order they came.
     *
     * @return string[]
     */
    private static function layers(): array {
        return array_values(array_filter(explode(',', (string) get_config(self::COMPONENT, 'artlayers'))));
    }

    /**
     * What a pack installed in the site says of itself (its pack.json), or null.
     *
     * @param string $code
     * @return array|null
     */
    private static function pack(string $code): ?array {
        if (!isset(self::$packs[$code])) {
            $context = \context_system::instance();
            $file = get_file_storage()->get_file($context->id, self::COMPONENT, self::FILEAREA, 0, '/' . $code . '/', 'pack.json');
            $info = $file ? json_decode($file->get_content(), true) : null;
            self::$packs[$code] = is_array($info) && is_array($info['files'] ?? null) ? $info + ['kind' => 'world'] : false;
        }
        return self::$packs[$code] ?: null;
    }

    /**
     * The art of the site, for the client: each key with the address of its image, taken from the mission, the style or
     * the world, in that order.
     *
     * @return array|null code, name, version and theme of the world (or of the first layer, without a world), files
     *     (key => URL) and layers (code, kind and version of each pack); null without packs.
     */
    public static function manifest(): ?array {
        $context = \context_system::instance();
        $world = (string) get_config(self::COMPONENT, 'artpack');
        $packs = [];
        foreach (array_merge($world !== '' ? [$world] : [], self::layers()) as $code) {
            $info = self::pack($code);
            if ($info) {
                $packs[$code] = $info;
            }
        }
        if (!$packs) {
            return null;
        }
        // The one under first: each layer writes over it.
        uksort($packs, fn($a, $b) => array_search($packs[$a]['kind'], self::KINDS) <=> array_search($packs[$b]['kind'], self::KINDS));
        $files = [];
        $layers = [];
        foreach ($packs as $code => $info) {
            foreach ($info['files'] as $key => $path) {
                $files[$key] = \moodle_url::make_pluginfile_url(
                    $context->id,
                    self::COMPONENT,
                    self::FILEAREA,
                    (int) $info['version'],
                    '/' . $code . '/' . (dirname($path) === '.' ? '' : dirname($path) . '/'),
                    basename($path)
                )->out(false);
            }
            $layers[] = ['code' => (string) $code, 'kind' => $info['kind'], 'version' => (int) $info['version']];
        }
        $base = reset($packs);
        return ['code' => (string) key($packs), 'name' => $base['name'], 'version' => (int) $base['version'], 'files' => $files,
            'theme' => themes::clean($base['theme'] ?? null), 'layers' => $layers];
    }

    /**
     * The art of the site as the pages carry it: the address of each pack once (roots) and, for each key, its pack and
     * the rest of its address; only the extension when the image is where its key says (its kind/key.extension). The
     * client puts the addresses together again. Where Moodle does not use slashes in its file addresses, each key goes
     * with its whole address.
     *
     * @return array|null As manifest(), with roots, and files as key => [pack, rest] or key => address.
     */
    public static function client(): ?array {
        $art = self::manifest();
        if (!$art) {
            return null;
        }
        $context = \context_system::instance();
        $roots = [];
        foreach ($art['layers'] as $i => $layer) {
            $roots[$i] = \moodle_url::make_pluginfile_url(
                $context->id,
                self::COMPONENT,
                self::FILEAREA,
                $layer['version'],
                '/' . $layer['code'] . '/',
                ''
            )->out(false);
        }
        $files = [];
        foreach ($art['files'] as $key => $url) {
            $files[$key] = $url;
            foreach ($roots as $i => $root) {
                if (strpos($url, $root) === 0 && preg_match('~^[a-z0-9_./-]+$~', substr($url, strlen($root)))) {
                    $rest = substr($url, strlen($root));
                    $derived = strtok($key, '.') . '/' . $key . '.';
                    $files[$key] = [$i, strpos($rest, $derived) === 0 && strpos(substr($rest, strlen($derived)), '/') === false ?
                        substr($rest, strlen($derived)) : $rest];
                    break;
                }
            }
        }
        return ['roots' => $roots, 'files' => $files] + $art;
    }

    /**
     * Serves an image of a pack to anyone logged in. The first argument is the version, only there for caching.
     *
     * @param array $args
     * @param array $options
     * @return bool False when there is no such image.
     */
    public static function serve(array $args, array $options): bool {
        if (count($args) < 3) {
            return false;
        }
        array_shift($args);
        $filename = array_pop($args);
        $filepath = '/' . implode('/', $args) . '/';
        $context = \context_system::instance();
        $file = get_file_storage()->get_file($context->id, self::COMPONENT, self::FILEAREA, 0, $filepath, $filename);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (
            !$file || $file->is_directory()
            || !in_array($extension, array_merge(self::EXTENSIONS, self::SOUNDS, self::DATA), true)
        ) {
            return false;
        }
        send_stored_file($file, YEARSECS, 0, false, $options);
        return true;
    }

    /**
     * Whether a file is data that can be kept: JSON, an object or a list, small. It is written again from what was read,
     * so nothing else stays in it.
     *
     * @param string $path
     * @return bool
     */
    private static function clean_data(string $path): bool {
        if (filesize($path) > self::DATAMAX) {
            return false;
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return false;
        }
        file_put_contents($path, json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP));
        return true;
    }

    /**
     * Whether a file starts as a sound does: MP3 (with its tag or a frame), Ogg or MP4 audio. Nothing else is kept as a
     * sound, whatever its name.
     *
     * @param string $path
     * @return bool
     */
    private static function is_sound(string $path): bool {
        $head = (string) @file_get_contents($path, false, null, 0, 12);
        return strpos($head, 'ID3') === 0 || strpos($head, 'OggS') === 0 || substr($head, 4, 4) === 'ftyp'
            || (strlen($head) > 1 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0);
    }
}
