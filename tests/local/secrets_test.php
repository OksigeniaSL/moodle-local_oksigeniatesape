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
 * Tests for the touchable secrets of the places.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\secrets
 */
#[\PHPUnit\Framework\Attributes\CoversClass(secrets::class)]
final class secrets_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_a_secret_goes_to_the_album_with_its_mark_its_page_and_the_mark_of_its_set(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $mission = missions::get($course->id);
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        $member = engine::member($char, $course->id);
        $has = fn($kind, $code) => $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'kind' => $kind,
            'code' => $code]);
        // In the library, the second print and the empty jar, still hidden.
        $this->assertEqualsCanonicalizing(['huella-2', 'frasco'], array_column(secrets::in_place(
            $char,
            $mission,
            'biblioteca',
            []
        ), 'id'));
        $jar = secrets::find($char, $member, 'frasco');
        $this->assertStringContainsString('frasco vacío', $jar['text']);
        $this->assertTrue($has('flag', 'frasco-vacio'));
        $this->assertSame(['huella-2'], array_column(secrets::in_place(
            $char,
            $mission,
            'biblioteca',
            secrets::found($char, $mission)
        ), 'id'));
        // The page under a leaf gives its page of the Great Book, once.
        $leaf = secrets::find($char, $member, 'pagina-hoja');
        $this->assertNotSame('', $leaf['page']['title']);
        $this->assertTrue($has('page', 'descomponedores'));
        $this->assertSame('', secrets::find($char, $member, 'pagina-hoja')['page']['title']);
        // The six prints: the last one says what the Embrollo is made of, and leaves its mark.
        foreach (range(1, 5) as $n) {
            $this->assertSame('', secrets::find($char, $member, 'huella-' . $n)['set']);
        }
        $this->assertFalse($has('flag', 'seis-huellas'));
        $last = secrets::find($char, $member, 'huella-6');
        $this->assertStringContainsString('sin comprobar', $last['set']);
        $this->assertTrue($has('flag', 'seis-huellas'));
        $album = secrets::album($mission, secrets::found($char, $mission));
        $this->assertCount(8, $album['found']);
        $this->assertSame(8, $album['total']);
        // No saber nor gems for a secret.
        $this->assertSame(0, (int) $DB->count_records(self::T . 'ledger', ['charid' => $char->id]));
        // A secret that does not exist is not found.
        $this->expectException(\moodle_exception::class);
        secrets::find($char, $member, 'nada');
    }

    public function test_the_teacher_shows_few_at_a_time(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1, 'rules' => ['secrets' => 1]]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $mission = missions::get($course->id);
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        $member = engine::member($char, $course->id);
        // One at a time: in the library, only the first; the other is not there to find yet.
        $shown = array_column(secrets::in_place($char, $mission, 'biblioteca', []), 'id');
        $this->assertCount(1, $shown);
        $other = array_values(array_diff(['huella-2', 'frasco'], $shown))[0];
        try {
            secrets::find($char, $member, $other);
            $this->fail('A secret the painting does not show yet is found');
        } catch (\moodle_exception $e) {
            $this->assertSame('nosecret', $e->errorcode);
        }
        // Found the one shown, the next one shows.
        secrets::find($char, $member, $shown[0]);
        $this->assertSame([$other], array_column(secrets::in_place(
            $char,
            $mission,
            'biblioteca',
            secrets::found($char, $mission)
        ), 'id'));
        $this->assertNotSame('', secrets::find($char, $member, $other)['text']);
        // All at once again, as it came.
        regions::save($course->id, ['rules' => ['secrets' => 0]]);
        $this->assertCount(2, secrets::in_place($char, $mission, 'jardin', []));
    }
}
