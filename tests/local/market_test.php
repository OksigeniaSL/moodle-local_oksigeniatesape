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

use core_external\external_api;
use local_oksigeniatesape\external\get_world;

/**
 * Tests for the market of the valley and the album of the field guide.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\market
 * @covers     \local_oksigeniatesape\local\album
 */
#[\PHPUnit\Framework\Attributes\CoversClass(market::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(album::class)]
final class market_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on and a team of two with gems and a creature.
     *
     * @param int $gems
     * @return \stdClass course, group, users.
     */
    private function make_class(int $gems): \stdClass {
        global $DB;
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->group = $gen->create_group(['courseid' => $w->course->id]);
        $w->users = [];
        foreach (['sage', 'healer'] as $role) {
            $user = $gen->create_and_enrol($w->course, 'student');
            $gen->create_group_member(['groupid' => $w->group->id, 'userid' => $user->id]);
            $char = characters::create($user->id, $role, 'f');
            $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'gems' => $gems,
                'creature' => json_encode(scenes::creature($char))]);
            engine::member($char, $w->course->id);
            $w->users[] = $user;
        }
        return $w;
    }

    public function test_buying_spends_gems_once_and_the_others_see_it(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(30);
        market::buy($w->course->id, $w->users[0]->id, 'paint-gold');
        $this->assertEquals(20, characters::get($w->users[0]->id)->gems);
        $this->assertEquals(1, $DB->count_records(self::T . 'ledger', ['reason' => 'market', 'resource' => 'gems']));
        try {
            market::buy($w->course->id, $w->users[0]->id, 'paint-gold');
            $this->fail('Bought twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadyowned', $e->errorcode);
        }
        market::buy($w->course->id, $w->users[0]->id, 'aura-fireflies');
        // Not enough for another one.
        try {
            market::buy($w->course->id, $w->users[0]->id, 'aura-leaves');
            $this->fail('Bought without gems');
        } catch (\moodle_exception $e) {
            $this->assertSame('notenoughgems', $e->errorcode);
        }
        // A teammate sees the paint on the creature and the aura on the hero.
        $mates = view::world($w->course->id, $w->users[1]->id)['team']['members'];
        $buyer = array_values(array_filter($mates, fn($m) => $m['userid'] == $w->users[0]->id))[0]['character'];
        $this->assertSame('aura-fireflies', $buyer['aura']);
        $this->assertSame('paint-gold', json_decode($buyer['creature'], true)['paint']);
        // Taking it off and wearing it again costs nothing.
        market::wear($w->users[0]->id, 'aura');
        $this->assertSame([], array_diff_key(market::worn(characters::get($w->users[0]->id)), ['paint' => 1]));
        market::wear($w->users[0]->id, 'aura-fireflies');
        $this->assertEquals(5, characters::get($w->users[0]->id)->gems);
        // What one does not own cannot be worn.
        $this->expectException(\moodle_exception::class);
        market::wear($w->users[0]->id, 'paint-snow');
    }

    public function test_the_team_pays_for_the_banner_together(): void {
        $this->resetAfterTest();
        $w = $this->make_class(12);
        market::buy($w->course->id, $w->users[0]->id, 'banner-lantern', 12);
        $this->assertSame([], market::team_owns($w->course->id, $w->group->id));
        // Only what is missing is taken.
        market::buy($w->course->id, $w->users[1]->id, 'banner-lantern', 12);
        $this->assertEquals(4, characters::get($w->users[1]->id)->gems);
        $this->assertSame(['banner-lantern'], market::team_owns($w->course->id, $w->group->id));
        $world = view::world($w->course->id, $w->users[1]->id);
        $this->assertSame(['banner-lantern'], $world['team']['banner']);
        $banner = array_values(array_filter($world['market'], fn($x) => $x['code'] === 'banner-lantern'))[0];
        $this->assertTrue($banner['owned']);
        $this->assertSame(8, $banner['mine']);
        $this->setUser($w->users[1]);
        external_api::clean_returnvalue(get_world::execute_returns(), $world);
        // Paid in full: nothing more goes in.
        try {
            market::buy($w->course->id, $w->users[1]->id, 'banner-lantern', 1);
            $this->fail('Gems put into something already paid');
        } catch (\moodle_exception $e) {
            $this->assertSame('alreadyowned', $e->errorcode);
        }
    }

    public function test_the_album_counts_the_entries_of_the_team(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(0);
        $data = $this->getDataGenerator()->create_module('data', ['course' => $w->course->id]);
        $gen = $this->getDataGenerator()->get_plugin_generator('mod_data');
        $gen->create_field((object) ['name' => 'Nombre', 'type' => 'text'], $data);
        $gen->create_field((object) ['name' => 'Grupo', 'type' => 'menu', 'param1' => "Ave\nPlanta"], $data);
        $gen->create_field((object) ['name' => 'Cadena', 'type' => 'menu', 'param1' => "Productor\nHerbívoro"], $data);
        $ids = $DB->get_records_menu('data_fields', ['dataid' => $data->id], 'id', 'name, id');
        $entry = fn(string $name, string $group, string $chain) => [$ids['Nombre'] => $name, $ids['Grupo'] => $group,
            $ids['Cadena'] => $chain];
        activities::save($w->course->id, (int) $data->cmid, ['saber' => 30]);
        $other = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        $this->setUser($w->users[0]);
        $gen->create_entry($data, $entry('Hornero', 'Ave', 'Herbívoro'));
        $this->setUser($w->users[1]);
        $gen->create_entry($data, $entry('Lapacho', 'Planta', ''));
        $this->setUser($other);
        $gen->create_entry($data, $entry('Yacaré', '', ''));
        $album = album::for_student($w->course->id, $w->users[0]->id);
        $this->assertSame(['Hornero', 'Lapacho'], array_column($album['cards'], 'name'));
        $this->assertSame([true, false], array_column($album['cards'], 'golden'));
        $this->assertSame([true, false], array_column($album['cards'], 'mine'));
        $this->assertEquals(3, $album['class']);
        $this->assertEquals($data->cmid, $album['cmid']);
        // Where the guide asks for approval, an entry not approved yet is no card.
        $DB->set_field('data', 'approval', 1, ['id' => $data->id]);
        $DB->set_field('data_records', 'approved', 1, ['userid' => $w->users[0]->id]);
        $this->assertCount(1, album::for_student($w->course->id, $w->users[0]->id)['cards']);
    }
}
