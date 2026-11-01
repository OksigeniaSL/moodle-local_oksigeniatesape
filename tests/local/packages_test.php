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
 * Tests for the packages of missions: those of the plugin and those uploaded to the site.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\missions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(missions::class)]
final class packages_test extends \advanced_testcase {
    /**
     * A zip with the pilot mission, changed: another code, version or title.
     *
     * @param array $changes Fields of mission.json to change.
     * @param bool $withmission Whether it has its mission.json.
     * @return string Path of the zip.
     */
    private function package(array $changes, bool $withmission = true): string {
        global $CFG;
        $dir = make_request_directory();
        $source = $CFG->dirroot . '/local/oksigeniatesape/missions/livingthings';
        $mission = array_merge(json_decode(file_get_contents($source . '/mission.json'), true), $changes);
        file_put_contents($dir . '/mission.json', json_encode($mission));
        $files = $withmission ? ['mission.json' => $dir . '/mission.json'] : [];
        foreach (glob($source . '/scenes/*.json') as $file) {
            $scene = json_decode(file_get_contents($file), true);
            $scene['mission'] = $mission['code'];
            file_put_contents($dir . '/' . basename($file), json_encode($scene));
            $files['scenes/' . basename($file)] = $dir . '/' . basename($file);
        }
        $zip = $dir . '/package.zip';
        get_file_packer('application/zip')->archive_to_pathname($files, $zip);
        return $zip;
    }

    public function test_the_mission_of_the_plugin_carries_its_identity(): void {
        $this->resetAfterTest();
        $mission = missions::packages()['livingthings'];
        $this->assertSame('plugin', missions::package_origin('livingthings'));
        $this->assertSame('free', $mission['licence']);
        $this->assertSame(1, $mission['edition']);
        $this->assertSame((int) $mission['version'], (int) $mission['changes'][0]['version']);
        $this->assertContains($mission['changes'][0]['kind'], ['fix', 'improvement', 'edition']);
    }

    public function test_a_mission_uploaded_to_the_site(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::upload($this->package(['code' => 'pruebas', 'version' => 1, 'licence' => 'paid']));
        $this->assertSame('pruebas', $mission['code']);
        $this->assertSame('site', missions::package_origin('pruebas'));
        $this->assertCount(31, missions::package_scenes('pruebas'));
        missions::install($course->id, 'pruebas', 'valley', 'gen');
        $this->assertSame('pruebas', missions::get($course->id)->code);
    }

    public function test_a_newer_version_of_a_mission_of_the_plugin(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'gen');
        edits::save($course->id, 'C1-2', 'title', 'Un título propio');
        $version = (int) missions::packages()['livingthings']['version'];
        missions::upload($this->package(['version' => $version + 1, 'subtitle' => ['valley' => 'Una versión nueva']]));
        $this->assertSame('site', missions::package_origin('livingthings'));
        // The course is brought up to date when it is opened, with the changes of the teacher.
        $mission = missions::get($course->id);
        $this->assertSame($version + 1, (int) $mission->version);
        $this->assertSame('Una versión nueva', $mission->data['subtitle']['valley']);
        $sceneid = $DB->get_field('local_oksigeniatesape_scene', 'id', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $this->assertSame('Un título propio', scenes::get($course->id, (int) $sceneid)->data['title']);
        // An older one is not taken.
        $this->expectException(\moodle_exception::class);
        missions::upload($this->package(['version' => $version]));
    }

    public function test_a_package_it_cannot_read_is_refused_saying_why(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $version = (int) missions::packages()['livingthings']['version'];
        foreach (
            [
                'missionformat' => ['version' => $version + 1, 'format' => missions::FORMAT + 1],
                'missionrequires' => ['version' => $version + 1, 'requires' => 2099010100],
            ] as $why => $changes
        ) {
            try {
                missions::upload($this->package($changes));
                $this->fail('It should not take it');
            } catch (\moodle_exception $e) {
                $this->assertSame($why, $e->errorcode);
            }
        }
        $this->assertSame($version, (int) missions::packages()['livingthings']['version']);
    }

