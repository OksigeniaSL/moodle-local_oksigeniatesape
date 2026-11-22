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
 * Tests for the mission of the climb (`subida`): installed in a course and played whole, as a student plays it, by the
 * services of always.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\scenes
 * @covers     \local_oksigeniatesape\local\climb
 */
#[\PHPUnit\Framework\Attributes\CoversClass(scenes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(climb::class)]
final class climb_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_first_lantern_is_played_whole(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'subida', 'valley', 'es');
        $places = $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$mission->id]);
        $this->assertSame(['claro'], array_values($places));
        $scenes = $DB->get_records_menu(self::T . 'scene', ['missionid' => $mission->id], 'code', 'code, id');
        $this->assertSame(['L1-algo', 'L1-duelo', 'L1-nido'], array_keys($scenes));
        $user = $dg->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        engine::member($char, $course->id);
        $this->setUser($user);
        $play = fn($code, $events) => scenes::play($course->id, $user->id, (int) $scenes[$code], $events, null);
        // The Nest: a look and a try, the five living things next to the seed and the three others aside.
        $nest = [['n' => 'nido', 'verb' => 'lombriz'], ['n' => 'nido', 'probe' => 'cristal']];
        foreach (['lombriz', 'seta', 'musgo', 'bellota', 'rana'] as $id) {
            $nest[] = ['n' => 'nido', 'leave' => $id, 'to' => 'seed'];
        }
        foreach (['cristal', 'pluma', 'burbuja'] as $id) {
            $nest[] = ['n' => 'nido', 'leave' => $id, 'to' => 'aside'];
        }
        $this->assertSame('success', $play('L1-nido', $nest)['outcome']);
        // Someone hiding, found with one trail: the find shines.
        $hunt = $play('L1-algo', [['n' => 'algo', 'clue' => 'r1-sage'], ['n' => 'algo', 'guess' => 'caracol']]);
        $this->assertSame('success', $hunt['outcome']);
        // The duel, lost at the second knot; the rematch keeps what was solved and wins.
        $duel = $play('L1-duelo', [['n' => 'duelo', 'touch' => 'f2'], ['n' => 'duelo', 'touch' => 'f1'],
            ['n' => 'duelo', 'touch' => 'f3'], ['n' => 'revancha', 'touch' => 'f4'], ['n' => 'revancha', 'touch' => 'f5']]);
        $this->assertSame('success', $duel['outcome']);
        // The three scenes of the clearing: its lantern is lit for the student.
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => 'claro'], '*', MUST_EXIST);
        $this->assertTrue(scenes::chapter_done(characters::get($user->id), 2, $chapter));
        // On the path: the three steps in the order of the place, done; the lantern of the path not yet lit.
        $state = climb::state($course->id, $user->id);
        $this->assertSame(['L1-nido', 'L1-algo', 'L1-duelo'], array_column($state['scenes'], 'code'));
        $this->assertSame([true, true, true], array_column($state['scenes'], 'done'));
        $this->assertSame(['El Nido', 'Algo se mueve', 'El duelo'], array_column($state['scenes'], 'title'));
        $this->assertFalse($state['paid']);
        $this->assertFalse($state['sniff']);
        // Its moment: five gems, once, and the creature learns to sniff.
        $before = $state['gems'];
        $state = climb::light($course->id, $user->id);
        $this->assertTrue($state['paid']);
        $this->assertTrue($state['sniff']);
        $this->assertSame($before + 5, $state['gems']);
        $this->assertSame($before + 5, climb::light($course->id, $user->id)['gems']);
        // The creature is named, or not.
        $this->assertSame('Kapi', climb::name($course->id, $user->id, '  Kapi ')['creature']['name']);
        $this->assertSame('', climb::name($course->id, $user->id, '')['creature']['name']);
    }

    public function test_a_hero_is_chosen_on_the_path(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        missions::install($course->id, 'subida', 'valley', 'es');
        $user = $dg->create_and_enrol($course, 'student');
        $this->setUser($user);
        $this->assertNull(climb::state($course->id, $user->id)['hero']);
        $state = climb::hero($course->id, $user->id, 'scout', 'm');
        $this->assertSame(['role' => 'scout', 'nameform' => 'm'], $state['hero']);
        // Chosen again: another figure; the lantern is not lit before its scenes.
        $this->assertSame('f', climb::hero($course->id, $user->id, 'scout', 'f')['hero']['nameform']);
        $this->assertFalse(climb::light($course->id, $user->id)['paid']);
        $this->assertNotNull(climb::mission($course->id));
    }
}
