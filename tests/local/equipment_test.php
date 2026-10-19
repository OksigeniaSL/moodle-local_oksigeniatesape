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
 * Tests for what the hero wears: in the hand or on the shoulder, only what they carry and the mission lets wear, one in
 * each place; and what it does in the minigames.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\equipment
 */
#[\PHPUnit\Framework\Attributes\CoversClass(equipment::class)]
final class equipment_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_one_item_in_each_place_of_those_carried_that_can_be_worn(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $course->id);
        $carry = function (string $code) use ($member) {
            engine::item($member, missions::item_id($code), 'test', 0, 0);
        };
        // What is not carried is not worn.
        try {
            equipment::wear($char, $course->id, 'lantern', true);
            $this->fail('Worn without carrying it');
        } catch (\moodle_exception $e) {
            $this->assertSame('noequip', $e->errorcode);
        }
        foreach (['lantern', 'magnifier', 'net', 'thread'] as $code) {
            $carry($code);
        }
        equipment::wear($char, $course->id, 'lantern', true);
        equipment::wear($char, $course->id, 'net', true);
        $this->assertSame(['hand' => 'lantern', 'pack' => 'net'], equipment::worn($char, $course->id));
        // The magnifier takes the hand: the lantern goes back to the bag.
        equipment::wear($char, $course->id, 'magnifier', true);
        $this->assertSame(['hand' => 'magnifier', 'pack' => 'net'], equipment::worn($char, $course->id));
        // What the mission does not let wear, no.
        try {
            equipment::wear($char, $course->id, 'thread', true);
            $this->fail('Worn what cannot be worn');
        } catch (\moodle_exception $e) {
            $this->assertSame('noequip', $e->errorcode);
        }
        // In the world: where each is, where it could be and what it does so.
        $this->setUser($user);
        $items = array_column(view::world($course->id, $user->id)['items'], null, 'code');
        $this->assertSame(['', 'hand'], [$items['lantern']['slot'], $items['lantern']['fits']]);
        $this->assertSame('hand', $items['magnifier']['slot']);
        $this->assertNotSame('', $items['net']['worn']);
        $this->assertSame('', $items['thread']['fits']);
        // In the minigames: a look more with the magnifier; in the nest, the net catches one that slips.
        $mission = missions::get($course->id);
        $worn = equipment::worn($char, $course->id);
        $plain = minigames::config($mission, 'quienestavivo', 2, 'healer');
        $lens = minigames::config($mission, 'quienestavivo', 2, 'healer', [], 0, $worn);
        $this->assertSame([0, 1, 1], [$plain['looks'], $lens['looks'], $lens['lens']]);
        $nest = minigames::config($mission, 'nestdefence', 2, 'healer');
        $netted = minigames::config($mission, 'nestdefence', 2, 'healer', [], 0, $worn);
        $this->assertSame([$nest['shield'] + 1, max(1, $nest['goal'] - 1), 1], [$netted['shield'], $netted['goal'],
            $netted['packnet']]);
        // Taken off.
        equipment::wear($char, $course->id, 'net', false);
        $this->assertSame(['hand' => 'magnifier'], equipment::worn($char, $course->id));
    }
}
