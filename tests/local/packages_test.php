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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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
        $this->assertCount(28, missions::package_scenes('pruebas'));
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

    public function test_a_zip_that_is_not_a_mission(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        missions::upload($this->package([], false));
    }
}
