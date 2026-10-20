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
use local_oksigeniatesape\external\photo;

/**
 * Tests for the photos of the classroom as stickers of the map.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\photos
 */
#[\PHPUnit\Framework\Attributes\CoversClass(photos::class)]
final class photos_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    /** @var string A picture of one pixel, as the tablet sends it. */
    private const PIXEL = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    /**
     * A course with the game on, the pilot mission, two teams of two and a teacher.
     *
     * @return \stdClass course, context, teams (two), users (four: two in each team), teacher.
     */
    private function make_class(): \stdClass {
        $gen = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $gen->create_course();
        $w->context = \context_course::instance($w->course->id);
        regions::save($w->course->id, ['enabled' => 1]);
        missions::install($w->course->id, 'livingthings', 'valley', 'es', self::NOW);
        $w->teacher = $gen->create_and_enrol($w->course, 'editingteacher');
        $w->teams = [$gen->create_group(['courseid' => $w->course->id]), $gen->create_group(['courseid' => $w->course->id])];
        $w->users = [];
        foreach (['protector', 'healer', 'sage', 'scout'] as $i => $role) {
            $user = $gen->create_and_enrol($w->course, 'student');
            $gen->create_group_member(['groupid' => $w->teams[intdiv($i, 2)]->id, 'userid' => $user->id]);
            engine::member(characters::create($user->id, $role, 'f'), $w->course->id, self::NOW);
            $w->users[] = $user;
        }
        return $w;
    }

    /**
     * The pictures kept for the photos of a course.
     *
     * @param \stdClass $w
     * @return int
     */
    private function files(\stdClass $w): int {
        return count(get_file_storage()->get_area_files(
            $w->context->id,
            'local_oksigeniatesape',
            photos::FILEAREA,
            false,
            'itemid',
            false
        ));
    }

    public function test_a_photo_waits_for_the_teacher_and_only_then_is_on_the_map_of_its_team(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $photo = photos::upload($w->course->id, $w->users[0]->id, 'claro', self::PIXEL, self::NOW);
        $this->assertSame('pending', $photo->status);
        $this->assertSame(1, $this->files($w));
        // Waiting: its team knows it was sent, without the picture; the other team, nothing.
        $mine = photos::of_team($w->course->id, $w->users[1]->id);
        $this->assertSame('pending', $mine['claro']['status']);
        $this->assertSame('', $mine['claro']['url']);
        $this->assertFalse($mine['claro']['mine']);
        $this->assertTrue(photos::of_team($w->course->id, $w->users[0]->id)['claro']['mine']);
        $this->assertSame([], photos::of_team($w->course->id, $w->users[2]->id));
        // The teacher sees it with its picture; the class screen, not yet.
        $all = photos::of_course($w->course->id);
        $this->assertCount(1, $all);
        $this->assertNotSame('', $all[0]['url']);
        $this->assertSame([], photos::of_course($w->course->id, true));
        $this->assertFalse(photos::can_see($photo, $w->context, $w->users[0]->id));
        $this->assertTrue(photos::can_see($photo, $w->context, $w->teacher->id));
        // Approved: on the map of the team and on the class screen, never for the other team.
        photos::review($w->course->id, $photo->id, true, $w->teacher->id, self::NOW + 60);
        $this->assertSame('approved', photos::of_course($w->course->id, true)[0]['status']);
        $this->assertNotSame('', photos::of_team($w->course->id, $w->users[1]->id)['claro']['url']);
        $record = (object) ['status' => 'approved', 'groupid' => $w->teams[0]->id];
        $this->assertTrue(photos::can_see($record, $w->context, $w->users[1]->id));
        $this->assertFalse(photos::can_see($record, $w->context, $w->users[2]->id));
    }

    public function test_a_photo_turned_down_goes_away_and_a_new_one_takes_the_place_of_the_old(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $first = photos::upload($w->course->id, $w->users[0]->id, 'claro', self::PIXEL, self::NOW);
        photos::review($w->course->id, $first->id, false, $w->teacher->id, self::NOW + 60);
        // The picture is deleted; the team knows it was not approved; the teacher does not see it again.
        $this->assertSame(0, $this->files($w));
        $this->assertSame('rejected', photos::of_team($w->course->id, $w->users[0]->id)['claro']['status']);
        $this->assertSame([], photos::of_course($w->course->id));
        // Another one, by the other member: only one photo for the place, waiting again.
        photos::upload($w->course->id, $w->users[1]->id, 'claro', self::PIXEL, self::NOW + 120);
        photos::upload($w->course->id, $w->users[1]->id, 'claro', self::PIXEL, self::NOW + 180);
        $this->assertSame(1, $this->files($w));
        $this->assertSame(1, (int) $GLOBALS['DB']->count_records(self::T . 'photo', ['courseid' => $w->course->id]));
        $this->assertSame('pending', photos::of_team($w->course->id, $w->users[0]->id)['claro']['status']);
    }

    public function test_only_a_picture_of_a_place_of_the_mission_from_someone_with_a_team(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        foreach (
            [['nowhere', self::PIXEL], ['claro', 'data:text/plain;base64,aG9sYQ=='],
                ['claro', 'data:image/png;base64,aG9sYQ==']] as [$chapter, $picture]
        ) {
            try {
                photos::upload($w->course->id, $w->users[0]->id, $chapter, $picture, self::NOW);
                $this->fail('Sent: ' . $chapter);
            } catch (\moodle_exception $e) {
                $this->assertSame(0, $this->files($w));
            }
        }
        // Without a team, nothing to stick on a map.
        $loner = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        characters::create($loner->id, 'sage', 'f');
        $this->expectException(\invalid_parameter_exception::class);
        photos::upload($w->course->id, $loner->id, 'claro', self::PIXEL, self::NOW);
    }

    public function test_the_photos_of_who_leaves_go_with_their_pictures(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        photos::upload($w->course->id, $w->users[0]->id, 'claro', self::PIXEL, self::NOW);
        photos::upload($w->course->id, $w->users[2]->id, 'claro', self::PIXEL, self::NOW);
        photos::remove_in_course($w->course->id, [(int) characters::get($w->users[0]->id)->id]);
        $this->assertSame(1, $this->files($w));
        $this->assertSame([], photos::of_team($w->course->id, $w->users[1]->id));
        photos::remove_in_course($w->course->id);
        $this->assertSame(0, $this->files($w));
    }

    public function test_a_course_deleted_or_reset_takes_its_photos(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        photos::upload($w->course->id, $w->users[0]->id, 'claro', self::PIXEL, self::NOW);
        // A reset for next year that takes the students out: what was played goes, with the photos.
        \core\event\course_reset_ended::create(['context' => $w->context, 'courseid' => $w->course->id,
            'other' => ['reset_options' => ['unenrol_users' => [$DB->get_field('role', 'id', ['shortname' => 'student'])]]]])
            ->trigger();
        $this->assertSame(0, $this->files($w));
        $this->assertSame(0, $DB->count_records(self::T . 'photo'));
        photos::upload($w->course->id, $w->users[0]->id, 'claro', self::PIXEL, self::NOW);
        delete_course($w->course, false);
        $this->assertSame(0, $DB->count_records(self::T . 'photo'));
    }

    public function test_the_service_lets_a_student_send_and_only_a_teacher_review(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->setUser($w->users[0]);
        $out = external_api::clean_returnvalue(photo::execute_returns(), photo::execute(
            $w->course->id,
            'upload',
            'claro',
            self::PIXEL
        ));
        $this->assertSame('pending', $out['photos'][0]['status']);
        $id = (int) $GLOBALS['DB']->get_field(self::T . 'photo', 'id', ['courseid' => $w->course->id]);
        try {
            photo::execute($w->course->id, 'review', '', '', $id, true);
            $this->fail('A student reviewed a photo');
        } catch (\required_capability_exception $e) {
            $this->assertSame('pending', $GLOBALS['DB']->get_field(self::T . 'photo', 'status', ['id' => $id]));
        }
        $this->setUser($w->teacher);
        $out = external_api::clean_returnvalue(photo::execute_returns(), photo::execute(
            $w->course->id,
            'review',
            '',
            '',
            $id,
            true
        ));
        $this->assertSame('approved', $out['photos'][0]['status']);
        // The place by its name, not its code.
        $this->assertNotSame('claro', $out['photos'][0]['place']);
    }
}
