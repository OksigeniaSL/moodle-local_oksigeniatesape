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

namespace local_oksigeniatesape\external;

use core_external\external_api;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/autoplayer.php');

/**
 * Tests for the web services of teams, powers, minigames and the box of ideas.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\external\make_teams
 * @covers     \local_oksigeniatesape\external\use_power
 * @covers     \local_oksigeniatesape\external\get_powers
 * @covers     \local_oksigeniatesape\external\decide_power
 * @covers     \local_oksigeniatesape\external\save_power
 * @covers     \local_oksigeniatesape\external\get_minigame
 * @covers     \local_oksigeniatesape\external\play_minigame
 * @covers     \local_oksigeniatesape\external\set_coat
 * @covers     \local_oksigeniatesape\external\send_idea
 * @covers     \local_oksigeniatesape\external\get_ideas
 * @covers     \local_oksigeniatesape\external\handle_idea
 */
#[\PHPUnit\Framework\Attributes\CoversClass(make_teams::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(use_power::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_powers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(decide_power::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(save_power::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_minigame::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(play_minigame::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_coat::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(send_idea::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(get_ideas::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(handle_idea::class)]
final class team_externals_test extends \advanced_testcase {
    /**
     * Calls a web service as a user and checks what it returns against its description.
     *
     * @param string $class
     * @param \stdClass $user
     * @param array $args
     * @return mixed
     */
    private function call(string $class, \stdClass $user, array $args) {
        $this->setUser($user);
        $class = __NAMESPACE__ . '\\' . $class;
        return external_api::clean_returnvalue($class::execute_returns(), $class::execute(...$args));
    }

