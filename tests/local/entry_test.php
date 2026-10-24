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
 * Tests for the first look: the step it suggests, kept once, and put by the teacher.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\entry
 */
#[\PHPUnit\Framework\Attributes\CoversClass(entry::class)]
final class entry_test extends \advanced_testcase {
    public function test_the_first_look_suggests_the_step(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $mission = missions::get($c);
        $e = $mission->data['firstlook'];
        $right = array_map(fn($q) => $q['answer'], $e['questions']);
        $notyet = array_fill(0, count($right), get_string('app_entry_notyet', 'local_oksigeniatesape'));
        // All right, expert; all but one, further; most, core; half or less, first steps.
        $this->assertSame(4, entry::suggest($e, $right, 'valley', 'es'));
        $this->assertSame(3, entry::suggest($e, array_replace($right, [5 => 'Los hongos']), 'valley', 'es'));
        $this->assertSame(2, entry::suggest($e, array_replace($right, [4 => 'x', 5 => 'x']), 'valley', 'es'));
        $this->assertSame(1, entry::suggest($e, array_replace($right, [3 => 'x', 4 => 'x', 5 => 'x']), 'valley', 'es'));
        $this->assertSame(1, entry::suggest($e, $notyet, 'valley', 'es'));
        // A student sees it, with «Not yet» last in each question, until they answer it.
        $users = [];
        foreach (['ana' => $right, 'leo' => $notyet] as $name => $answers) {
            $users[$name] = $gen->create_and_enrol($course, 'student');
            $member = engine::member(characters::create($users[$name]->id, 'sage', 'f'), $c);
            $look = entry::for_student($c, $member);
            $this->assertCount(6, $look['questions']);
            $this->assertSame(end($notyet), end($look['questions'][0]['options']));
            $this->assertTrue(entry::answer($c, (int) $users[$name]->id, $answers));
            $this->assertNull(entry::for_student($c, engine::member(characters::get($users[$name]->id), $c)));
        }
        // Who has played a scene already does not see it.
        $other = $gen->create_and_enrol($course, 'student');
        $member = engine::member(characters::create($other->id, 'scout', 'm'), $c);
        $DB->insert_record('local_oksigeniatesape_sceneattempt', (object) ['sceneid' => 1, 'charid' => $member->charid,
            'courseid' => $c, 'teamplay' => 0, 'path' => '{}', 'score' => 0, 'objectives' => '{}', 'ending' => 'x',
            'attempt' => 1, 'timestarted' => time(), 'timefinished' => time()]);
        $this->assertNull(entry::for_student($c, $member));
        // Once: answering again changes nothing.
        $this->assertFalse(entry::answer($c, (int) $users['leo']->id, $right));
        $step = fn($user) => (int) engine::member(characters::get($user->id), $c)->step;
        $this->assertSame([4, 1], array_map(
            fn($u) => (int) engine::member(characters::get($u->id), $c)->entrystep,
            array_values($users)
        ));
        // One answer for each question.
        try {
            entry::answer($c, (int) $gen->create_and_enrol($course, 'student')->id, ['Una seta']);
            $this->fail('Fewer answers');
        } catch (\moodle_exception $e) {
            $this->assertInstanceOf(\moodle_exception::class, $e);
        }
        // The teacher puts the suggested steps: both change; again, none.
        $this->assertSame(2, entry::apply($c));
        $this->assertSame([4, 1], [$step($users['ana']), $step($users['leo'])]);
        $this->assertSame(0, entry::apply($c));
        $this->assertTrue($DB->record_exists('local_oksigeniatesape_member', ['courseid' => $c, 'step' => 4]));
    }
}
