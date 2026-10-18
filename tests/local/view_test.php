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
 * Tests for what the world of a student shows of the map: the things of each place on its painting, and where each one
 * of their team is.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\view
 */
#[\PHPUnit\Framework\Attributes\CoversClass(view::class)]
final class view_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_the_map_shows_the_spots_of_each_place_and_where_the_team_is(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $team = $dg->create_group(['courseid' => $course->id]);
        $users = [];
        foreach ([0, 1, 2] as $i) {
            $users[$i] = $dg->create_and_enrol($course, 'student');
            characters::create($users[$i]->id, 'scout', 'm');
            engine::member(characters::get($users[$i]->id), $course->id);
            $dg->create_group_member(['groupid' => $team->id, 'userid' => $users[$i]->id]);
        }
        // The first one plays the nest of the clearing; the second one has not played yet.
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        [$carried, $roles, $flags, $role] = scenes::context(characters::get($users[0]->id), $course->id);
        $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
        scenes::play($course->id, $users[0]->id, $scene->id, $events, null);
        $world = view::world($course->id, $users[2]->id);
        $at = array_column($world['team']['members'], 'at', 'userid');
        $this->assertSame((int) $scene->chapterid, $at[$users[0]->id]);
        $this->assertSame(0, $at[$users[1]->id]);
        // The nest is in the twigs at the bottom of the painting of the clearing.
        $clearing = $world['mission']['chapters'][0];
        $spots = array_column($clearing['spots'], null, 'target');
        $this->assertSame(['target' => 'C1-2', 'x' => 50, 'y' => 76], $spots['C1-2']);
        // The Embrollo of each place, for when the student lights its lantern: the first knot of the place and its letter.
        $this->assertSame('la Piedra que rueda', $clearing['embrollo']['name']);
        $this->assertSame('Las tres funciones vitales', $clearing['embrollo']['weakness']);
        $this->assertNotSame('', $clearing['embrollo']['letter']);
        $this->assertSame('la Nube de arena', $world['mission']['chapters'][1]['embrollo']['name']);
    }

    public function test_the_creature_is_happy_after_three_good_endings_and_asleep_after_a_week_away(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $dg->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        $DB->set_field(self::T . 'char', 'creature', json_encode(scenes::creature($char)), ['id' => $char->id]);
        $DB->set_field(self::T . 'char', 'creaturestage', 1, ['id' => $char->id]);
        engine::member(characters::get($user->id), $course->id);
        $this->assertSame('', view::world($course->id, $user->id)['character']['member']['mood']);
        foreach (['C1-2', 'C2-2', 'C3-3'] as $code) {
            $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => $code]);
            [$carried, $roles, $flags, $role] = scenes::context(characters::get($user->id), $course->id);
            $events = autoplayer::play(json_decode($scene->definition, true), $carried, $roles, $flags, $role);
            $this->assertSame('success', scenes::play($course->id, $user->id, $scene->id, $events, null)['outcome'], $code);
        }
        $this->assertSame('happy', view::world($course->id, $user->id)['character']['member']['mood']);
        // A week away: asleep (it wakes on the device, with a touch); the visit is noted, and the next time it is awake.
        $DB->set_field(self::T . 'member', 'lastseen', time() - 8 * DAYSECS, ['charid' => $char->id]);
        $this->assertSame('asleep', view::world($course->id, $user->id)['character']['member']['mood']);
        $this->assertSame('happy', view::world($course->id, $user->id)['character']['member']['mood']);
    }

    public function test_the_decisions_of_the_class_are_stickers_on_the_map_of_the_board(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $chars = [];
        foreach ([0, 1, 2] as $i) {
            $user = $dg->create_and_enrol($course, 'student');
            characters::create($user->id, 'healer', 'f');
            $chars[$i] = characters::get($user->id);
            engine::member($chars[$i], $course->id);
        }
        $mark = fn(int $i, string $code) => $DB->insert_record(self::T . 'found', ['charid' => $chars[$i]->id,
            'courseid' => $course->id, 'missionid' => $mission->id, 'kind' => 'flag', 'code' => $code, 'timecreated' => time()]);
        $stickers = fn() => array_column(view::board($course->id)['mission']['chapters'], 'stickers', 'code');
        $this->assertSame([], $stickers()['claro']);
        // Two of three took the frog back to its pond; one let it stay: the pond, by the clearing.
        $mark(0, 'rana-en-la-charca');
        $mark(1, 'rana-en-la-charca');
        $mark(2, 'rana-en-el-nido');
        $this->assertSame([['flag' => 'rana-en-la-charca', 'text' => 'La rana, en su charca']], $stickers()['claro']);
        $this->assertSame([], $stickers()['lago']);
    }

    public function test_the_class_carries_the_marks_of_at_least_half_of_it(): void {
        global $DB;
        $this->resetAfterTest();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $chars = [];
        foreach ([0, 1, 2, 3] as $i) {
            $user = $dg->create_and_enrol($course, 'student');
            characters::create($user->id, 'healer', 'f');
            $chars[$i] = characters::get($user->id);
            engine::member($chars[$i], $course->id);
        }
        $mark = function (int $i, string $code) use ($DB, $chars, $course, $mission): void {
            $DB->insert_record(self::T . 'found', ['charid' => $chars[$i]->id, 'courseid' => $course->id,
                'missionid' => $mission->id, 'kind' => 'flag', 'code' => $code, 'timecreated' => time()]);
        };
        // Two of four put the seed in the nest; only one asked the owl well.
        $mark(0, 'semilla-en-el-nido');
        $mark(1, 'semilla-en-el-nido');
        $mark(2, 'pregunta-buena');
        $this->assertSame(['semilla-en-el-nido'], scenes::class_flags($course->id, (int) $mission->id));
        // The end of the mission tells it, in the voice of the Great Book.
        $lines = view::remembered($course->id, missions::get($course->id));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('semilla', $lines[0]);
        // Who is paused does not count among those who play.
        $DB->set_field(self::T . 'member', 'status', 'paused', ['charid' => $chars[3]->id]);
        $DB->set_field(self::T . 'member', 'status', 'paused', ['charid' => $chars[1]->id]);
        $marks = scenes::class_flags($course->id, (int) $mission->id);
        $this->assertEqualsCanonicalizing(['semilla-en-el-nido', 'pregunta-buena'], $marks);
    }

    public function test_who_has_no_character_gets_the_prologue_of_the_mission(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $world = view::world($course->id, $user->id);
        $this->assertCount(3, $world['opening']['beats']);
        $this->assertSame('druida-mayor', $world['opening']['beats'][2]['speaker']);
        $this->assertSame('La druida mayor', $world['opening']['beats'][2]['speakername']);
        $this->assertStringContainsString('¿tú qué haces?', $world['opening']['ask']);
        $this->assertNotSame('', $world['opening']['mirror']);
        // Once they have their character, no more.
        characters::create($user->id, 'sage', 'f');
        $this->assertArrayNotHasKey('opening', view::world($course->id, $user->id));
    }
}
