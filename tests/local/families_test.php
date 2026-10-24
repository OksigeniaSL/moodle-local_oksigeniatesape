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
 * Tests for the summaries for the families.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\families
 */
#[\PHPUnit\Framework\Attributes\CoversClass(families::class)]
final class families_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_summary_tells_the_path_of_a_student_in_words_without_comparing(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $mission = missions::get($course->id);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', ['firstname' => 'Lucía']);
        $char = characters::create($user->id, 'sage', 'f', null, [], null, ['curious', 'calm']);
        engine::member($char, $course->id);
        // The class lit the clearing; the library is where it is now.
        $DB->set_field(self::T . 'chapter', 'litat', time(), ['missionid' => $mission->id, 'code' => 'claro']);
        foreach (['caracol', 'hormiga'] as $code) {
            $DB->insert_record(self::T . 'found', ['charid' => $char->id, 'courseid' => $course->id, 'missionid' => $mission->id,
                'kind' => 'being', 'code' => $code, 'timecreated' => time()]);
        }
        $s = families::summary($course->id, $user->id);
        $this->assertSame('Lucía', $s['name']);
        $str = fn(string $key, $a = null) => get_string($key, 'local_oksigeniatesape', $a);
        $both = ['a' => $str('app_natureword_curious_f'), 'b' => $str('app_natureword_calm_f')];
        $this->assertSame($str('app_nature_and', $both), $s['nature']);
        $this->assertCount(6, $s['places']);
        $this->assertTrue($s['places'][0]['lit']);
        $this->assertTrue($s['places'][1]['now']);
        $this->assertSame(['el caracol', 'la hormiga'], $s['beings']);
        // What to talk about at home and what the teams aim at, from the place where the class is now.
        $this->assertSame('¿Por qué una seta no es una planta?', $s['home'][0]);
        $this->assertNotEmpty($s['pacts']);
        $this->assertSame('Bosque de las Huellas', $s['next']);
        // With no scene played, nothing to say of what they are learning; and never a number.
        $this->assertSame([], $s['learning']);
        $this->assertSame($str('family_how_clear'), families::how(0.9));
        $this->assertSame($str('family_how_good'), families::how(0.6));
        $this->assertSame($str('family_how_working'), families::how(0.2));
        $this->assertSame([], array_intersect(array_keys($s), ['gems', 'saber', 'level', 'renown', 'adapt', 'rank']));
        // A page for each student, in a PDF.
        $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->assertStringStartsWith('%PDF', families::pdf($course, [(int) $user->id]));
        $this->assertStringStartsWith('%PDF', families::pdf($course));
    }
}
