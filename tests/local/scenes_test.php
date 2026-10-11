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

use local_oksigeniatesape\autoplayer;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/autoplayer.php');

/**
 * Tests for missions in a course and playing their scenes: what is kept, what is given and when lanterns light.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\missions
 * @covers     \local_oksigeniatesape\local\scenes
 * @covers     \local_oksigeniatesape\local\results
 */
#[\PHPUnit\Framework\Attributes\CoversClass(missions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(scenes::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(results::class)]
final class scenes_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on, the pilot mission and some students with characters.
     *
     * @param int $students
     * @return \stdClass
     */
    private function make_class(int $students = 1): \stdClass {
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $w->mission = missions::install($w->course->id, 'livingthings', 'valley', 'py');
        $w->users = [];
        for ($i = 0; $i < $students; $i++) {
            $user = $dg->create_and_enrol($w->course, 'student');
            characters::create($user->id, 'sage', 'f');
            engine::member(characters::get($user->id), $w->course->id);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * Plays a scene of the mission well.
     *
     * @param \stdClass $w
     * @param \stdClass $user
     * @param string $code
     * @param array $items Items the student carries, for the autoplayer.
     * @return array
     */
    private function play(\stdClass $w, \stdClass $user, string $code, array $items = []): array {
        global $DB;
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => $code]);
        return scenes::play($w->course->id, $user->id, $scene->id, autoplayer::play(
            json_decode($scene->definition, true),
            $items
        ), null);
    }

    public function test_install(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(0);
        $this->assertSame('py', $w->mission->variant);
        $this->assertEquals(6, $DB->count_records(self::T . 'chapter', ['missionid' => $w->mission->id]));
        $this->assertEquals(15, $DB->count_records(self::T . 'scene', ['missionid' => $w->mission->id]));
        $this->assertTrue($DB->record_exists(self::T . 'item', ['code' => 'naturalistlens']));
        // A classroom challenge per chapter, from the activity off the screen.
        $challenges = classroom::list($w->course->id);
        $this->assertCount(6, $challenges);
        $this->assertEquals(missions::CHAPTER_CHALLENGE_SABER, reset($challenges)->saber);
        // Installing again changes the skin and keeps everything else.
        missions::install($w->course->id, 'livingthings', 'azara', 'es');
        $this->assertEquals(1, $DB->count_records(self::T . 'mission', ['courseid' => $w->course->id]));
        $this->assertEquals(15, $DB->count_records(self::T . 'scene', ['missionid' => $w->mission->id]));
        $this->assertCount(6, classroom::list($w->course->id));
        $this->assertSame('azara', missions::get($w->course->id)->skin);
        $this->expectException(\invalid_parameter_exception::class);
        missions::install($w->course->id, 'livingthings', 'mars', 'es');
    }

    public function test_first_scene_hatches_the_creature_and_lights_the_lantern(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $result = $this->play($w, $user, 'C1-2');
        $this->assertSame('success', $result['outcome']);
        $r = $result['rewards'];
        $this->assertSame(20, $r['saber']);
        $this->assertTrue($r['hatched']);
        $this->assertSame(['claro'], $r['lanterns']);
        $this->assertSame(['ser-vivo'], $r['pages']);
        $this->assertSame('claro', $r['chapter']);
        // Alone in the class, they are more than half of it.
        $this->assertSame('claro', $r['classlantern']);
        $char = characters::get($user->id);
        $this->assertEquals(1, $char->creaturestage);
        $creature = json_decode($char->creature, true);
        $this->assertSame(scenes::creature($char), $creature);
        // Playing it again is kept, but gives nothing.
        $again = $this->play($w, $user, 'C1-2');
        $this->assertSame(0, $again['rewards']['saber']);
        $this->assertFalse($again['rewards']['hatched']);
        $this->assertSame(2, $again['attempt']);
        $this->assertEquals(20, characters::get($user->id)->saber);
        $this->assertEquals(2, $DB->count_records(self::T . 'sceneattempt', ['charid' => $char->id]));
    }

    public function test_first_steps_have_their_own_scenes_and_everybody_meets_the_embrollo(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $codes = function (string $chapter, int $step) use ($DB, $w): array {
            $chapterid = $DB->get_field(self::T . 'chapter', 'id', ['missionid' => $w->mission->id, 'code' => $chapter]);
            $scenes = array_values($DB->get_records(self::T . 'scene', ['chapterid' => $chapterid], 'code'));
            return array_map(fn($sc) => $sc->code, scenes::for_step($scenes, $step));
        };
        $this->assertSame(['C1-1'], $codes('claro', 1));
        $this->assertSame(['C1-2'], $codes('claro', 2));
        $this->assertSame(['C6-1', 'C6-2'], $codes('torre', 1));
        $this->assertSame(['C6-2'], $codes('torre', 2));
        // The first scene of first steps gives what the nest gives: the creature, the lantern and the first page.
        $member = engine::member(characters::get($user->id), $w->course->id);
        $DB->set_field(self::T . 'member', 'step', 1, ['id' => $member->id]);
        $r = $this->play($w, $user, 'C1-1')['rewards'];
        $this->assertTrue($r['hatched']);
        $this->assertSame(['claro'], $r['lanterns']);
        $this->assertSame(['ser-vivo'], $r['pages']);
    }

    public function test_a_retry_ending_gives_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        $events = [['n' => 'elegir', 'pick' => ['seta', 'cristal', 'humo', 'pompa', 'piedra']]];
        $result = scenes::play($w->course->id, $w->users[0]->id, $scene->id, $events, null);
        $this->assertSame('retry', $result['outcome']);
        $this->assertSame('elegir', $result['retry']);
        $this->assertSame([], $result['rewards']);
        $this->assertEquals(0, characters::get($w->users[0]->id)->saber);
    }

    public function test_chapter_reward_opens_an_option(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        // The garden has two scenes: the lantern of the chapter and its reward come with the second.
        $first = $this->play($w, $user, 'C4-2');
        $this->assertSame('', $first['rewards']['chapter']);
        $second = $this->play($w, $user, 'C4-3');
        $this->assertSame('jardin', $second['rewards']['chapter']);
        $this->assertContains('lantern', $second['rewards']['items']);
        // With the lantern, the night by the lake has one more option, and it gives a gem.
        $night = $this->play($w, $user, 'C5-2', ['lantern']);
        $this->assertSame(1, $night['rewards']['gems']);
    }

    public function test_the_creature_grows_once(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $this->play($w, $user, 'C1-2');
        $result = $this->play($w, $user, 'C6-2');
        $this->assertTrue($result['rewards']['grew']);
        $this->assertContains('naturalistlens', $result['rewards']['items']);
        $this->play($w, $user, 'C6-2');
        $this->assertEquals(2, characters::get($user->id)->creaturestage);
    }

    public function test_class_lantern_needs_half_of_the_class(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(3);
        $this->assertSame('', $this->play($w, $w->users[0], 'C1-2')['rewards']['classlantern']);
        $this->assertSame('claro', $this->play($w, $w->users[1], 'C1-2')['rewards']['classlantern']);
        $this->assertNotEquals(0, $DB->get_field(self::T . 'chapter', 'litat', ['missionid' => $w->mission->id,
            'code' => 'claro']));
        $this->assertSame('', $this->play($w, $w->users[2], 'C1-2')['rewards']['classlantern']);
    }

    public function test_paused_students_do_not_play(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $member = engine::member(characters::get($w->users[0]->id), $w->course->id);
        engine::pause($member, 'A break', 2);
        $this->expectException(\moodle_exception::class);
        $this->play($w, $w->users[0], 'C1-2');
    }

    public function test_the_saber_of_the_mission_goes_under_no_cap(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        regions::save($w->course->id, ['weeklycap' => 10]);
        set_config('weeklycap', 10, 'local_oksigeniatesape');
        $first = $this->play($w, $w->users[0], 'C1-2');
        $this->assertSame(20, $first['rewards']['saber']);
        $this->assertSame(0, $first['rewards']['capped']);
        $this->assertSame(0, $this->play($w, $w->users[0], 'C1-2')['rewards']['saber']);
    }

    public function test_results_for_the_teacher(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(2);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        // One student picks the crystal and the smoke first.
        scenes::play(
            $w->course->id,
            $w->users[0]->id,
            $scene->id,
            [['n' => 'elegir', 'pick' => ['seta', 'cristal', 'humo', 'pompa', 'piedra']]],
            null
        );
        $this->play($w, $w->users[0], 'C1-2');
        $this->play($w, $w->users[1], 'C1-2');
        $results = results::mission($w->course->id);
        $this->assertSame(2, $results['players']);
        $claro = $results['chapters'][0];
        $this->assertSame('claro', $claro['code']);
        $this->assertSame(2, $claro['done']);
        $nest = array_values(array_filter($claro['scenes'], fn($sc) => $sc['code'] === 'C1-2'))[0];
        $this->assertSame(2, $nest['players']);
        $this->assertSame(2, $nest['done']);
        $this->assertSame(3, $nest['attempts']);
        $this->assertSame('SV-01', $nest['objectives'][0]['code']);
        $this->assertSame(11, $nest['objectives'][0]['ok']);
        $this->assertSame(15, $nest['objectives'][0]['total']);
        $this->assertCount(results::CONFUSIONS, $nest['confusions']);
        $this->assertSame(1, $nest['confusions'][0]['count']);
        $this->assertSame('¿Qué cinco cosas lleváis al nido?', $nest['confusions'][0]['question']);
    }
}
