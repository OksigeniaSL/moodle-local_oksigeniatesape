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
use local_oksigeniatesape\external\direct;

/**
 * Tests for the teacher as the director of the game.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\director
 */
#[\PHPUnit\Framework\Attributes\CoversClass(director::class)]
final class director_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_a_mark_of_the_story_for_a_team_with_what_they_did(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $teams = [$gen->create_group(['courseid' => $course->id]), $gen->create_group(['courseid' => $course->id])];
        $users = [];
        foreach (['scout', 'sage', 'healer'] as $i => $role) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $teams[$i ? 1 : 0]->id, 'userid' => $user->id]);
            engine::member(characters::create($user->id, $role, $i % 2 ? 'f' : 'm'), $course->id);
            $users[] = $user;
        }
        // The marks of the mission, in its story.
        $marks = array_column(director::marks($course->id), 'text', 'flag');
        $this->assertSame('La garza es amiga de la clase', $marks['garza-amiga']);
        // Given to the second team, with what it did: each one of it has the mark and a gem that says why.
        $this->setUser($teacher);
        $note = 'Han comprobado en el libro que la garza come ranas';
        $given = external_api::clean_returnvalue(
            direct::execute_returns(),
            direct::execute($course->id, $teams[1]->id, 'garza-amiga', $note)
        );
        $this->assertSame(2, $given['given']);
        $mission = missions::get($course->id);
        foreach ($users as $i => $user) {
            $char = characters::get($user->id);
            $has = $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'flag',
                'code' => 'garza-amiga']);
            $this->assertSame($i > 0, $has);
            $this->assertSame($i > 0 ? director::GEMS : 0, (int) $char->gems);
        }
        $this->assertSame($note, $DB->get_field(self::T . 'ledger', 'note', ['charid' => characters::get($users[1]->id)->id,
            'reason' => 'manual']));
        // The place remembers it, for those who have it.
        $member = engine::member(characters::get($users[1]->id), $course->id);
        $lago = array_column(view::mission($course->id, characters::get($users[1]->id), $member)['chapters'], 'memory', 'code');
        $this->assertContains('La garza nos llama amigos: nos lo ganamos entre todos.', $lago['lago']);
        // Only the marks of the mission, and always with what they did.
        try {
            director::give($course->id, 0, 'cualquier-cosa', $note, (int) $teacher->id);
            $this->fail('Not a mark of the mission');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('Not a mark', $e->getMessage());
        }
        try {
            director::give($course->id, 0, 'garza-amiga', '  ', (int) $teacher->id);
            $this->fail('Without what they did');
        } catch (\moodle_exception $e) {
            $this->assertSame('directornote', $e->errorcode);
        }
        // To the whole class: the one who had it keeps it once, and everybody gets the gem.
        $this->assertSame(3, director::give($course->id, 0, 'garza-amiga', 'La clase entera ha cuidado la orilla', (int) $teacher->id));
        $this->assertSame(3, $DB->count_records(self::T . 'found', ['missionid' => $mission->id, 'kind' => 'flag',
            'code' => 'garza-amiga']));
    }
}
