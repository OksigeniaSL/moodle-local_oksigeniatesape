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
 * Tests for the sky of the valley: the zone of the course and its hemisphere for the page, and the seasons of the
 * mission for its map.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\page
 */
#[\PHPUnit\Framework\Attributes\CoversClass(page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(view::class)]
final class sky_test extends \advanced_testcase {
    /**
     * The sky the page gives the game of a course.
     *
     * @param \stdClass $course
     * @return array
     */
    private function sky(\stdClass $course): array {
        global $PAGE;
        $PAGE = new \moodle_page();
        $PAGE->set_url('/local/oksigeniatesape/play.php', ['id' => $course->id]);
        ob_start();
        page::output('game', $course);
        preg_match('/window\.TESAPE_CONFIG = (\{.*?\});<\/script>/s', ob_get_clean(), $m);
        return json_decode($m[1], true)['sky'];
    }

    public function test_the_sky_is_that_of_the_place_of_the_course(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1, 'timezone' => 'Europe/Madrid']);
        missions::install($c, 'livingthings', 'valley', 'es');
        $this->setUser($gen->create_and_enrol($course, 'student'));
        $this->assertSame(['zone' => 'Europe/Madrid', 'south' => false], $this->sky($course));
        // In Asunción, the seasons go the other way round.
        regions::save($c, ['timezone' => 'America/Asuncion']);
        $this->assertSame(['zone' => 'America/Asuncion', 'south' => true], $this->sky($course));
        // What happens in each season, for the map.
        $seasons = view::seasons(missions::get($c));
        $this->assertSame(['spring', 'summer', 'autumn', 'winter'], array_keys($seasons));
        $this->assertStringContainsString('hoja caduca', $seasons['autumn']);
    }

    public function test_the_cinematics_of_the_class_screen_in_their_story(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save((int) $course->id, ['enabled' => 1]);
        missions::install((int) $course->id, 'livingthings', 'azara', 'py');
        $cine = array_column(view::cinematics(missions::get((int) $course->id)), null, 'code');
        $this->assertSame(['prologo', 'embrollo'], array_keys($cine));
        $this->assertCount(5, $cine['prologo']['steps']);
        $this->assertStringContainsString('1781', $cine['prologo']['steps'][0]['text']);
        $this->assertSame(['boss', 'in'], [$cine['embrollo']['steps'][1]['image'], $cine['embrollo']['steps'][1]['move']]);
    }

    public function test_the_stage_of_the_class(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        $region = regions::save($c, ['enabled' => 1]);
        $this->assertSame('primary', regions::stage($region));
        $early = $gen->create_and_enrol($course, 'student');
        $this->assertSame(2, (int) engine::member(characters::create($early->id, 'sage', 'm'), $c)->step);
        // The first years of primary: whoever comes in starts on the first steps.
        $this->setUser($gen->create_and_enrol($course, 'editingteacher'));
        $saved = \local_oksigeniatesape\external\save_region::execute($c, stage: 'firstyears');
        $this->assertSame('firstyears', $saved['stage']);
        $late = $gen->create_and_enrol($course, 'student');
        $this->assertSame(1, (int) engine::member(characters::create($late->id, 'scout', 'f'), $c)->step);
        // Those who were there keep theirs.
        $this->assertSame(2, (int) engine::member(characters::get($early->id), $c)->step);
        try {
            \local_oksigeniatesape\external\save_region::execute($c, stage: 'kinder');
            $this->fail('A stage that is not');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('stage', $e->getMessage());
        }
    }
}
