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

namespace local_oksigeniatesape\event;

use local_oksigeniatesape\local\autoplayer;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\live;
use local_oksigeniatesape\local\minigames;
use local_oksigeniatesape\local\minions;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\scenes;
use local_oksigeniatesape\local\sessions;

/**
 * Tests for the events of Moodle the game triggers: its log, and what reads it (reports, an xAPI log store).
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\event\character_created
 * @covers     \local_oksigeniatesape\event\scene_completed
 * @covers     \local_oksigeniatesape\event\minigame_completed
 * @covers     \local_oksigeniatesape\event\lantern_lit
 * @covers     \local_oksigeniatesape\event\battle_ended
 * @covers     \local_oksigeniatesape\event\minion_untangled
 */
#[\PHPUnit\Framework\Attributes\CoversClass(character_created::class)]
final class events_test extends \advanced_testcase {
    public function test_the_game_tells_moodle_what_happens(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $mission = missions::get($c);
        $user = $gen->create_and_enrol($course, 'student');
        $sink = $this->redirectEvents();
        $char = characters::create($user->id, 'scout', 'f');
        $member = engine::member($char, $c);
        // A scene played well.
        $scene = $DB->get_record('local_oksigeniatesape_scene', ['missionid' => $mission->id, 'code' => 'C1-1']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($user->id), $c, (int) $mission->id);
        scenes::play($c, $user->id, $scene->id, autoplayer::play(
            json_decode($scene->definition, true),
            $carried,
            $roles,
            $flags,
            $role
        ), null);
        // A minigame, every animal with its net.
        $game = minigames::config($mission, 'nestdefence', (int) $member->step);
        minigames::play($c, $user->id, 'nestdefence', array_map(fn($a) => ['a' => $a['id'], 'net' => $a['net']], $game['animals']));
        // A minion untangled, a lantern lit and a battle ended.
        minions::untangle($char, $member, $mission, ['code' => 'piedra'], time());
        $DB->set_field('local_oksigeniatesape_chapter', 'readyat', time() - 10, ['missionid' => $mission->id, 'code' => 'torre']);
        sessions::light_ready((int) $mission->id, time());
        live::finish(live::start($c, 'phones', 4, 30), 0);
        $events = [];
        foreach ($sink->get_events() as $event) {
            if (str_starts_with(get_class($event), 'local_oksigeniatesape\\event\\')) {
                $events[substr(get_class($event), strlen('local_oksigeniatesape\\event\\'))] = $event;
            }
        }
        $sink->close();
        $this->assertEqualsCanonicalizing(['character_created', 'scene_completed', 'minigame_completed', 'minion_untangled',
            'lantern_lit', 'battle_ended'], array_keys($events));
        $this->assertSame((int) $char->id, (int) $events['character_created']->objectid);
        $this->assertSame('C1-1', $events['scene_completed']->other['scene']);
        $this->assertSame($c, (int) $events['scene_completed']->courseid);
        $this->assertSame((int) $user->id, (int) $events['scene_completed']->userid);
        $this->assertTrue($events['minigame_completed']->other['success']);
        $this->assertSame('piedra', $events['minion_untangled']->other['minion']);
        $this->assertSame('torre', $events['lantern_lit']->other['place']);
        $this->assertSame('lost', $events['battle_ended']->other['outcome']);
        foreach ($events as $event) {
            $this->assertNotSame('', $event->get_name());
            $this->assertNotSame('', $event->get_description());
            $this->assertInstanceOf(\moodle_url::class, $event->get_url());
        }
    }
}
