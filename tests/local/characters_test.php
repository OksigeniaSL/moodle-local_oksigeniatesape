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
 * Tests for characters and regions.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\characters
 * @covers     \local_oksigeniatesape\local\regions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(characters::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(regions::class)]
final class characters_test extends \advanced_testcase {
    public function test_create_once(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->assertNull(characters::get($user->id));
        $char = characters::create($user->id, 'healer', 'f', 'elf', ['body' => 2, 'skin' => '#a0522d']);
        $this->assertEquals('healer', $char->role);
        $this->assertEquals(1, $char->level);
        $this->assertEquals(['body' => 2, 'skin' => '#a0522d'], json_decode($char->appearance, true));
        $this->assertEquals($char->id, characters::get($user->id)->id);
        $this->expectException(\moodle_exception::class);
        characters::create($user->id, 'sage', 'm');
    }

    public function test_choices_are_checked(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        foreach ([['wizard', 'm', null], ['sage', 'x', null], ['sage', 'm', 'orc']] as [$role, $form, $lineage]) {
            try {
                characters::create($user->id, $role, $form, $lineage);
                $this->fail("Accepted $role $form $lineage");
            } catch (\invalid_parameter_exception $e) {
                $this->assertNull(characters::get($user->id));
            }
        }
    }

    public function test_appearance_keeps_only_plain_values(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $char = characters::create($user->id, 'scout', 'm', null, [
            'hair' => 'curly-3',
            'hairColour' => '#000000',
            'cape' => '<script>',
            'eyes' => 99999,
            'pet' => ['nested'],
            '1bad' => 'x',
        ]);
        $this->assertEquals(['hair' => 'curly-3'], json_decode($char->appearance, true));
        $char = characters::update_look($char, ['hat' => 'none'], 'dwarf');
        $this->assertEquals('dwarf', characters::get($user->id)->lineage);
        $this->assertEquals(['hat' => 'none'], json_decode(characters::get($user->id)->appearance, true));
    }

    public function test_two_words_that_describe_the_hero(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        // Made with three words, one of them twice and one that does not exist: the first two that exist.
        $char = characters::create($user->id, 'sage', 'f', null, [], null, ['calm', 'calm', 'sleepy', 'curious', 'brave']);
        $this->assertSame(['calm', 'curious'], characters::nature($char));
        // They change at any time, or go.
        characters::set_nature($char, ['dreamy']);
        $this->assertSame('dreamy', $DB->get_field('local_oksigeniatesape_char', 'nature', ['id' => $char->id]));
        characters::set_nature($char, []);
        $this->assertSame([], characters::nature(characters::get($user->id)));
    }

    public function test_role_change_once_a_term(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $now = time();
        $char = characters::create($user->id, 'sage', 'm', null, [], $now);
        $char = characters::change_role($char, 'healer', false, $now);
        $this->assertFalse(characters::can_change_role($char, $now + DAYSECS));
        // The teacher can allow it before its time.
        $char = characters::change_role($char, 'scout', true, $now + DAYSECS);
        $this->assertEquals('scout', $char->role);
        $this->assertTrue(characters::can_change_role($char, $now + DAYSECS + characters::ROLE_CHANGE_DAYS * DAYSECS));
        $this->expectException(\moodle_exception::class);
        characters::change_role($char, 'sage', false, $now + 2 * DAYSECS);
    }

    public function test_regions(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(regions::enabled($course->id));
        $region = regions::save($course->id, ['enabled' => 1, 'family' => 'animals', 'weeklycap' => -5, 'nicknames' => 7]);
        $this->assertTrue(regions::enabled($course->id));
        $this->assertEquals('animals', $region->family);
        $this->assertEquals(0, $region->weeklycap);
        $this->assertEquals(2, $region->nicknames);
        // Defaults of the database for what is not given.
        $this->assertEquals(20, $region->dailymanualcap);
        $this->assertEquals(10, regions::rule($region, 'completion'));
        $region = regions::save($course->id, ['rules' => ['completion' => 5, 'unknown' => 9]]);
        $this->assertEquals(['completion' => 5, 'talents' => 1, 'skills' => 1, 'secrets' => 0,
            'library' => 0], json_decode($region->rules, true));
        $this->assertEquals('animals', $region->family);
        $this->expectException(\invalid_parameter_exception::class);
        regions::save($course->id, ['family' => 'robots']);
    }
}
