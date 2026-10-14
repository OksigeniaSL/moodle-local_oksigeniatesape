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
 * Tests for art packs: installing one, what the client gets and what is left out.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\art
 */
#[\PHPUnit\Framework\Attributes\CoversClass(art::class)]
final class art_test extends \advanced_testcase {
    /**
     * Makes a zip with a pack.json and some files.
     *
     * @param array $manifest
     * @param string[] $files Paths in the zip.
     * @return string Path of the zip.
     */
    private function make_zip(array $manifest, array $files): string {
        $dir = make_request_directory();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $list = [];
        file_put_contents($dir . '/pack.json', json_encode($manifest));
        $list['pack.json'] = $dir . '/pack.json';
        foreach ($files as $i => $path) {
            $local = $dir . '/f' . $i;
            file_put_contents($local, str_ends_with($path, '.svg') ? '<svg xmlns="http://www.w3.org/2000/svg"/>' : $png);
            $list[$path] = $local;
        }
        $zip = $dir . '/pack.zip';
        get_file_packer('application/zip')->archive_to_pathname($list, $zip);
        return $zip;
    }

    public function test_install_and_turn_off(): void {
        $this->resetAfterTest();
        $this->assertNull(art::manifest());
        $zip = $this->make_zip([
            'code' => 'painted-valley',
            'name' => 'The painted valley',
            'version' => 3,
            'files' => [
                'character.healer' => 'characters/healer.png',
                'map.livingthings' => 'map.webp',
                'icon.saber' => 'icons/saber.svg',
                'BAD KEY' => 'characters/healer.png',
                'creature' => 'missing.png',
            ],
        ], ['characters/healer.png', 'map.webp', 'icons/saber.svg']);
        $manifest = art::install($zip);
        $this->assertSame(['character.healer', 'map.livingthings'], array_keys($manifest['files']));
        $this->assertSame((string) get_config('local_oksigeniatesape', 'artpack'), 'painted-valley');

        $client = art::manifest();
        $this->assertSame(3, $client['version']);
        $url = $client['files']['character.healer'];
        $this->assertStringContainsString('/pluginfile.php/' . \context_system::instance()->id .
            '/local_oksigeniatesape/art/3/painted-valley/characters/healer.png', $url);
        $this->assertStringEndsWith('/art/3/painted-valley/map.webp', $client['files']['map.livingthings']);

        // A new version replaces the old files.
        art::install($this->make_zip(
            ['code' => 'painted-valley', 'version' => 4, 'files' => ['map.livingthings' => 'map.webp']],
            ['map.webp']
        ));
        $this->assertSame(['map.livingthings'], array_keys(art::manifest()['files']));
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id,
            'local_oksigeniatesape',
            'art',
            0,
            'filename',
            false
        );
        $this->assertSame(['map.webp', 'pack.json'], array_values(array_map(fn($f) => $f->get_filename(), $files)));

        art::off();
        $this->assertNull(art::manifest());
    }

    public function test_bad_packs(): void {
        $this->resetAfterTest();
        $cases = [
            ['code' => 'Bad Code', 'files' => ['icon.saber' => 'a.png']],
            ['code' => 'nofiles', 'files' => ['icon.saber' => 'a.svg']],
            ['name' => 'No code'],
        ];
        foreach ($cases as $manifest) {
            try {
                art::install($this->make_zip($manifest, ['a.png', 'a.svg']));
                $this->fail('Installed: ' . json_encode($manifest));
            } catch (\moodle_exception $e) {
                $this->assertSame('artpackbad', $e->errorcode);
            }
        }
        $this->assertNull(art::manifest());
    }

    public function test_a_world_brings_its_theme(): void {
        $this->resetAfterTest();
        art::install($this->make_zip([
            'code' => 'sea',
            'name' => 'The sea',
            'version' => 1,
            'files' => ['map.livingthings' => 'map.webp'],
            'theme' => ['base' => 'day', 'day' => ['lantern' => '#33aacc', 'sky' => 'url(x)']],
        ], ['map.webp']));
        $this->assertSame(['base' => 'day', 'day' => ['lantern' => '#33aacc']], art::manifest()['theme']);
    }
}
