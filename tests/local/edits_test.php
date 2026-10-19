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
        $this->assertCount(19, $view['questions']);
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
        foreach (['title', 'whatif', 'text', 'prompt', 'hints', 'label', 'feedback', 'stuck'] as $kind) {
            $this->assertArrayHasKey($kind, $kinds);
        }
    }

    public function test_a_change_is_played_and_goes_back_to_the_original(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $original = $this->scene($course->id, 'C1-2')->data;
        $this->assertTrue(edits::save($course->id, 'C1-2', 'title', 'Un título propio'));
        $this->assertTrue(edits::save($course->id, 'C1-2', 'whatif', '¿Y si el nido estuviera en el patio del cole?'));
        $this->assertSame('¿Y si el nido estuviera en el patio del cole?', $this->scene($course->id, 'C1-2')->data['whatif']);
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

    public function test_the_teacher_switches_the_sniffing_of_the_creature_in_a_scene(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $scenes = array_column(array_merge(...array_column(edits::view($course->id)['chapters'], 'scenes')), null, 'code');
        // The nest has no question to sniff; the clue of the C2-2, one.
        $this->assertFalse($scenes['C1-2']['sniffable']);
        $this->assertTrue($scenes['C2-2']['sniffable']);
        $this->assertTrue($scenes['C2-2']['sniff']);
        $this->assertTrue(edits::save($course->id, 'C2-2', edits::SNIFF, 'off'));
        $this->assertFalse($this->scene($course->id, 'C2-2')->data['sniff']);
        $scenes = array_column(array_merge(...array_column(edits::view($course->id)['chapters'], 'scenes')), null, 'code');
        $this->assertFalse($scenes['C2-2']['sniff']);
        $this->assertTrue($scenes['C2-2']['sniffcame']);
        // Back as it came; and nothing but on, off or empty, nor in a scene without a question to sniff.
        $this->assertFalse(edits::save($course->id, 'C2-2', edits::SNIFF, ''));
        $this->assertArrayNotHasKey('sniff', $this->scene($course->id, 'C2-2')->data);
        $this->expectException(\invalid_parameter_exception::class);
        edits::save($course->id, 'C1-2', edits::SNIFF, 'off');
    }

    public function test_the_teacher_takes_a_scene_out_of_the_game(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->make_course();
        $mission = missions::get($course->id);
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => $mission->data['chapters'][0]['code']]);
        $here = array_values($DB->get_records(self::T . 'scene', ['chapterid' => $chapter->id], 'code'));
        $route = array_values(array_filter($here, fn($s) => empty($s->quest)));
        $this->assertGreaterThan(1, count($route));
        $out = $route[0];
        edits::save($course->id, $out->code, edits::ENABLED, 'off');
        // Not in the workshop as in the game, not in any route, and it cannot be played.
        $scenes = array_column(array_merge(...array_column(edits::view($course->id)['chapters'], 'scenes')), null, 'code');
        $this->assertFalse($scenes[$out->code]['enabled']);
        foreach ([1, 2, 3, 4] as $step) {
            $this->assertNotContains($out->code, array_column(scenes::for_step($here, $step), 'code'));
        }
        try {
            scenes::playable($course->id, (int) $out->id);
            $this->fail('A scene out of the game was played');
        } catch (\moodle_exception $e) {
            $this->assertSame('invalidgameplay', $e->errorcode);
        }
        // Back in the game.
        edits::save($course->id, $out->code, edits::ENABLED, '');
        $this->assertContains($out->code, array_column(scenes::for_step($here, max(1, (int) $out->step)), 'code'));
        // The last scene of the chapter that is not a quest always stays.
        foreach (array_slice($route, 1) as $scene) {
            edits::save($course->id, $scene->code, edits::ENABLED, 'off');
        }
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('wslastscene', 'local_oksigeniatesape'));
        edits::save($course->id, $route[0]->code, edits::ENABLED, 'off');
    }

    public function test_the_class_passes_a_place_by_and_comes_back(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->make_course();
        $mission = missions::get($course->id);
        [$first, $second] = array_column($mission->data['chapters'], 'code');
        $this->assertSame($first, pacts::chapter($mission)->code);
        edits::skip($course->id, $first, true);
        // Its lantern is lit without playing it, and the expedition goes on in the next place.
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => $first]);
        $this->assertGreaterThan(0, (int) $chapter->litat);
        $this->assertGreaterThan(0, (int) $chapter->skippedat);
        $this->assertSame($second, pacts::chapter($mission)->code);
        $places = array_column(edits::view($course->id)['chapters'], null, 'code');
        $this->assertTrue($places[$first]['skipped']);
        $this->assertFalse($places[$first]['skippable']);
        // Back to it: its lantern goes off.
        edits::skip($course->id, $first, false);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'chapter', 'litat', ['id' => $chapter->id]));
        $this->assertSame($first, pacts::chapter($mission)->code);
        // Not the place of the final battle.
        $this->expectException(\moodle_exception::class);
        edits::skip($course->id, (string) $mission->data['battle']['final']['lantern'], true);
    }

    public function test_the_teacher_chooses_the_classroom_challenge_of_a_place(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->make_course();
        $mission = missions::get($course->id);
        $first = $mission->data['chapters'][0]['code'];
        $make = fn($name) => $DB->insert_record(self::T . 'challenge', (object) ['cmid' => 0, 'courseid' => $course->id,
            'kind' => 'classroom', 'name' => $name, 'description' => '', 'teamwork' => 0, 'saber' => 10, 'gems' => 0,
            'steps' => '', 'itemid' => 0, 'objectives' => '', 'chapterid' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        $plant = $make('Plantar una semilla');
        $owl = $make('Dibujar el búho');
        edits::place_challenge($course->id, $first, $plant);
        $view = edits::view($course->id);
        $this->assertContains('Plantar una semilla', array_column($view['challenges'], 'name'));
        $this->assertSame($plant, $view['chapters'][0]['challengeid']);
        // Another one takes its place; none leaves the place without one.
        edits::place_challenge($course->id, $first, $owl);
        $this->assertSame($owl, edits::view($course->id)['chapters'][0]['challengeid']);
        $this->assertSame(0, (int) $DB->get_field(self::T . 'challenge', 'chapterid', ['id' => $plant]));
        edits::place_challenge($course->id, $first, 0);
        $this->assertSame(0, edits::view($course->id)['chapters'][0]['challengeid']);
        // Only a classroom challenge of this course.
        $this->expectException(\invalid_parameter_exception::class);
        edits::place_challenge($course->id, $first, 99999);
    }

    public function test_a_question_taken_for_a_knot_is_one_of_its_lanes(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $mission = missions::get($course->id);
        $knot = $mission->data['battle']['knots'][0];
        $before = count($knot['questions']);
        $lanes = count(array_column(knots::usable($mission), null, 'code')[$knot['code']]['lanes']);
        $id = edits::knot_accept($course->id, $knot['code'], ['text' => '¿Está viva una semilla seca?',
            'options' => ['Sí, aunque no lo parezca', 'No, porque no se mueve', 'Solo si crece'], 'why' => 'Con agua germina.']);
        $mission = missions::get($course->id);
        // In the battle questions, with the place and the objective of the knot; and one more lane of the knot.
        $question = array_column($mission->data['questions'], null, 'id')[$id];
        $this->assertSame($knot['objective'], $question['objective']);
        $this->assertSame($mission->data['chapters'][(int) $knot['chapter'] - 1]['code'], $question['chapter']);
        $this->assertCount($before + 1, array_column($mission->data['battle']['knots'], null, 'code')[$knot['code']]['questions']);
        $usable = array_column(knots::usable($mission), null, 'code')[$knot['code']];
        $this->assertContains($id, $usable['questions']);
        $this->assertCount($lanes + 1, $usable['lanes']);
        // A knot without lanes of its own plays its questions: the one taken, once.
        $bare = array_values(array_filter($mission->data['battle']['knots'], fn($k) => empty($k['lanes'])))[0];
        $other = edits::knot_accept($course->id, $bare['code'], ['text' => '¿Vuela el murciélago?', 'options' => ['Sí', 'No'],
            'why' => 'Tiene alas.']);
        $usable = array_column(knots::usable(missions::get($course->id)), null, 'code')[$bare['code']];
        $this->assertCount(1, array_keys($usable['questions'], $other));
        // The proposal of the AI is cleaned as anything a teacher writes.
        $clean = edits::clean_proposal(['questions' => [['text' => ' ¿Qué es? ', 'options' => ['A', '', 'B', 'C', 'D', 'E']],
            ['text' => 'Sin opciones', 'options' => ['A']], 'no'], 'laughs' => ['¡Ja!', '¡Je!', '¡Ji!']]);
        $this->assertSame([['text' => '¿Qué es?', 'options' => ['A', 'B', 'C'], 'why' => '']], $clean['questions']);
        $this->assertSame(['¡Ja!', '¡Je!'], $clean['laughs']);
        // Without the AI of the site, nothing to ask.
        $this->expectException(\moodle_exception::class);
        edits::knot_proposal($course->id, $knot['code'], (int) get_admin()->id);
    }

    public function test_the_sheets_of_the_sessions_can_be_changed_and_the_guide_reads_them(): void {
        $this->resetAfterTest();
        $course = $this->make_course();
        $paths = array_column(array_merge(...array_column(edits::view($course->id)['chapters'], 'fields')), 'path');
        $this->assertContains('chapters/claro/sessions/1/steps/1/do', $paths);
        $this->assertContains('chapters/claro/sessions/1/questions/0/expect', $paths);
        // The entry session goes with the first place.
        $this->assertContains('entry/watch/1/redirect', $paths);
        $this->assertTrue(edits::save($course->id, edits::MISSION, 'chapters/claro/sessions/1/steps/1/do', 'El nido, con calma.'));
        $this->assertTrue(edits::save($course->id, edits::MISSION, 'entry/questions/0/ask', '¿Qué hace cada papel?'));
        $mission = missions::get($course->id);
        $this->assertSame('El nido, con calma.', sessions::view($course->id, $mission, 'claro', 2)['steps'][1]['do']);
        $this->assertSame('¿Qué hace cada papel?', sessions::view($course->id, $mission, sessions::ENTRY, 1)['questions'][0]['ask']);
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
        // The knots of the battle are texts of their place: what the Embrollo claims can be the teacher's.
        $paths = array_column(edits::view($course->id)['chapters'][0]['fields'], 'path');
        $this->assertContains('battle/knots/piedra/claim', $paths);
        $this->assertTrue(edits::save($course->id, edits::MISSION, 'battle/knots/piedra/claim', '¡La piedra respira!'));
        $this->assertSame('¡La piedra respira!', knots::get(missions::get($course->id), 'piedra')['claim']);
        $questions = edits::view($course->id)['questions'];
        $questions[0]['text'] = '¿Una pregunta mía?';
        $questions[] = ['id' => '', 'chapter' => 'lago', 'objective' => 'SV-06', 'text' => '¿Una más?',
            'options' => ['Sí', '', 'No'], 'why' => ''];
        $kept = edits::save_questions($course->id, $questions);
        $this->assertCount(20, $kept);
        $this->assertSame(['Sí', 'No'], $kept[19]['options']);
        $this->assertNotSame('', $kept[19]['id']);
        $data = missions::get($course->id)->data;
        $this->assertSame('¿Una pregunta mía?', $data['questions'][0]['text']);
        $this->assertNotNull(questions::get($course->id, $kept[19]['id'], 1));
        // A question without two options is not kept.
        try {
            edits::save_questions($course->id, [['text' => '¿Sola?', 'options' => ['Una']]]);
            $this->fail('A question with one option was kept');
        } catch (\invalid_parameter_exception $e) {
            $this->assertCount(20, missions::get($course->id)->data['questions']);
        }
        // An empty list is the questions of the mission again.
        edits::save_questions($course->id, []);
        $this->assertCount(19, missions::get($course->id)->data['questions']);
    }

    public function test_the_changes_go_to_another_course_with_the_same_mission(): void {
        $this->resetAfterTest();
        $from = $this->make_course();
        $to = $this->make_course();
        edits::save($from->id, 'C1-2', 'title', 'Un título propio');
        edits::save($to->id, 'C1-2', 'nodes/intro/text', 'Una narración que se queda');
        $questions = edits::view($from->id)['questions'];
        $questions[0]['text'] = '¿Una pregunta mía?';
        edits::save_questions($from->id, $questions);
        $data = edits::export($from->id);
        $this->assertSame('livingthings', $data['mission']);
        // A text the other mission does not have is left out; the rest goes.
        $data['edits'][] = ['scene' => 'C1-2', 'path' => 'nodes/nope/text', 'value' => 'No encaja'];
        $done = edits::import($to->id, $data);
        $this->assertSame(['applied' => 2, 'skipped' => 1], $done);
        $scene = $this->scene($to->id, 'C1-2')->data;
        $this->assertSame('Un título propio', $scene['title']);
        $this->assertSame('Una narración que se queda', $scene['nodes']['intro']['text']);
        $this->assertSame('¿Una pregunta mía?', missions::get($to->id)->data['questions'][0]['text']);
        // Changes of another mission do not fit.
        $data['mission'] = 'fractions';
        $this->expectException(\moodle_exception::class);
        edits::import($to->id, $data);
    }

    public function test_a_newer_version_of_the_mission_keeps_the_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->make_course();
        edits::save($course->id, 'C1-2', 'title', 'Un título propio');
        // An older version installed: the newer package brings it up to date when the mission is read.
        $DB->set_field(self::T . 'mission', 'version', 1, ['courseid' => $course->id]);
        missions::forget();
        $this->assertGreaterThan(1, (int) missions::get($course->id)->version);
        $this->assertSame('Un título propio', $this->scene($course->id, 'C1-2')->data['title']);
        // And a deleted course takes its changes with it.
        delete_course($course, false);
        $this->assertSame(0, $DB->count_records(self::T . 'edit', ['courseid' => $course->id]));
    }
}
