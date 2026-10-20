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
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
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
            // A sound starts as an Ogg file; a «fake» one is only text.
            $content = str_ends_with($path, '.svg') ? '<svg xmlns="http://www.w3.org/2000/svg"/>' : $png;
            if (str_ends_with($path, '.ogg') || str_ends_with($path, '.mp3')) {
                $content = str_contains($path, 'fake') ? 'not a sound' : 'OggS' . str_repeat("\0", 40);
            }
            // The data of a puppet: an atlas, or something that is not JSON.
            if (str_ends_with($path, '.json')) {
                $content = str_contains($path, 'fake') ? '<script>alert(1)</script>' :
                    '{"frames": {"head": {"frame": {"x": 0, "y": 0, "w": 1, "h": 1}}}, "meta": {"note": "<b>"}}';
            }
            file_put_contents($local, $content);
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

    public function test_packs_in_layers_take_each_key_from_the_mission_the_style_and_the_world(): void {
        $this->resetAfterTest();
        art::install($this->make_zip(
            ['code' => 'valle', 'version' => 2, 'theme' => ['base' => 'night'],
                'files' => ['map.livingthings' => 'map.webp', 'icon.saber' => 'saber.png', 'character.healer' => 'old.png']],
            ['map.webp', 'saber.png', 'old.png']
        ));
        art::install($this->make_zip(
            ['code' => 'mission-livingthings', 'kind' => 'mission', 'version' => 5,
                'files' => ['map.livingthings' => 'map.webp', 'scene.claro' => 'claro.webp']],
            ['map.webp', 'claro.webp']
        ));
        $manifest = art::install($this->make_zip(
            ['code' => 'heroes', 'kind' => 'style', 'version' => 1, 'files' => ['character.healer' => 'healer.png']],
            ['healer.png']
        ));
        $this->assertSame('style', $manifest['kind']);
        $art = art::manifest();
        // The world stays the pack of the site; the mission wins over it, and so does the style.
        $this->assertSame('valle', $art['code']);
        $this->assertSame('night', $art['theme']['base']);
        $this->assertStringEndsWith('/art/5/mission-livingthings/map.webp', $art['files']['map.livingthings']);
        $this->assertStringEndsWith('/art/5/mission-livingthings/claro.webp', $art['files']['scene.claro']);
        $this->assertStringEndsWith('/art/2/valle/saber.png', $art['files']['icon.saber']);
        $this->assertStringEndsWith('/art/1/heroes/healer.png', $art['files']['character.healer']);
        $this->assertSame(['world', 'style', 'mission'], array_column($art['layers'], 'kind'));
        // A new world keeps the layers over it.
        art::install($this->make_zip(
            ['code' => 'valle', 'version' => 3, 'files' => ['icon.saber' => 'saber.png']],
            ['saber.png']
        ));
        $this->assertStringEndsWith('/art/3/valle/saber.png', art::manifest()['files']['icon.saber']);
        $this->assertArrayHasKey('scene.claro', art::manifest()['files']);
        // Without the mission, the world is seen again where it was under.
        $this->assertTrue(art::remove('mission-livingthings'));
        $this->assertArrayNotHasKey('scene.claro', art::manifest()['files']);
        $this->assertFalse(art::remove('mission-livingthings'));
        // Without a world, the layers are still the art of the site.
        art::remove('valle');
        $this->assertSame('heroes', art::manifest()['code']);
        art::off();
        $this->assertNull(art::manifest());
    }

    public function test_the_pages_carry_the_art_light_and_whole_again_it_is_the_same(): void {
        $this->resetAfterTest();
        art::install($this->make_zip(
            ['code' => 'valle', 'version' => 2, 'files' => ['icon.saber' => 'icon/icon.saber.png', 'map.x' => 'maps/x.webp']],
            ['icon/icon.saber.png', 'maps/x.webp']
        ));
        art::install($this->make_zip(
            ['code' => 'mission-x', 'kind' => 'mission', 'version' => 1, 'files' => ['scene.a' => 'scene/scene.a.webp']],
            ['scene/scene.a.webp']
        ));
        $client = art::client();
        $this->assertCount(2, $client['roots']);
        // Where the image is where its key says, only its extension; elsewhere, the rest of its address.
        $this->assertSame([0, 'png'], $client['files']['icon.saber']);
        $this->assertSame([0, 'maps/x.webp'], $client['files']['map.x']);
        $this->assertSame([1, 'webp'], $client['files']['scene.a']);
        // Put together as the client does, the addresses are those of the manifest.
        $whole = [];
        foreach ($client['files'] as $key => $f) {
            $whole[$key] = is_array($f) ? $client['roots'][$f[0]] . (preg_match('~[./]~', $f[1]) ? $f[1] :
                strtok($key, '.') . '/' . $key . '.' . $f[1]) : $f;
        }
        $this->assertSame(art::manifest()['files'], $whole);
    }

    public function test_bad_packs(): void {
        $this->resetAfterTest();
        $cases = [
            ['code' => 'Bad Code', 'files' => ['icon.saber' => 'a.png']],
            ['code' => 'nofiles', 'files' => ['icon.saber' => 'a.svg']],
            ['name' => 'No code'],
            ['code' => 'badkind', 'kind' => 'galaxy', 'files' => ['icon.saber' => 'a.png']],
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
        $this->assertSame(['base' => 'day', 'music' => 'valley', 'day' => ['lantern' => '#33aacc']], art::manifest()['theme']);
    }

    public function test_a_world_brings_its_sounds(): void {
        $this->resetAfterTest();
        $manifest = art::install($this->make_zip([
            'code' => 'valley',
            'name' => 'The valley',
            'version' => 2,
            'files' => [
                'map.livingthings' => 'map.webp',
                'sound.right' => 'sounds/right.ogg',
                'sound.ambient.meadow' => 'sounds/meadow.ogg',
                'sound.wrong' => 'sounds/fake.mp3',
                'creature' => 'sounds/meadow.ogg',
                'sound.map' => 'map.webp',
            ],
        ], ['map.webp', 'sounds/right.ogg', 'sounds/meadow.ogg', 'sounds/fake.mp3']));
        // Sounds only under «sound.», only of their kinds, and only if they start as a sound does.
        $this->assertSame(['map.livingthings', 'sound.right', 'sound.ambient.meadow'], array_keys($manifest['files']));
        $this->assertStringEndsWith('/art/2/valley/sounds/right.ogg', art::manifest()['files']['sound.right']);
    }

    public function test_a_world_brings_its_puppets_and_voices(): void {
        $this->resetAfterTest();
        $manifest = art::install($this->make_zip([
            'code' => 'valley',
            'name' => 'The valley',
            'version' => 4,
            'files' => [
                'rig.creature.2.fur' => 'rig/creature.webp',
                'rig.creature.2.fur.atlas' => 'rig/creature.atlas.json',
                'anim.rig.creature' => 'anim/creature.json',
                'rig.hero' => 'rig/fake.json',
                'map.livingthings' => 'rig/creature.atlas.json',
                'voice.creature.smell' => 'voices/smell.mp3',
                'voice.hidden.giggle' => 'voices/fake.mp3',
            ],
        ], [
            'rig/creature.webp',
            'rig/creature.atlas.json',
            'anim/creature.json',
            'rig/fake.json',
            'voices/smell.mp3',
            'voices/fake.mp3',
        ]));
        // Data only under «rig.» and «anim.», and only if it is JSON; voices as sounds.
        $this->assertSame(
            ['rig.creature.2.fur', 'rig.creature.2.fur.atlas', 'anim.rig.creature', 'voice.creature.smell'],
            array_keys($manifest['files'])
        );
        // Kept as it was read, with nothing that could close a tag.
        $context = \context_system::instance();
        $file = get_file_storage()->get_file($context->id, 'local_oksigeniatesape', 'art', 0, '/valley/rig/', 'creature.atlas.json');
        $this->assertStringNotContainsString('<', $file->get_content());
        $this->assertSame('<b>', json_decode($file->get_content(), true)['meta']['note']);
    }
}