    public function test_a_class_that_has_started_keeps_its_version(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $prefix = 'local_oksigeniatesape_';
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $old = (int) missions::install($course->id, 'livingthings', 'valley', 'gen')->version;
        $mission = missions::get($course->id);
        $scene = $DB->get_field($prefix . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C1-2']);
        // The class has played something.
        $DB->insert_record($prefix . 'sceneattempt', (object) ['sceneid' => $scene, 'charid' => 1, 'courseid' => $course->id,
            'teamplay' => 0, 'score' => 0, 'attempt' => 1, 'timestarted' => 1000, 'timefinished' => 0]);
        // A place closed long ago does not count.
        $places = array_values($DB->get_fieldset_select($prefix . 'chapter', 'id', 'missionid = ? ORDER BY id', [$mission->id]));
        $DB->set_field($prefix . 'chapter', 'litat', 1000, ['id' => $places[0]]);
        missions::upload($this->package(['version' => $old + 1, 'changes' => [['version' => $old + 1, 'text' => 'Lo nuevo'],
            ['version' => $old, 'text' => 'Lo de antes']]]));
        missions::forget();
        // It keeps its version, and the teacher is told what the new one brings.
        $this->assertSame($old, (int) missions::get($course->id)->version);
        $offered = missions::update_offered($course->id);
        $this->assertSame($old + 1, $offered['version']);
        $this->assertSame(['Lo nuevo'], $offered['changes']);
        $this->assertSame('valley', $offered['skin']);
        // Closing a place after the version came, it takes it, and there is nothing to offer.
        $DB->set_field($prefix . 'chapter', 'litat', time() + 10, ['id' => $places[1]]);
        missions::forget();
        $this->assertSame($old + 1, (int) missions::get($course->id)->version);
        $this->assertNull(missions::update_offered($course->id));
    }

    public function test_a_site_can_ask_for_every_new_version_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $prefix = 'local_oksigeniatesape_';
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $old = (int) missions::install($course->id, 'livingthings', 'valley', 'gen')->version;
        $mission = missions::get($course->id);
        $scene = $DB->get_field($prefix . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $DB->insert_record($prefix . 'sceneattempt', (object) ['sceneid' => $scene, 'charid' => 1, 'courseid' => $course->id,
            'teamplay' => 0, 'score' => 0, 'attempt' => 1, 'timestarted' => 1000, 'timefinished' => 0]);
        missions::upload($this->package(['version' => $old + 1]));
        missions::forget();
        $this->assertSame($old, (int) missions::get($course->id)->version);
        // A site that asks for always (a demo) takes it the next time it is opened.
        set_config('missionupdates', 'auto', 'local_oksigeniatesape');
        missions::forget();
        $this->assertSame($old + 1, (int) missions::get($course->id)->version);
    }

    public function test_the_teacher_can_take_the_new_version_at_once(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $prefix = 'local_oksigeniatesape_';
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $old = (int) missions::install($course->id, 'livingthings', 'valley', 'gen')->version;
        $mission = missions::get($course->id);
        $scene = $DB->get_field($prefix . 'scene', 'id', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $DB->insert_record($prefix . 'sceneattempt', (object) ['sceneid' => $scene, 'charid' => 1, 'courseid' => $course->id,
            'teamplay' => 0, 'score' => 0, 'attempt' => 1, 'timestarted' => 1000, 'timefinished' => 0]);
        missions::upload($this->package(['version' => $old + 1]));
        missions::forget();
        $this->assertSame($old, (int) missions::get($course->id)->version);
        missions::install($course->id, 'livingthings', 'valley', 'gen');
        missions::forget();
        $this->assertSame($old + 1, (int) missions::get($course->id)->version);
        $this->assertNull(missions::update_offered($course->id));
    }

    public function test_a_zip_that_is_not_a_mission(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        missions::upload($this->package([], false));
    }
}
