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
    public function test_a_name_is_read_in_the_app_and_never_compiled(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $names = ['{{ 1 + 1 }}', '@let a = 1;', '@if (1) { b }', '@switch', 'Clase @mates'];
        $game = $gen->create_course(['fullname' => implode(' ', $names)]);
        regions::save((int) $game->id, ['enabled' => 1]);
        missions::install((int) $game->id, 'livingthings', 'valley', 'es');
        $this->setUser($gen->create_and_enrol($game, 'student'));
        foreach ([mobile::mobile_games_view([]), mobile::mobile_course_view(['courseid' => $game->id])] as $page) {
            $html = $page['templates'][0]['html'];
            // Nothing that starts a binding, an expansion or a block of Angular (a block: an at sign and a letter).
            $this->assertStringNotContainsString('{', $html);
            $this->assertStringNotContainsString('}', $html);
            $this->assertDoesNotMatchRegularExpression('/@[a-z]/i', $html);
            // And it is read the same.
            $this->assertStringContainsString('｛｛ 1 + 1 ｝｝', $html);
            $this->assertStringContainsString('Clase ＠mates', $html);
            // The ways in still go where they went.
            $this->assertStringContainsString('href="' . (new \moodle_url(
                '/local/oksigeniatesape/play.php',
                ['id' => $game->id]
            ))->out(false) . '"', $html);
        }
    }

    public function test_a_name_written_with_entities_is_not_compiled_either(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $names = ['&#123;&#123;7*7&#125;&#125;', '&#x7b;&#X7B;8*8&#x7d;&#x7D;', '&#38;#123;&#38;#123;9*9&#38;#125;&#38;#125;',
            '&amp;#123;&amp;#123;6*6&amp;#125;&amp;#125;', '&lbrace;&lbrace;5*5&rbrace;&rbrace;', '&commat;if (a)', '&#64;let b = 1;',
            '&#9999999999;', '&#x110000;', '&#&#1;123;&#&#1;123;4*4&#&#1;125;&#&#1;125;', '&#1;'];
        // In several courses: a full name has its limit.
        $user = $gen->create_user();
        $pages = [];
        foreach (array_chunk($names, 3) as $some) {
            $game = $gen->create_course(['fullname' => implode(' ', $some)]);
            regions::save((int) $game->id, ['enabled' => 1]);
            missions::install((int) $game->id, 'livingthings', 'valley', 'es');
            $gen->enrol_user($user->id, $game->id, 'student');
            $pages[] = $game;
        }
        $this->setUser($user);
        $pages = array_merge(array_map(fn($game) => mobile::mobile_course_view(['courseid' => $game->id]), $pages), [mobile::mobile_games_view([])]);
        foreach ($pages as $page) {
            $html = $page['templates'][0]['html'];
            // Nothing that Angular would read as a brace, an at sign or the start of another entity.
            $this->assertDoesNotMatchRegularExpression('/&#0*(123|125|64|38);|&#x0*(7b|7d|40|26);|&(lbrace|rbrace|commat|lcub|rcub);/i', $html);
            // Nor a number that is no character, which would stop the page from compiling.
            $this->assertDoesNotMatchRegularExpression('/&#(9{10}|x110000|0*1);/i', $html);
            $this->assertStringNotContainsString('{', $html);
            $this->assertDoesNotMatchRegularExpression('/@[a-z]/i', $html);
        }
        // And it is read as what it says, all together in the page of all the games.
        foreach (['｛｛7*7｝｝', '｛｛9*9｝｝', '｛｛4*4｝｝', '＠if (a)'] as $read) {
            $this->assertStringContainsString($read, $html);
        }
    }

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
