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
}
