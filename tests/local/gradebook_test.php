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
 * Tests for the ratings of the competencies in the gradebook, when the teacher wants it.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\gradebook
 */
#[\PHPUnit\Framework\Attributes\CoversClass(gradebook::class)]
final class gradebook_test extends \advanced_testcase {
    /**
     * A course with the pilot mission, a teacher and a student with a character.
     *
     * @return \stdClass course, teacher, student.
     */
    private function make_class(): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->student = $gen->create_and_enrol($w->course, 'student');
        engine::member(characters::create($w->student->id, 'sage', 'f'), $w->course->id);
        return $w;
    }

    /**
     * The grade of a student in the item of an objective, or null.
     *
     * @param \stdClass $w
     * @param string $objective
     * @return float|null
     */
    private function grade(\stdClass $w, string $objective): ?float {
        $item = \grade_item::fetch(['courseid' => $w->course->id, 'idnumber' => 'tesape_livingthings_' . $objective]);
        if (!$item) {
            return null;
        }
        $grade = $item->get_grade($w->student->id, false);
        return $grade && $grade->finalgrade !== null ? (float) $grade->finalgrade : null;
    }

    public function test_off_by_default_nothing_goes_to_the_gradebook(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->setUser($w->teacher);
        competencies::rate($w->course->id, $w->student->id, 'SV-01', 3);
        $this->assertFalse(\grade_item::fetch(['courseid' => $w->course->id, 'idnumber' => 'tesape_livingthings_SV-01']));
    }

    public function test_turned_on_the_ratings_go_and_those_given_before_too(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->setUser($w->teacher);
        competencies::rate($w->course->id, $w->student->id, 'SV-01', 3);
        regions::save($w->course->id, ['gradebook' => 1]);
        // The rating given before, in its item, inside the category of the mission, with the scale of its competency.
        $this->assertSame(3.0, $this->grade($w, 'SV-01'));
        $item = \grade_item::fetch(['courseid' => $w->course->id, 'idnumber' => 'tesape_livingthings_SV-01']);
        $this->assertEquals(GRADE_TYPE_SCALE, $item->gradetype);
        $this->assertSame('Tesapé · Escala de dominio', $DB->get_field('scale', 'name', ['id' => $item->scaleid]));
        $this->assertSame('Tesapé · Los seres vivos', \grade_category::fetch(['id' => $item->categoryid])->fullname);
        // A new rating goes as it is given.
        competencies::rate($w->course->id, $w->student->id, 'SV-02', 2);
        $this->assertSame(2.0, $this->grade($w, 'SV-02'));
        // Turned off: the items stay as they were, and nothing new goes.
        regions::save($w->course->id, ['gradebook' => 0]);
        competencies::rate($w->course->id, $w->student->id, 'SV-02', 4);
        $this->assertSame(2.0, $this->grade($w, 'SV-02'));
        $this->assertSame(3.0, $this->grade($w, 'SV-01'));
    }
}
