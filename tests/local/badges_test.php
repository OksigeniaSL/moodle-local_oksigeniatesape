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
 * Tests for the badge of Moodle a mission brings.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\badges
 */
#[\PHPUnit\Framework\Attributes\CoversClass(badges::class)]
final class badges_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission and a student with a character.
     *
     * @return \stdClass course, mission (with data), student, char.
     */
    private function make_class(): \stdClass {
        global $CFG;
        require_once($CFG->libdir . '/badgeslib.php');
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $w->mission = missions::get($w->course->id);
        $w->student = $gen->create_and_enrol($w->course, 'student');
        $w->char = characters::create($w->student->id, 'sage', 'f');
        engine::member($w->char, $w->course->id);
        return $w;
    }

    /**
     * The student lights the lanterns of some places of the mission.
     *
     * @param \stdClass $w
     * @param string[] $codes
     */
    private function light(\stdClass $w, array $codes): void {
        global $DB;
        foreach ($codes as $code) {
            $DB->insert_record(self::T . 'found', (object) ['charid' => $w->char->id, 'courseid' => $w->course->id,
                'missionid' => $w->mission->id, 'kind' => 'lantern', 'code' => $code, 'timecreated' => time()]);
        }
    }

    public function test_the_mission_brings_its_badge_turned_off(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class();
        $id = badges::find($w->course->id, $w->mission);
        $this->assertNotSame(0, $id);
        $badge = new \core_badges\badge($id);
        $this->assertSame('Naturalista del Valle de Tesapé', $badge->name);
        $this->assertFalse($badge->is_active());
        // With its medallion, and the one of the plugin still there for the next course.
        $fs = get_file_storage();
        $this->assertTrue($fs->file_exists($badge->get_context()->id, 'badges', 'badgeimage', $id, '/', 'f3.png'));
        $this->assertFileExists(__DIR__ . '/../../pix/badge.png');
        // Given by the game or by the teacher by hand.
        $this->assertTrue($badge->has_criteria());
        $this->assertTrue($DB->record_exists('badge_criteria', ['badgeid' => $id, 'criteriatype' => BADGE_CRITERIA_TYPE_MANUAL]));
        // Installed again (a newer package), the same badges: that of the mission and that of the explorer.
        missions::install($w->course->id, 'livingthings', 'valley', 'es');
        $this->assertSame(2, $DB->count_records('badge', ['courseid' => $w->course->id]));
        $this->assertNotSame(0, badges::find($w->course->id, $w->mission, 'explorer'));
    }

    public function test_the_last_lantern_gives_the_badge_once_it_is_on(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class();
        $id = badges::find($w->course->id, $w->mission);
        $places = $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$w->mission->id]);
        $this->assertCount(6, $places);
        $this->light($w, array_slice($places, 0, 5));
        // Turned off: nothing, even with every lantern.
        $this->light($w, array_slice($places, 5));
        badges::check($w->course->id, $w->student->id);
        $this->assertFalse((new \core_badges\badge($id))->is_issued($w->student->id));
        // Turned on by the teacher: with five of six, nothing; with six, the badge.
        $DB->delete_records(self::T . 'found', ['charid' => $w->char->id, 'code' => end($places)]);
        (new \core_badges\badge($id))->set_status(BADGE_STATUS_ACTIVE);
        badges::check($w->course->id, $w->student->id);
        $this->assertFalse((new \core_badges\badge($id))->is_issued($w->student->id));
        $this->light($w, [end($places)]);
        badges::check($w->course->id, $w->student->id);
        $this->assertTrue((new \core_badges\badge($id))->is_issued($w->student->id));
        // Once.
        badges::check($w->course->id, $w->student->id);
        $this->assertSame(1, $DB->count_records('badge_issued', ['badgeid' => $id, 'userid' => $w->student->id]));
    }

    public function test_turned_on_at_the_end_it_goes_to_who_has_every_lantern(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class();
        $id = badges::find($w->course->id, $w->mission);
        $this->light($w, $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$w->mission->id]));
        $other = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        engine::member(characters::create($other->id, 'scout', 'm'), $w->course->id);
        // The teacher turns it on when the mission is over: whoever lit every lantern gets it at once.
        (new \core_badges\badge($id))->set_status(BADGE_STATUS_ACTIVE);
        $this->assertTrue((new \core_badges\badge($id))->is_issued($w->student->id));
        $this->assertFalse((new \core_badges\badge($id))->is_issued($other->id));
    }

    public function test_the_game_knows_when_the_student_has_the_badge(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $w = $this->make_class();
        $id = badges::find($w->course->id, $w->mission);
        // Turned off: the game says nothing of it.
        $this->assertNull(badges::for_student($w->course->id, $w->student->id));
        $this->assertArrayNotHasKey('badge', view::world($w->course->id, $w->student->id));
        (new \core_badges\badge($id))->set_status(BADGE_STATUS_ACTIVE);
        $badge = badges::for_student($w->course->id, $w->student->id);
        $this->assertSame(badges::name($w->mission), $badge['name']);
        $this->assertFalse($badge['issued']);
        $this->assertStringContainsString('/badges/badgeimage/' . $id . '/f3', $badge['image']);
        // With every lantern, the student has it, and the game celebrates it.
        $this->light($w, $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$w->mission->id]));
        badges::check($w->course->id, $w->student->id);
        $this->assertTrue(view::world($w->course->id, $w->student->id)['badge']['issued']);
    }

    public function test_without_badges_in_courses_there_is_none(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('badges_allowcoursebadges', 0);
        $w = $this->make_class();
        $this->assertSame(0, $DB->count_records('badge', ['courseid' => $w->course->id]));
        $this->light($w, $DB->get_fieldset_select(self::T . 'chapter', 'code', 'missionid = ?', [$w->mission->id]));
        badges::check($w->course->id, $w->student->id);
        $this->assertSame(0, $DB->count_records('badge_issued', ['userid' => $w->student->id]));
    }
}
