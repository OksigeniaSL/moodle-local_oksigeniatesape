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
 * Tests for the sessions of a class, run by the teacher as a director.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\sessions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(sessions::class)]
final class sessions_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A student plays the core scene of the clearing, which gives its lantern.
     *
     * @param int $courseid
     * @param int $userid
     */
    private function play_the_nest(int $courseid, int $userid): void {
        global $DB;
        $mission = missions::get($courseid);
        $scene = $DB->get_record('local_oksigeniatesape_scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        [$items, $roles, $flags, $role] = scenes::context(characters::get($userid), $courseid);
        $events = autoplayer::play(json_decode($scene->definition, true), $items, $roles, $flags, $role);
        scenes::play($courseid, $userid, (int) $scene->id, $events, null);
    }

    /**
     * A class with the pilot mission, a teacher and a team of two.
     *
     * @return array [course, teacher]
     */
    private function make_class(): array {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $team = $gen->create_group(['courseid' => $course->id, 'name' => 'Los Linces']);
        foreach (['healer', 'sage'] as $role) {
            $user = $gen->create_and_enrol($course, 'student');
            characters::create($user->id, $role, 'f');
            $gen->create_group_member(['groupid' => $team->id, 'userid' => $user->id]);
        }
        return [$course, $teacher];
    }

    public function test_the_sessions_of_the_mission_come_in_order_from_the_entry(): void {
        [$course] = $this->make_class();
        $mission = missions::get($course->id);
        $all = sessions::all($mission);
        $this->assertCount(14, $all);
        $this->assertSame([sessions::ENTRY, 1], [$all[0]['chapter'], $all[0]['number']]);
        $this->assertSame(['claro', 2], [$all[2]['chapter'], $all[2]['number']]);
        $this->assertTrue(!empty(end($all)['sheet']['optional']));
        $this->assertSame(['chapter' => sessions::ENTRY, 'number' => 1], sessions::next($course->id, $mission));
        // The sheet of the second session of the clearing: its steps with what they launch, its questions and mistakes.
        $sheet = sessions::view($course->id, $mission, 'claro', 2);
        $this->assertSame('Claro del Despertar', $sheet['place']);
        $this->assertSame(['card', 'scene', 'timer', 'challenge'], array_column($sheet['steps'], 'action'));
        $this->assertNotSame(0, $sheet['steps'][1]['sceneid']);
        $this->assertSame(20, $sheet['steps'][2]['timer']);
        $this->assertCount(3, $sheet['questions']);
        $this->assertCount(2, $sheet['watch']);
        $this->assertNull(sessions::view($course->id, $mission, 'claro', 9));
    }

    public function test_during_a_session_the_lantern_waits_ready_for_its_rite(): void {
        global $DB;
        [$course] = $this->make_class();
        $mission = missions::get($course->id);
        $students = array_values(get_enrolled_users(\context_course::instance($course->id), 'local/oksigeniatesape:play'));
        $now = time();
        sessions::start($course->id, 'claro', 1, $now);
        // Half of the class has the lantern of the clearing: it waits ready, not lit.
        $this->play_the_nest($course->id, (int) $students[0]->id);
        $clearing = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => 'claro']);
        $this->assertSame(0, (int) $clearing->litat);
        $this->assertGreaterThan(0, (int) $clearing->readyat);
        $this->assertSame(['Claro del Despertar'], sessions::teacher($course->id)['open']['ready']);
        // The teacher lights it with the class: the class screen shows its rite.
        sessions::light($course->id, $now + 60);
        $this->assertSame($now + 60, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $clearing->id]));
        $board = sessions::board($course->id, $now + 61);
        $this->assertSame(['light', 'Claro del Despertar'], [$board['shown'], $board['lit']]);
        $this->assertSame([], sessions::ready((int) $mission->id));
        // Ready and not lit by the teacher: closing the session lights it.
        $DB->set_field(self::T . 'chapter', 'litat', 0, ['id' => $clearing->id]);
        $DB->set_field(self::T . 'chapter', 'readyat', $now, ['id' => $clearing->id]);
        sessions::close($course->id, [], $now + 3000);
        $this->assertSame($now + 3000, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $clearing->id]));
        // And left ready with a session open, it lights by itself after a day.
        sessions::start($course->id, 'claro', 2, $now + 4000);
        $DB->set_field(self::T . 'chapter', 'litat', 0, ['id' => $clearing->id]);
        $DB->set_field(self::T . 'chapter', 'readyat', $now + 4000, ['id' => $clearing->id]);
        sessions::teacher($course->id, $now + 5000);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $clearing->id]));
        sessions::teacher($course->id, $now + 4000 + sessions::READY_WAIT);
        $this->assertGreaterThan(0, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $clearing->id]));
        // Without a session, the lantern lights at once, as always.
        sessions::close($course->id, [], $now + 90000);
        $DB->set_field(self::T . 'chapter', 'litat', 0, ['id' => $clearing->id]);
        $DB->delete_records(self::T . 'found', ['kind' => 'lantern']);
        $this->play_the_nest($course->id, (int) $students[1]->id);
        $this->assertGreaterThan(0, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $clearing->id]));
    }

    public function test_the_teacher_starts_goes_step_by_step_shows_and_closes_with_what_to_review(): void {
        [$course] = $this->make_class();
        $mission = missions::get($course->id);
        $now = time();
        sessions::start($course->id, 'claro', 1, $now);
        $view = sessions::teacher($course->id, $now);
        $this->assertSame(['claro', 1, 0], [$view['open']['chapter'], $view['open']['number'], $view['open']['step']]);
        $this->assertNull($view['next']);
        sessions::step($course->id, 2);
        // The place entered, on the class screen: its name, with its intro.
        sessions::show($course->id, 'enter', 0, 0, $now + 60);
        $board = sessions::board($course->id, $now + 61);
        $this->assertSame(['enter', 'Claro del Despertar'], [$board['shown'], $board['place']]);
        $this->assertNotSame('', $board['intro']);
        // A timer of twenty minutes, and the commitments of the place with how many have chosen, by team.
        sessions::show($course->id, 'timer', 20, 0, $now + 120);
        $this->assertSame(20, sessions::board($course->id, $now + 121)['minutes']);
        sessions::show($course->id, 'pacts', 0, 0, $now + 180);
        $pacts = sessions::board($course->id, $now + 181)['pacts'];
        $this->assertNotEmpty($pacts['options']);
        $this->assertSame('Claro del Despertar', $pacts['place']);
        $this->assertSame([['name' => 'Los Linces', 'chose' => 0, 'total' => 2]], $pacts['teams']);
        try {
            sessions::show($course->id, 'fireworks', 0, 0, $now);
            $this->fail('Only what the class screen knows');
        } catch (\moodle_exception $e) {
            $this->assertSame('nosession', $e->errorcode);
        }
        // Closed, with what to review: the next one is the second of the clearing, and starts with it.
        sessions::close($course->id, ['La semilla seca, que «no se mueve»', '  ', 'El fuego que crece'], $now + 3000);
        $this->assertNull(sessions::board($course->id, $now + 3001));
        $view = sessions::teacher($course->id, $now + 3001);
        $this->assertNull($view['open']);
        $this->assertSame(['claro', 2], [$view['next']['chapter'], $view['next']['number']]);
        $this->assertSame(['La semilla seca, que «no se mueve»', 'El fuego que crece'], $view['next']['review']);
        $done = array_filter($view['list'], fn($s) => $s['done']);
        $this->assertSame([['claro', 1]], array_values(array_map(fn($s) => [$s['chapter'], $s['number']], $done)));
        // Starting another one closes the one going on.
        sessions::start($course->id, 'claro', 2, $now + 4000);
        sessions::start($course->id, 'biblioteca', 1, $now + 4100);
        $this->assertSame('biblioteca', sessions::open($course->id)->chapter);
    }
}
