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
 * Tests for the page of the game client and the teacher panel.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\page
 * @covers     \local_oksigeniatesape\hook_callbacks
 */
#[\PHPUnit\Framework\Attributes\CoversClass(page::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_oksigeniatesape\hook_callbacks::class)]
final class page_test extends \advanced_testcase {
    public function test_names_cannot_break_out_of_the_script(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(['fullname' => 'Science </script><script>alert(1)</script>']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        // Straight into the database: the generator would clean it, an import or a web service might not.
        $DB->set_field('user', 'firstname', '</script><b>x', ['id' => $user->id]);
        $this->setUser($DB->get_record('user', ['id' => $user->id]));
        ob_start();
        page::output('game', $course);
        $html = ob_get_clean();
        // The configuration and the fourteen scripts of the game: nothing in a name closes a script early.
        $this->assertSame(15, substr_count($html, '</script>'));
        preg_match('/window\.TESAPE_CONFIG = (\{.*?\});<\/script>/s', $html, $m);
        $config = json_decode($m[1], true);
        $this->assertSame('</script><b>x', $config['firstname']);
        $this->assertSame((int) $course->id, $config['courseid']);
        $this->assertArrayHasKey('app_who', $config['strings']);
        $this->assertArrayHasKey('role_storyheroes_sage_f', $config['strings']);
        $this->assertArrayNotHasKey('pluginname', $config['strings']);
    }

    public function test_course_link(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $link = function (\stdClass $user) use ($course): ?string {
            global $PAGE;
            $this->setUser($user);
            $PAGE = new \moodle_page();
            $PAGE->set_course($course);
            $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));
            $view = new \core\navigation\views\secondary($PAGE);
            \local_oksigeniatesape\hook_callbacks::secondary_extend(new \core\hook\navigation\secondary_extend($view));
            $node = $view->find('local_oksigeniatesape', \navigation_node::TYPE_CUSTOM);
            return $node ? $node->action->out_as_local_url(false) : null;
        };
        // Teachers always find the panel, to turn the game on; students only once it is on.
        $this->assertStringStartsWith('/local/oksigeniatesape/teach.php', $link($teacher));
        $this->assertNull($link($student));
        regions::save($course->id, ['enabled' => 1]);
        $this->assertStringStartsWith('/local/oksigeniatesape/play.php', $link($student));
    }
}
