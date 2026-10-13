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
 * Tests for what a teacher changes in the mission of a course.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\edits
 */
#[\PHPUnit\Framework\Attributes\CoversClass(edits::class)]
final class edits_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission.
     *
     * @return \stdClass
     */
    private function make_course(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        return $course;
    }

    /**
     * A scene of the mission of a course, as the game reads it.
     *
     * @param int $courseid
     * @param string $code
     * @return \stdClass
     */
    private function scene(int $courseid, string $code): \stdClass {
        global $DB;
        $id = $DB->get_field(self::T . 'scene', 'id', ['missionid' => missions::get($courseid)->id, 'code' => $code]);
        return scenes::get($courseid, (int) $id);
    }

    public function test_every_text_of_the_mission_can_be_changed(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $view = edits::view($course->id);
        $this->assertCount(6, $view['chapters']);
        $this->assertCount(18, $view['questions']);
        $kinds = [];
        foreach ($view['chapters'] as $chapter) {
            $this->assertNotEmpty($chapter['fields']);
            foreach ($chapter['scenes'] as $scene) {
                foreach ($scene['fields'] as $field) {
                    $kinds[$field['kind']] = true;
                    $this->assertNotSame('', $field['text']);
                    $this->assertStringNotContainsString('{{local:', $field['text']);
                }
            }
        }
        foreach (['title', 'text', 'prompt', 'hints', 'label', 'feedback', 'stuck'] as $kind) {
            $this->assertArrayHasKey($kind, $kinds);
        }
    }

    public function test_a_change_is_played_and_goes_back_to_the_original(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $original = $this->scene($course->id, 'C1-2')->data;
        $this->assertTrue(edits::save($course->id, 'C1-2', 'title', 'Un título propio'));
        edits::save($course->id, 'C1-2', 'nodes/intro/text', 'Una narración propia');
        edits::save($course->id, 'C1-2', 'nodes/elegir/options/seta/label', 'una seta del parque');
        $data = $this->scene($course->id, 'C1-2')->data;
        $this->assertSame('Un título propio', $data['title']);
        $this->assertSame('Una narración propia', $data['nodes']['intro']['text']);
        $labels = array_column($data['nodes']['elegir']['options'], 'label', 'id');
        $this->assertSame('una seta del parque', $labels['seta']);
        // The rest, as it came; and an empty text is the original again.
        $this->assertEquals($original['nodes']['elegir']['prompt'], $data['nodes']['elegir']['prompt']);
        $this->assertFalse(edits::save($course->id, 'C1-2', 'title', ''));
        $this->assertEquals($original['title'], $this->scene($course->id, 'C1-2')->data['title']);
        edits::restore($course->id, 'C1-2');
        $this->assertEquals($original, $this->scene($course->id, 'C1-2')->data);
    }

    public function test_only_texts_can_be_changed(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $this->expectException(\invalid_parameter_exception::class);
        edits::save($course->id, 'C1-2', 'nodes/elegir/options/seta/correct', '1');
    }

    public function test_the_places_and_the_questions(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        edits::save($course->id, edits::MISSION, 'chapters/claro/sessions/0/do', 'Otra primera sesión');
        $this->assertSame('Otra primera sesión', missions::get($course->id)->data['chapters'][0]['sessions'][0]['do']);
        $questions = edits::view($course->id)['questions'];
        $questions[0]['text'] = '¿Una pregunta mía?';
        $questions[] = ['id' => '', 'chapter' => 'lago', 'objective' => 'SV-06', 'text' => '¿Una más?',
            'options' => ['Sí', '', 'No'], 'why' => ''];
        $kept = edits::save_questions($course->id, $questions);
        $this->assertCount(19, $kept);
        $this->assertSame(['Sí', 'No'], $kept[18]['options']);
        $this->assertNotSame('', $kept[18]['id']);
        $data = missions::get($course->id)->data;
        $this->assertSame('¿Una pregunta mía?', $data['questions'][0]['text']);
        $this->assertNotNull(questions::get($course->id, $kept[18]['id'], 1));
        // A question without two options is not kept.
        try {
            edits::save_questions($course->id, [['text' => '¿Sola?', 'options' => ['Una']]]);
            $this->fail('A question with one option was kept');
        } catch (\invalid_parameter_exception $e) {
            $this->assertCount(19, missions::get($course->id)->data['questions']);
        }
        // An empty list is the questions of the mission again.
        edits::save_questions($course->id, []);
        $this->assertCount(18, missions::get($course->id)->data['questions']);
    }

    public function test_a_newer_version_of_the_mission_keeps_the_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->make_course();
        edits::save($course->id, 'C1-2', 'title', 'Un título propio');
        // An older version installed: the newer package brings it up to date when the mission is read.
        $DB->set_field(self::T . 'mission', 'version', 1, ['courseid' => $course->id]);
        $this->assertGreaterThan(1, (int) missions::get($course->id)->version);
        $this->assertSame('Un título propio', $this->scene($course->id, 'C1-2')->data['title']);
        // And a deleted course takes its changes with it.
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records(self::T . 'edit', ['courseid' => $course->id]));
    }
}
