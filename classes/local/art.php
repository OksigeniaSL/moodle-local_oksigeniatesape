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
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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
        $files = [];
        foreach ($info['files'] as $key => $path) {
            $path = ltrim(clean_param((string) $path, PARAM_PATH), '/');
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (
                !preg_match('/^[a-z0-9_.-]{1,80}$/', (string) $key) || $path === '' || !in_array($extension, self::EXTENSIONS, true)
                || !is_file($tmp . '/' . $path)
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
        set_config('artpack', $code, self::COMPONENT);
        return $manifest;
    }

    /**
     * Turns the art packs off: the client goes back to its own drawings.
     */
    public static function off(): void {
        set_config('artpack', '', self::COMPONENT);
    }

    /**
     * The pack of the site, for the client: each key with the address of its image.
     *
     * @return array|null code, name, version and files (key => URL); null without a pack.
     */
    public static function manifest(): ?array {
        $code = (string) get_config(self::COMPONENT, 'artpack');
        if ($code === '') {
            return null;
        }
        $context = \context_system::instance();
        $file = get_file_storage()->get_file($context->id, self::COMPONENT, self::FILEAREA, 0, '/' . $code . '/', 'pack.json');
        $info = $file ? json_decode($file->get_content(), true) : null;
        if (!is_array($info)) {
            return null;
        }
        $files = [];
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
        return ['code' => $code, 'name' => $info['name'], 'version' => (int) $info['version'], 'files' => $files,
            'theme' => themes::clean($info['theme'] ?? null)];
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
        if (!$file || $file->is_directory() || !in_array($extension, self::EXTENSIONS, true)) {
            return false;
        }
        send_stored_file($file, YEARSECS, 0, false, $options);
        return true;
    }
}
