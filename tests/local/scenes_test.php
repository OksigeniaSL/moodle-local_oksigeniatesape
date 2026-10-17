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
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($user->id), $w->course->id);
        return scenes::play($w->course->id, $user->id, $scene->id, autoplayer::play(
            json_decode($scene->definition, true),
            array_merge($carried, $items),
            $roles,
            $flags,
            $role
        ), null);
    }

    public function test_install(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(0);
        $this->assertSame('py', $w->mission->variant);
        $this->assertEquals(6, $DB->count_records(self::T . 'chapter', ['missionid' => $w->mission->id]));
        $this->assertEquals(27, $DB->count_records(self::T . 'scene', ['missionid' => $w->mission->id]));
        $this->assertTrue($DB->record_exists(self::T . 'item', ['code' => 'naturalistlens']));
        // A classroom challenge per chapter, from the activity off the screen.
        $challenges = classroom::list($w->course->id);
        $this->assertCount(6, $challenges);
        $this->assertEquals(missions::CHAPTER_CHALLENGE_SABER, reset($challenges)->saber);
        // Installing again changes the skin and keeps everything else.
        missions::install($w->course->id, 'livingthings', 'azara', 'es');
        $this->assertEquals(1, $DB->count_records(self::T . 'mission', ['courseid' => $w->course->id]));
        $this->assertEquals(27, $DB->count_records(self::T . 'scene', ['missionid' => $w->mission->id]));
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
        // And the clearing done, the magnifier that some options of the next places ask for.
        $this->assertContains('magnifier', $r['items']);
    }

    public function test_whoever_plays_the_core_has_done_the_chapter(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $member = engine::member(characters::get($user->id), $w->course->id);
        $DB->set_field(self::T . 'member', 'step', 1, ['id' => $member->id]);
        // First steps, but the core of the forest played (with the class on the class screen): the forest is done.
        $this->assertSame([], $this->play($w, $user, 'C3-2')['rewards']['lanterns']);
        $this->assertSame(['bosque'], $this->play($w, $user, 'C3-3')['rewards']['lanterns']);
    }

    public function test_a_retry_ending_gives_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        $events = [['n' => 'antes', 'pick' => ['buscar']], ['n' => 'elegir', 'pick' => ['seta', 'cristal', 'humo', 'pompa', 'piedra']]];
        $result = scenes::play($w->course->id, $w->users[0]->id, $scene->id, $events, null);
        $this->assertSame('retry', $result['outcome']);
        $this->assertSame('elegir', $result['retry']);
        // Nothing won: only the hints opened, if any, are paid.
        $this->assertSame(['hints' => 0, 'shielded' => false], $result['rewards']);
        $this->assertEquals(0, characters::get($w->users[0]->id)->saber);
    }

    public function test_only_the_scenes_of_the_mission_in_course_are_played(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $old = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-1']);
        // The class goes on to another expedition: the scenes of the past one are only read.
        missions::install($w->course->id, 'livingthings', 'valley', 'py');
        $this->assertNotEmpty(scenes::get($w->course->id, $old->id)->data);
        $this->expectException(\moodle_exception::class);
        scenes::play($w->course->id, $w->users[0]->id, $old->id, [], null);
    }

    public function test_a_play_starts_at_a_retry_node_only_after_that_retry(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-4']);
        $retry = array_values(array_filter(array_column(json_decode($scene->definition, true)['nodes'], 'retry')))[0];
        $this->assertNotSame(json_decode($scene->definition, true)['start'], $retry);
        $this->expectException(\moodle_exception::class);
        scenes::play($w->course->id, $w->users[0]->id, $scene->id, [], $retry);
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

    public function test_quests_are_for_everybody_and_give_no_saber(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $quests = $DB->get_records(self::T . 'scene', ['missionid' => $w->mission->id, 'quest' => 1], 'code');
        $this->assertCount(12, $quests);
        // Nobody's route: a student of the extension step plays the core scenes, and the quests apart.
        $member = engine::member(characters::get($user->id), $w->course->id);
        $DB->set_field(self::T . 'member', 'step', 3, ['id' => $member->id]);
        $claro = $DB->get_field(self::T . 'chapter', 'id', ['missionid' => $w->mission->id, 'code' => 'claro']);
        $scenes = array_values($DB->get_records(self::T . 'scene', ['chapterid' => $claro], 'code'));
        $this->assertSame(['C1-2'], array_map(fn($sc) => $sc->code, scenes::for_step($scenes, 3)));
        $map = view::world($w->course->id, $user->id)['mission']['chapters'][0];
        $this->assertSame(['C1-2'], array_column($map['scenes'], 'code'));
        $this->assertCount(2, $map['quests']);
        // Played well: gems, maybe a page, never saber, lanterns, items or the creature.
        foreach ($quests as $quest) {
            $result = $this->play($w, $user, $quest->code);
            $this->assertContains($result['outcome'], ['success', 'partial'], $quest->code);
            $this->assertSame(0, $result['rewards']['saber'], $quest->code);
            $this->assertGreaterThan(0, $result['rewards']['gems'], $quest->code);
            $this->assertSame([], $result['rewards']['lanterns'], $quest->code);
            $this->assertSame([], $result['rewards']['items'], $quest->code);
            $this->assertFalse($result['rewards']['hatched'], $quest->code);
            $this->assertSame('', $result['rewards']['chapter'], $quest->code);
        }
        $this->assertEquals(0, characters::get($user->id)->saber);
    }

    public function test_the_rehearsal_gives_only_saber(): void {
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $this->play($w, $user, 'C1-2');
        // What the end of the mission gives comes with the final battle: the rehearsal only trains.
        $result = $this->play($w, $user, 'C6-2');
        $this->assertEquals(10, $result['rewards']['saber']);
        $this->assertSame([], $result['rewards']['items']);
        // It closes the chapter of the tower, but its lantern is lit by the battle.
        $this->assertSame('torre', $result['rewards']['chapter']);
        $this->assertSame([], $result['rewards']['lanterns']);
        $this->assertFalse($result['rewards']['grew']);
        $this->assertEquals(1, characters::get($user->id)->creaturestage);
    }

    public function test_the_creature_grows_once(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $this->play($w, $user, 'C1-2');
        // A scene that makes the creature grow (in the pilot mission, the final battle does).
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C6-2']);
        $def = json_decode($scene->definition, true);
        $def['nodes']['fin']['effects'][] = ['creature' => 'grow'];
        $DB->set_field(self::T . 'scene', 'definition', json_encode($def), ['id' => $scene->id]);
        $this->assertTrue($this->play($w, $user, 'C6-2')['rewards']['grew']);
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

    public function test_hints_cost_breath_and_the_sage_pays_less(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(2);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C2-2']);
        $def = json_decode($scene->definition, true);
        $def['nodes']['colocar']['hints'] = ['Una', 'Dos', 'Tres'];
        $DB->set_field(self::T . 'scene', 'definition', json_encode($def), ['id' => $scene->id]);
        $hints = [['n' => 'colocar', 'hint' => 0], ['n' => 'colocar', 'hint' => 1], ['n' => 'colocar', 'hint' => 2]];
        // Each one plays what they can see: the sage has one more card to place.
        $events = function (\stdClass $user) use ($w, $def, $hints): array {
            [$items, $roles, $flags, $role] = scenes::context(characters::get($user->id), $w->course->id);
            return array_merge($hints, autoplayer::play($def, $items, $roles, $flags, $role));
        };
        // The sage: the first free, half for the rest.
        $sage = scenes::play($w->course->id, $w->users[0]->id, $scene->id, $events($w->users[0]), null);
        $this->assertSame(1, $sage['rewards']['hints']);
        $this->assertEquals(engine::BREATH_MAX - 1, engine::member(characters::get($w->users[0]->id), $w->course->id)->breath);
        // Anyone else: one each.
        $DB->set_field(self::T . 'char', 'role', 'scout', ['userid' => $w->users[1]->id]);
        $scout = scenes::play($w->course->id, $w->users[1]->id, $scene->id, $events($w->users[1]), null);
        $this->assertSame(3, $scout['rewards']['hints']);
    }

    public function test_a_play_sent_again_counts_once(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $user = $w->users[0];
        $DB->set_field(self::T . 'char', 'role', 'scout', ['userid' => $user->id]);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C2-2']);
        $def = json_decode($scene->definition, true);
        $def['nodes']['colocar']['hints'] = ['Una', 'Dos'];
        $DB->set_field(self::T . 'scene', 'definition', json_encode($def), ['id' => $scene->id]);
        [$items, $roles, $flags, $role] = scenes::context(characters::get($user->id), $w->course->id);
        $hints = [['n' => 'colocar', 'hint' => 0], ['n' => 'colocar', 'hint' => 1]];
        $events = array_merge($hints, autoplayer::play($def, $items, $roles, $flags, $role));
        $first = scenes::play($w->course->id, $user->id, $scene->id, $events, null, null, [], 'abc123');
        $this->assertSame(2, $first['rewards']['hints']);
        // The answer was lost on the way and the device sends it again: the same ending, nothing paid or given again.
        $again = scenes::play($w->course->id, $user->id, $scene->id, $events, null, null, [], 'abc123');
        $this->assertSame($first['ending'], $again['ending']);
        $this->assertSame($first['outcome'], $again['outcome']);
        $this->assertSame(1, $again['attempt']);
        $this->assertSame([], $again['rewards']);
        $this->assertEquals(engine::BREATH_MAX - 2, engine::member(characters::get($user->id), $w->course->id)->breath);
        $this->assertEquals(1, $DB->count_records(self::T . 'sceneattempt', ['sceneid' => $scene->id]));
        // Played again on purpose, it is another play.
        $other = scenes::play($w->course->id, $user->id, $scene->id, $events, null, null, [], 'def456');
        $this->assertSame(2, $other['attempt']);
    }

    public function test_the_healer_looks_after_the_teammate_with_least_breath(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(3);
        $group = $this->getDataGenerator()->create_group(['courseid' => $w->course->id]);
        foreach ($w->users as $user) {
            $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
        }
        $DB->set_field(self::T . 'char', 'role', 'healer', ['userid' => $w->users[0]->id]);
        $low = engine::member(characters::get($w->users[2]->id), $w->course->id);
        $DB->set_field(self::T . 'member', 'breath', 1, ['id' => $low->id]);
        $result = $this->play($w, $w->users[0], 'C1-2');
        $this->assertSame($w->users[2]->firstname, $result['rewards']['cared']);
        $this->assertEquals(2, engine::member(characters::get($w->users[2]->id), $w->course->id)->breath);
    }

    public function test_a_scene_played_as_a_team_counts_for_the_teammates_who_were_there(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(4);
        $dg = $this->getDataGenerator();
        $team = $dg->create_group(['courseid' => $w->course->id]);
        $other = $dg->create_group(['courseid' => $w->course->id]);
        foreach ([0, 1, 2] as $i) {
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $w->users[$i]->id]);
        }
        $dg->create_group_member(['groupid' => $other->id, 'userid' => $w->users[3]->id]);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($w->users[0]->id), $w->course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        // Marked: a teammate who is there, and a student of another team, who does not count.
        $marked = [$w->users[1]->id, $w->users[3]->id];
        $result = scenes::play($w->course->id, $w->users[0]->id, $scene->id, $events, null, null, $marked);
        $this->assertSame('success', $result['outcome']);
        $this->assertSame([$w->users[1]->firstname], $result['teamwith']);
        // The same game in the play of each one.
        $game = (int) $DB->get_field(self::T . 'sceneattempt', 'teamplay', ['charid' => characters::get($w->users[0]->id)->id]);
        $this->assertGreaterThan(0, $game);
        foreach ([0 => 1, 1 => 1, 2 => 0, 3 => 0] as $i => $plays) {
            $char = characters::get($w->users[$i]->id);
            $where = ['charid' => $char->id, 'teamplay' => $game];
            $this->assertEquals($plays, $DB->count_records(self::T . 'sceneattempt', $where));
            $this->assertEquals($plays ? 20 : 0, $char->saber);
        }
        // Alone, as always.
        $alone = $this->play($w, $w->users[2], 'C1-2');
        $this->assertSame([], $alone['teamwith']);
        $where = ['charid' => characters::get($w->users[2]->id)->id, 'teamplay' => 0];
        $this->assertEquals(1, $DB->count_records(self::T . 'sceneattempt', $where));
    }

    public function test_flags_are_kept_for_other_scenes(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class(1);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        $def = json_decode($scene->definition, true);
        $def['nodes']['despierta']['effects'][] = ['flag' => 'nido'];
        $DB->set_field(self::T . 'scene', 'definition', json_encode($def), ['id' => $scene->id]);
        $this->play($w, $w->users[0], 'C1-2');
        [, , $flags, $role] = scenes::context(characters::get($w->users[0]->id), $w->course->id);
        $this->assertSame(['nido'], $flags);
        $this->assertSame('sage', $role);
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
            [['n' => 'antes', 'pick' => ['buscar']], ['n' => 'elegir', 'pick' => ['seta', 'cristal', 'humo', 'pompa', 'piedra']]],
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
        $this->assertSame('¿Qué cinco cosas llevamos al nido?', $nest['confusions'][0]['question']);
    }
}
