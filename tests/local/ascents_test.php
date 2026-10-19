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
 * Tests for the rite of ascent: open with the six lanterns lit or the final battle won, once in a mission; the hero
 * stays or takes another role, keeping their level, without waiting the time a change of role waits otherwise.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\ascents
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ascents::class)]
final class ascents_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A class with its mission and a student with a character.
     *
     * @return array course, user, character, mission.
     */
    private function make_class(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        engine::member($char, $course->id);
        return [$course, $user, characters::get($user->id), missions::get($course->id)];
    }

    public function test_the_six_lanterns_open_the_rite_once_and_the_role_changes_keeping_the_level(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $char, $mission] = $this->make_class();
        $DB->set_field(self::T . 'char', 'saber', 500, ['id' => $char->id]);
        // Changed role a moment ago: out of the rite it would have to wait.
        $char = characters::change_role(characters::get($user->id), 'scout', true);
        $this->assertFalse(characters::can_change_role($char));
        $codes = array_map(fn($ch) => $ch['code'], $mission->data['chapters']);
        foreach (array_slice($codes, 0, -1) as $code) {
            $DB->insert_record(self::T . 'found', ['charid' => $char->id, 'courseid' => $course->id,
                'missionid' => $mission->id, 'kind' => 'lantern', 'code' => $code, 'timecreated' => time()]);
        }
        // Five lanterns: not yet.
        $this->assertNull(ascents::pending($char, $course->id));
        $DB->insert_record(self::T . 'found', ['charid' => $char->id, 'courseid' => $course->id, 'missionid' => $mission->id,
            'kind' => 'lantern', 'code' => end($codes), 'timecreated' => time()]);
        $this->assertSame('lanterns', ascents::pending($char, $course->id));
        $this->setUser($user);
        $this->assertSame('lanterns', view::world($course->id, $user->id)['character']['ascentpending']);
        // The scout becomes a healer, with the same saber.
        $char = ascents::ascend($char, $course->id, 'healer');
        $after = characters::get($user->id);
        $this->assertSame('healer', $after->role);
        $this->assertSame(500, (int) $after->saber);
        $this->assertNull(ascents::pending($after, $course->id));
        $this->assertSame('', view::world($course->id, $user->id)['character']['ascentpending']);
        // Once in a mission.
        try {
            ascents::ascend($after, $course->id, 'sage');
            $this->fail('A second ascent');
        } catch (\moodle_exception $e) {
            $this->assertSame('noascent', $e->errorcode);
        }
        $this->assertSame('healer', characters::get($user->id)->role);
    }

    public function test_the_final_won_opens_it_and_staying_keeps_the_role(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $char] = $this->make_class();
        $live = fn(array $state) => $DB->insert_record(self::T . 'live', (object) ['courseid' => $course->id, 'kind' => 'battle',
            'round' => 0, 'state' => json_encode($state), 'timecreated' => time(), 'timemodified' => time(), 'timeend' => time()]);
        // A battle won that is not the final, a rehearsal of the final, and a final lost: nothing.
        $live(['outcome' => 'won']);
        $live(['outcome' => 'won', 'final' => 1, 'rehearsal' => 1]);
        $live(['outcome' => 'lost', 'final' => 1]);
        $this->assertNull(ascents::pending($char, $course->id));
        // Not without being open.
        try {
            ascents::ascend($char, $course->id, 'healer');
            $this->fail('An ascent before its time');
        } catch (\moodle_exception $e) {
            $this->assertSame('noascent', $e->errorcode);
        }
        $live(['outcome' => 'won', 'final' => 1]);
        $this->assertSame('final', ascents::pending($char, $course->id));
        // Staying: the same role, and the rite is made.
        ascents::ascend($char, $course->id, 'scout');
        $this->assertSame('scout', characters::get($user->id)->role);
        $this->assertNull(ascents::pending(characters::get($user->id), $course->id));
    }
}
