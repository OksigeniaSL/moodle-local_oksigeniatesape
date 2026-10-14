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
 * Tests for the themes: the one of each class, the colours of a world and what is left out of them.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\themes
 */
#[\PHPUnit\Framework\Attributes\CoversClass(themes::class)]
final class themes_test extends \advanced_testcase {
    public function test_only_colours_come_in(): void {
        $theme = themes::clean([
            'base' => 'day',
            'music' => 'sea',
            'day' => [
                'lantern' => '#F5B83D',
                'card-a' => '#ffffffee',
                'text' => '#123',
                'night' => 'red',
                'sky' => '#fff;}body{display:none',
                'position' => '#000000',
            ],
            'night' => 'not a list',
        ]);
        $this->assertSame('day', $theme['base']);
        $this->assertSame('sea', $theme['music']);
        $this->assertSame(['lantern' => '#f5b83d', 'card-a' => '#ffffffee', 'text' => '#123'], $theme['day']);
        $this->assertArrayNotHasKey('night', $theme);
        $this->assertSame(['base' => 'night', 'music' => 'valley'], themes::clean(['base' => 'sea', 'music' => 'rock']));
        $this->assertSame(['base' => 'night', 'music' => 'valley'], themes::clean(null));
    }

    public function test_the_colours_of_a_world_become_css(): void {
        $art = ['theme' => ['base' => 'night', 'night' => ['lantern' => '#ffc857', 'text' => '#eee'], 'day' => [
            'night' => '#fafafa']]];
        $css = themes::style($art);
        $this->assertStringContainsString('[data-theme="night"]{--lantern:#ffc857;--lantern-rgb:255,200,87;--text:#eee;' .
            '--hi:238,238,238}', $css);
        $this->assertStringContainsString('[data-theme="day"]{--night:#fafafa;--night-rgb:250,250,250}', $css);
        $this->assertSame('', themes::style(null));
        $this->assertSame('#fafafa', themes::bar('day', $art));
        $this->assertSame('#0f2131', themes::bar('night', $art));
    }

    public function test_the_teacher_chooses_or_the_world_does(): void {
        $this->resetAfterTest();
        $day = ['theme' => ['base' => 'day']];
        $this->assertSame('night', themes::current('', null));
        $this->assertSame('day', themes::current('', $day));
        $this->assertSame('night', themes::current('night', $day));
        $this->assertSame('day', themes::current('day', null));

        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1, 'look' => 'day']);
        $this->assertSame('day', regions::get($course->id)->look);
        $this->expectException(\invalid_parameter_exception::class);
        regions::save($course->id, ['look' => 'sea']);
    }

    public function test_the_page_comes_with_the_theme_of_the_class(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($course, 'student'));
        regions::save($course->id, ['enabled' => 1, 'look' => 'day']);
        ob_start();
        page::output('game', $course);
        $html = ob_get_clean();
        $this->assertStringContainsString('data-theme="day"', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#eef4f8">', $html);
        preg_match('/window\.TESAPE_CONFIG = (\{.*?\});<\/script>/s', $html, $m);
        $config = json_decode($m[1], true);
        $this->assertSame(['current' => 'day', 'world' => 'night'], $config['theme']);
    }
}
