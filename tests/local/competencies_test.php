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

use core_competency\competency;
use core_competency\competency_framework;
use core_competency\course_competency;
use core_competency\evidence;
use core_competency\user_competency;

/**
 * Tests for the competencies of Moodle in the game.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\competencies
 */
#[\PHPUnit\Framework\Attributes\CoversClass(competencies::class)]
final class competencies_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission, a teacher and a student with a character.
     *
     * @return \stdClass course, mission, teacher, student.
     */
    private function make_class(): \stdClass {
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->mission = missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->student = $gen->create_and_enrol($w->course, 'student');
        engine::member(characters::create($w->student->id, 'sage', 'f'), $w->course->id);
        return $w;
    }

    public function test_the_mission_brings_its_framework_and_links_it_to_the_course(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $framework = competency_framework::get_record(['idnumber' => 'tesape-seres-vivos']);
        $this->assertNotFalse($framework);
        $this->assertSame('Tesapé · Los seres vivos', $framework->get('shortname'));
        $this->assertCount(12, competency::get_records(['competencyframeworkid' => $framework->get('id')]));
        $levels = array_values($framework->get_scale()->load_items());
        $this->assertSame(['Empezando', 'En camino', 'Lo consigue', 'Lo domina'], $levels);
        // Each objective, its competency of the framework, in the course.
        $links = competencies::links($w->course->id);
        $this->assertCount(8, $links);
        $sv01 = competency::get_record(['idnumber' => 'tesape-sv-01']);
        $this->assertEquals($sv01->get('id'), $links['SV-01']);
        $this->assertSame(8, course_competency::count_records(['courseid' => $w->course->id]));
        // A second course with the mission uses the same framework.
        $other = $this->getDataGenerator()->create_course();
        missions::install($other->id, 'livingthings', 'azara', 'py');
        $this->assertSame(1, competency_framework::count_records(['idnumber' => 'tesape-seres-vivos']));
        // Made again (a first try that stopped half way): the scale already there is used, not a second one.
        $framework->delete();
        competencies::framework(missions::get($w->course->id));
        $this->assertSame(1, $GLOBALS['DB']->count_records('scale', ['name' => 'Tesapé · Escala de dominio']));
    }

    public function test_a_scene_leaves_evidence_and_the_teacher_rates(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $nest = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        $events = [
            ['n' => 'antes', 'pick' => ['buscar']],
            ['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'rana']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        scenes::play($w->course->id, $w->student->id, $nest->id, $events, null);
        $sv01 = competency::get_record(['idnumber' => 'tesape-sv-01']);
        $uc = user_competency::get_record(['userid' => $w->student->id, 'competencyid' => $sv01->get('id')]);
        $this->assertNotFalse($uc);
        $evidence = evidence::get_records(['usercompetencyid' => $uc->get('id')]);
        $this->assertCount(1, $evidence);
        $item = reset($evidence);
        $this->assertEquals(evidence::ACTION_LOG, $item->get('action'));
        // It says how the answers came, not only how many were right.
        $this->assertSame('evidence_scene_how', $item->get('descidentifier'));
        $a = (array) $item->get('desca');
        $this->assertSame($a['ok'], $a['first'] + $a['helped'] + $a['after']);
        // The grid: the share right at the first try, and the rating the teacher gives.
        $this->setUser($w->teacher);
        $view = competencies::view($w->course->id);
        $this->assertTrue($view['enabled']);
        $first = array_search('SV-01', array_column($view['objectives'], 'code'), true);
        $this->assertSame(100, $view['students'][0]['cells'][$first]['percent']);
        $this->assertSame(0, $view['students'][0]['cells'][$first]['rating']);
        // All right alone at the first try: the game suggests the top of the scale; the teacher decides.
        $this->assertSame(4, $view['students'][0]['cells'][$first]['suggested']);
        $this->assertGreaterThan(0, $view['students'][0]['cells'][$first]['first']);
        competencies::rate($w->course->id, $w->student->id, 'SV-01', 3);
        $view = competencies::view($w->course->id);
        $this->assertSame(3, $view['students'][0]['cells'][$first]['rating']);
        // Several at once, as the teacher confirms the suggestions.
        \core_external\external_api::clean_returnvalue(
            \local_oksigeniatesape\external\rate_competency::execute_returns(),
            \local_oksigeniatesape\external\rate_competency::execute($w->course->id, 0, '', 0, [
                ['userid' => $w->student->id, 'objective' => 'SV-01', 'grade' => 2],
                ['userid' => $w->student->id, 'objective' => 'SV-02', 'grade' => 1],
            ])
        );
        $view = competencies::view($w->course->id);
        $second = array_search('SV-02', array_column($view['objectives'], 'code'), true);
        $this->assertSame([2, 1], [$view['students'][0]['cells'][$first]['rating'],
            $view['students'][0]['cells'][$second]['rating']]);
    }

    public function test_the_suggested_level_weighs_how_the_answers_came(): void {
        // Four levels: alone counts most, with help a bit less, after the consequence less; repeating a mistake takes away.
        $this->assertSame(4, competencies::suggest(['total' => 4, 'first' => 4], 4));
        $this->assertSame(3, competencies::suggest(['total' => 4, 'first' => 2, 'helped' => 2], 4));
        $this->assertSame(2, competencies::suggest(['total' => 4, 'after' => 4], 4));
        $this->assertSame(1, competencies::suggest(['total' => 4, 'twice' => 2], 4));
        // The top levels ask for more: half right alone is on the way, not there yet.
        $this->assertSame(2, competencies::suggest(['total' => 100, 'first' => 53], 4));
        $this->assertSame(3, competencies::suggest(['total' => 100, 'first' => 70], 4));
        $this->assertSame(4, competencies::suggest(['total' => 100, 'first' => 90], 4));
        $this->assertSame(0, competencies::suggest(['total' => 0], 4));
        // A play from before this was kept: what was right counts as at the first try.
        $this->assertSame(['first' => 3, 'helped' => 0, 'after' => 0, 'twice' => 0], competencies::how(['ok' => 3, 'total' => 4]));
    }

    public function test_the_school_can_use_its_own_competencies(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->setUser($w->teacher);
        $generator = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $own = $generator->create_framework(['shortname' => 'Marco del centro']);
        $theirs = $generator->create_competency(['competencyframeworkid' => $own->get('id'), 'shortname' => 'Ciencias 1']);
        $this->setAdminUser();
        \core_competency\api::add_competency_to_course($w->course->id, $theirs->get('id'));
        $this->setUser($w->teacher);
        $view = competencies::view($w->course->id);
        $this->assertContains('Marco del centro', array_column($view['options'], 'framework'));
        competencies::set($w->course->id, 'SV-01', $theirs->get('id'));
        $this->assertEquals($theirs->get('id'), competencies::links($w->course->id)['SV-01']);
        // None at all: the objective leaves no evidence.
        competencies::set($w->course->id, 'SV-02', 0);
        $this->assertSame(0, (int) competencies::links($w->course->id)['SV-02']);
    }

    public function test_without_competencies_in_the_site_nothing_happens(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('enabled', 0, 'core_competency');
        $w = $this->make_class();
        $this->assertSame([], competencies::links($w->course->id));
        $this->assertFalse(competencies::view($w->course->id)['enabled']);
        $nest = $DB->get_record(self::T . 'scene', ['missionid' => $w->mission->id, 'code' => 'C1-2']);
        $events = [
            ['n' => 'antes', 'pick' => ['buscar']],
            ['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'rana']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        scenes::play($w->course->id, $w->student->id, $nest->id, $events, null);
        $this->assertSame(0, $DB->count_records('competency_evidence'));
    }
}
