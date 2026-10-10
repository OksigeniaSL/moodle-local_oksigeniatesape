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
 * Tests for Defensa del nido: the levels of each step, counting a play on the server, the reward once and what the
 * teacher sees.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minigames
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minigames::class)]
final class minigames_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission and a student whose creature has hatched.
     *
     * @param string $variant
     * @return \stdClass
     */
    private function make_class(string $variant = 'es'): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->mission = missions::get(missions::install($w->course->id, 'livingthings', 'valley', $variant)->courseid);
        $w->user = $dg->create_and_enrol($w->course, 'student');
        $char = characters::create($w->user->id, 'scout', 'f');
        $DB->set_field(self::T . 'char', 'creature', json_encode(scenes::creature($char)), ['id' => $char->id]);
        $DB->set_field(self::T . 'char', 'creaturestage', 1, ['id' => $char->id]);
        $w->member = engine::member(characters::get($w->user->id), $w->course->id);
        return $w;
    }

    /**
     * The events of a play: every animal with its right net, except those given.
     *
     * @param array $game Of minigames::config().
     * @param array $wrong Animal => net thrown.
     * @return array
     */
    private function events(array $game, array $wrong = []): array {
        return array_map(fn($a) => ['a' => $a['id'], 'net' => $wrong[$a['id']] ?? $a['net']], $game['animals']);
    }

    public function test_levels_by_step(): void {
        $this->resetAfterTest();
        $w = $this->make_class('py');
        $first = minigames::config($w->mission, 'nestdefence', 1);
        $this->assertSame(['vertebrate', 'invertebrate'], array_column($first['nets'], 'id'));
        $animals = array_column($first['animals'], null, 'id');
        $this->assertSame('vertebrate', $animals['felino']['net']);
        $this->assertSame('yaguareté', $animals['felino']['label']);
        $this->assertSame('invertebrate', $animals['lombriz']['net']);

        $core = minigames::config($w->mission, 'nestdefence', 2);
        $this->assertCount(6, $core['nets']);
        $animals = array_column($core['animals'], null, 'id');
        $this->assertSame('mammal', $animals['murcielago']['net']);
        $this->assertSame('invertebrate', $animals['arana']['net']);
        $this->assertNotEmpty($animals['murcielago']['why']);

        $expert = minigames::config($w->mission, 'nestdefence', 4);
        $this->assertSame('arachnid', array_column($expert['animals'], null, 'id')['arana']['net']);
        $this->assertGreaterThan($core['speed'], $expert['speed']);
        $this->assertNull(minigames::config($w->mission, 'nogame', 2));
    }

    public function test_winning_gives_the_net_once(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $result = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $this->assertTrue($result['success']);
        $this->assertTrue($result['first']);
        $this->assertTrue($result['coat']);
        $this->assertSame(['net'], $result['items']);
        $this->assertSame(count($game['animals']), $result['caught']);
        $netid = $DB->get_field(self::T . 'item', 'id', ['code' => 'net']);
        $this->assertTrue($DB->record_exists(self::T . 'inventory', ['charid' => $w->member->charid, 'itemid' => $netid]));

        $again = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $this->assertTrue($again['success']);
        $this->assertFalse($again['first']);
        $this->assertSame([], $again['items']);
        $this->assertSame(2, $again['tries']);
    }

    public function test_losing_keeps_the_mix_ups(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $wrong = ['murcielago' => 'bird', 'pinguino' => 'fish', 'salamandra' => 'reptile', 'caballito' => ''];
        $result = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game, $wrong));
        $this->assertFalse($result['success']);
        $this->assertSame(4, $result['missed']);
        $this->assertSame([], $result['items']);
        $this->assertFalse(minigames::has_coat(characters::get($w->user->id), $w->course->id));

        $results = minigames::results($w->mission);
        $this->assertSame(1, $results[0]['players']);
        $this->assertSame(0, $results[0]['won']);
        $this->assertSame('bosque', $results[0]['chapter']);
        $texts = array_column($results[0]['confusions'], 'text');
        $this->assertContains('murciélago → Ave', $texts);
        $this->assertCount(minigames::CONFUSIONS, $texts);
    }

    public function test_what_does_not_fit_is_refused(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $cases = [
            [['a' => 'unicorn', 'net' => 'mammal']],
            [['a' => 'felino', 'net' => 'mammal'], ['a' => 'felino', 'net' => 'mammal']],
            [['a' => 'felino', 'net' => 'insect']],
        ];
        foreach ($cases as $events) {
            try {
                minigames::play($w->course->id, $w->user->id, 'nestdefence', $events);
                $this->fail('Accepted: ' . json_encode($events));
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidgameplay', $e->errorcode);
            }
        }
        // Paused, no play.
        engine::pause($w->member, 'A break', 0);
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
    }

    public function test_coat_of_the_creature(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        try {
            minigames::set_coat($w->course->id, $w->user->id, 'fur');
            $this->fail('A coat before winning');
        } catch (\moodle_exception $e) {
            $this->assertSame('nocoat', $e->errorcode);
        }
        $game = minigames::config($w->mission, 'nestdefence', 2);
        minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $char = minigames::set_coat($w->course->id, $w->user->id, 'feathers');
        $traits = json_decode($char->creature, true);
        $this->assertSame('feathers', $traits['coat']);
        $this->assertNotEmpty($traits['name']);
        $this->assertSame('', json_decode(minigames::set_coat($w->course->id, $w->user->id, '')->creature, true)['coat']);
        $this->expectException(\moodle_exception::class);
        minigames::set_coat($w->course->id, $w->user->id, 'glitter');
    }
}
