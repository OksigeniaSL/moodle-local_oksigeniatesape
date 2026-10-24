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

namespace local_oksigeniatesape\output;

use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;

/**
 * Tests for the game in the Moodle app.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\output\mobile
 */
#[\PHPUnit\Framework\Attributes\CoversClass(mobile::class)]
final class mobile_test extends \advanced_testcase {
    public function test_the_games_of_the_user_in_the_app(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $game = $gen->create_course(['fullname' => 'Ciencias 5A']);
        $plain = $gen->create_course(['fullname' => 'Sin juego']);
        regions::save((int) $game->id, ['enabled' => 1]);
        missions::install((int) $game->id, 'livingthings', 'valley', 'es');
        $ana = $gen->create_and_enrol($game, 'student');
        $gen->enrol_user($ana->id, $plain->id, 'student');
        $this->setUser($ana);
        // The menu entry and the tab, only where there is a game.
        $this->assertFalse(mobile::mobile_games_init([])['disabled']);
        $this->assertSame([(int) $game->id], mobile::mobile_course_init([])['restrict']['courses']);
        $html = mobile::mobile_games_view([])['templates'][0]['html'];
        $this->assertStringContainsString('Ciencias 5A', $html);
        $this->assertStringContainsString('core-link inApp="true" href="' . (new \moodle_url(
            '/local/oksigeniatesape/play.php',
            ['id' => $game->id]
        ))->out(false) . '"', $html);
        $this->assertStringContainsString('Ciencias 5A', mobile::mobile_course_view(['courseid' => $game->id])['templates'][0]['html']);
        $none = get_string('mobile_none', 'local_oksigeniatesape');
        $this->assertStringContainsString($none, mobile::mobile_course_view(['courseid' => $plain->id])['templates'][0]['html']);
        // Who plays nowhere has no entry nor tab.
        $this->setUser($gen->create_and_enrol($plain, 'student'));
        $this->assertTrue(mobile::mobile_games_init([])['disabled']);
        $this->assertSame([], mobile::mobile_course_init([])['restrict']['courses']);
        $this->assertStringContainsString($none, mobile::mobile_games_view([])['templates'][0]['html']);
    }
}
