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
 * Tests for the question bank of Moodle as a source of the battles.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\moodlebank
 */
#[\PHPUnit\Framework\Attributes\CoversClass(moodlebank::class)]
final class moodlebank_test extends \advanced_testcase {
    public function test_half_of_a_battle_comes_from_the_category_the_teacher_chose(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        // The bank of the course: the course itself up to Moodle 4.5, a question bank from Moodle 5.0.
        if (\core_component::get_component_directory('mod_qbank')) {
            $bank = $gen->create_module('qbank', ['course' => $c]);
            $context = \context_module::instance($bank->cmid);
        } else {
            $context = \context_course::instance($c);
        }
        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category(['contextid' => $context->id, 'name' => 'Seres vivos']);
        $one = $qgen->create_question('multichoice', 'one_of_four', ['category' => $cat->id,
            'questiontext' => ['text' => '<p>¿Cuál es un <b>ser vivo</b>?</p>', 'format' => FORMAT_HTML]]);
        $tf = $qgen->create_question('truefalse', 'true', ['category' => $cat->id]);
        // Not shown as they are: several right answers, or to write.
        $qgen->create_question('multichoice', 'two_of_four', ['category' => $cat->id]);
        $qgen->create_question('shortanswer', null, ['category' => $cat->id]);
        $this->setUser($teacher);
        $list = moodlebank::categories($c);
        $mine = array_values(array_filter($list, fn($x) => $x['id'] === (int) $cat->id));
        $this->assertCount(1, $mine);
        $this->assertSame(2, $mine[0]['count']);
        $this->assertStringContainsString('Seres vivos', $mine[0]['name']);
        $this->assertTrue(moodlebank::allowed($c, (int) $cat->id));
        // Another course's category is not this one's.
        $other = $gen->create_course();
        $this->assertFalse(moodlebank::allowed((int) $other->id, (int) $cat->id));
        $this->assertSame([], moodlebank::usable($c));
        // Chosen: the questions come in the game as text, with the right option where the seed puts it.
        regions::save($c, ['rules' => ['qbank' => (int) $cat->id]]);
        $ids = moodlebank::usable($c);
        sort($ids);
        $this->assertSame(['mb' . $one->id, 'mb' . $tf->id], $ids);
        $q = questions::get($c, 'mb' . $one->id, 7);
        $this->assertSame('¿Cuál es un ser vivo?', $q['text']);
        $this->assertCount(4, $q['options']);
        $this->assertSame('One', $q['options'][$q['correct']]);
        $this->assertSame($q, questions::get($c, 'mb' . $one->id, 7));
        $this->assertSame('', $q['chapter']);
        $t = questions::get($c, 'mb' . $tf->id, 3);
        $this->assertCount(2, $t['options']);
        $this->assertNull(questions::get((int) $other->id, 'mb' . $one->id, 7));
        // A battle of four: two from the bank, two from the mission.
        $picked = questions::pick_many($c, 4);
        $this->assertCount(4, $picked);
        $this->assertCount(2, array_filter($picked, fn($id) => str_starts_with($id, 'mb')));
        $this->assertCount(4, array_unique($picked));
        // The rule of another course pointing here (a course restored elsewhere) brings nothing.
        regions::save((int) $other->id, ['enabled' => 1, 'rules' => ['qbank' => (int) $cat->id]]);
        $this->assertSame([], moodlebank::usable((int) $other->id));
        // Without a category, only the mission.
        regions::save($c, ['rules' => ['qbank' => 0]]);
        $this->assertCount(0, array_filter(questions::pick_many($c, 4), fn($id) => str_starts_with($id, 'mb')));
    }
}