    /**
     * A course with the game and the mission, a teacher, a non-editing teacher and four students with characters.
     *
     * @return \stdClass
     */
    private function make_class(): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        $w->other = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        regions::save($w->other->id, ['enabled' => 1]);
        missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $w->teacher = $dg->create_and_enrol($w->course, 'editingteacher');
        $w->assistant = $dg->create_and_enrol($w->course, 'teacher');
        $w->students = [];
        foreach (['protector', 'healer', 'sage', 'scout'] as $role) {
            $user = $dg->create_and_enrol($w->course, 'student');
            $this->call('create_character', $user, [$w->course->id, $role, 'm']);
            $w->students[$role] = $user;
        }
        return $w;
    }

    public function test_teams_and_powers(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        // Making teams needs to manage the groups of the course.
        try {
            $this->call('make_teams', $w->assistant, [$w->course->id, 4]);
            $this->fail('A non-editing teacher made the teams');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->assertSame(1, $this->call('make_teams', $w->teacher, [$w->course->id, 4])['teams']);

        $world = $this->call('get_world', $w->students['healer'], [$w->course->id]);
        $this->assertCount(4, $world['team']['members']);
        $this->assertSame(0, $world['team']['week']['saber']);
        $this->assertCount(1, $world['standings']);
        $breather = array_values(array_filter($world['powers'], fn($p) => $p['code'] === 'breather'))[0];
        $this->assertSame('mate', $breather['target']);
        $this->assertFalse($breather['locked']);

        // The healer gives a breather to the sage; a teacher approves it.
        $world = $this->call('use_power', $w->students['healer'], [$w->course->id, $breather['id'],
            $w->students['sage']->id]);
        $this->assertSame('pending', $world['powerrequests'][0]['status']);
        $this->assertTrue(array_values(array_filter($world['powers'], fn($p) => $p['code'] === 'breather'))[0]['pending']);
        $powers = $this->call('get_powers', $w->assistant, [$w->course->id]);
        $this->assertCount(1, $powers['pending']);
        $this->assertSame($w->students['sage']->firstname, $powers['pending'][0]['target']);

        // Not from another course.
        try {
            $this->call('decide_power', $w->teacher, [$w->other->id, $powers['pending'][0]['id'], true]);
            $this->fail('Decided from another course');
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $powers = $this->call('decide_power', $w->assistant, [$w->course->id, $powers['pending'][0]['id'], true]);
        $this->assertCount(0, $powers['pending']);
        $this->assertSame('approved', $powers['decided'][0]['status']);

        // Only an editing teacher changes the catalog.
        $hint = array_values(array_filter($powers['powers'], fn($p) => $p['code'] === 'hint'))[0];
        try {
            $this->call('save_power', $w->assistant, [$w->course->id, $hint['id'], 'all', '', '', 1, 1, false, false]);
            $this->fail('A non-editing teacher changed the catalog');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $powers = $this->call('save_power', $w->teacher, [$w->course->id, $hint['id'], 'all', '', '', 1, 1, false, false]);
        $hint = array_values(array_filter($powers['powers'], fn($p) => $p['code'] === 'hint'))[0];
        $this->assertFalse($hint['enabled']);
        $this->assertSame('sage', $hint['role']);
        $world = $this->call('get_world', $w->students['sage'], [$w->course->id]);
        $this->assertNotContains('hint', array_column($world['powers'], 'code'));

        // Students cannot answer requests.
        $this->expectException(\required_capability_exception::class);
        $this->call('get_powers', $w->students['sage'], [$w->course->id]);
    }

    public function test_minigame_and_coat(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $student = $w->students['scout'];
        // Without a creature yet, no coat.
        try {
            $this->call('set_coat', $student, [$w->course->id, 'scales']);
            $this->fail('A coat without a creature');
        } catch (\moodle_exception $e) {
            $this->assertSame('nocoat', $e->errorcode);
        }
        $nest = $this->call('get_world', $student, [$w->course->id])['mission']['chapters'][0]['scenes'][0];
        $scene = $this->call('get_scene', $student, [$w->course->id, $nest['id']]);
        $events = \local_oksigeniatesape\autoplayer::play(json_decode($scene['definition'], true));
        $this->assertTrue($this->call('play_scene', $student, [$w->course->id, $nest['id'], json_encode($events)])['rewards']['hatched']);
        $game = $this->call('get_minigame', $student, [$w->course->id, 'nestdefence']);
        $this->assertCount(6, $game['nets']);
        $world = $this->call('get_world', $student, [$w->course->id]);
        $this->assertSame('nestdefence', $world['mission']['chapters'][2]['games'][0]['code']);
        $this->assertFalse($world['mission']['chapters'][2]['games'][0]['done']);

        $events = array_map(fn($a) => ['a' => $a['id'], 'net' => $a['net']], $game['animals']);
        $result = $this->call('play_minigame', $student, [$w->course->id, 'nestdefence', json_encode($events)]);
        $this->assertTrue($result['success']);
        $this->assertSame(['la red de captura'], $result['items']);
        $this->assertTrue($result['world']['hascoat']);
        $this->assertTrue($result['world']['mission']['chapters'][2]['games'][0]['done']);
        $this->assertContains('net', array_column($result['world']['items'], 'code'));
        $world = $this->call('set_coat', $student, [$w->course->id, 'scales']);
        $this->assertSame('scales', json_decode($world['character']['creature'], true)['coat']);

        $results = $this->call('get_results', $w->teacher, [$w->course->id]);
        $this->assertSame(1, $results['games'][0]['won']);
        $this->expectException(\invalid_parameter_exception::class);
        $this->call('play_minigame', $student, [$w->course->id, 'nestdefence', '{"a":"felino"}']);
    }

    public function test_box_of_ideas(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $world = $this->call('send_idea', $w->students['sage'], [$w->course->id, 'A map <b>of</b> our school', true]);
        $this->assertSame('new', $world['ideas'][0]['status']);
        $ideas = $this->call('get_ideas', $w->assistant, [$w->course->id]);
        $this->assertSame('', $ideas[0]['fullname']);
        $this->assertSame('A map of our school', $ideas[0]['message']);
        $ideas = $this->call('handle_idea', $w->assistant, [$w->course->id, $ideas[0]['id'], 'read']);
        $this->assertSame('read', $ideas[0]['status']);
        $this->assertSame(0, $this->call('get_class', $w->teacher, [$w->course->id])['ideasunread']);
        $this->expectException(\required_capability_exception::class);
        $this->call('get_ideas', $w->students['sage'], [$w->course->id]);
    }
}
