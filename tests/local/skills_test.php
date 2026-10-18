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
 * Tests for the named skills: lit by the scenes when the answers came well, or by a rating the teacher gives proficient;
 * for the student and their teachers; off where the course turns them off.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\skills
 */
#[\PHPUnit\Framework\Attributes\CoversClass(skills::class)]
final class skills_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A play of a scene at its first try, with how the answers of each objective came.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param int $sceneid
     * @param array $objectives
     * @param int $attempt
     */
    private function play(int $courseid, \stdClass $char, int $sceneid, array $objectives, int $attempt = 1): void {
        global $DB;
        $DB->insert_record(self::T . 'sceneattempt', (object) ['sceneid' => $sceneid, 'charid' => $char->id,
            'courseid' => $courseid, 'teamplay' => 0, 'score' => 0, 'attempt' => $attempt, 'timestarted' => time(),
            'timefinished' => time(), 'objectives' => json_encode($objectives)]);
    }

    public function test_the_scenes_and_the_teacher_light_what_the_hero_has_learnt(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        $member = engine::member($char, $course->id);
        // Each objective with its skill, dark to begin with.
        $skills = array_column(skills::of($char, $course->id), null, 'code');
        $this->assertCount(8, $skills);
        $this->assertSame('Mirar dentro de una célula', $skills['SV-02']['name']);
        $this->assertSame([], array_filter(array_column($skills, 'lit')));
        // Three answers right alone at the first try light it; two do not, nor three after the consequence.
        $this->play($course->id, $char, 1, ['SV-01' => ['ok' => 3, 'total' => 3, 'first' => 3],
            'SV-02' => ['ok' => 2, 'total' => 2, 'first' => 2], 'SV-05' => ['ok' => 3, 'total' => 3, 'after' => 3]]);
        // A later try of the same scene does not count.
        $this->play($course->id, $char, 1, ['SV-02' => ['ok' => 5, 'total' => 5, 'first' => 5]], 2);
        $lit = array_column(skills::of($char, $course->id), 'lit', 'code');
        $this->assertTrue($lit['SV-01']);
        $this->assertFalse($lit['SV-02']);
        $this->assertFalse($lit['SV-05']);
        // The teacher rates them proficient: it lights too.
        $this->setAdminUser();
        competencies::rate($course->id, $user->id, 'SV-03', 4);
        $this->assertTrue(array_column(skills::of($char, $course->id), 'lit', 'code')['SV-03']);
        // On their sheet, for them; the class screen, never.
        $this->setUser($user);
        $world = view::world($course->id, $user->id);
        $this->assertCount(8, $world['character']['skills']);
        $character = new \ReflectionMethod(view::class, 'character');
        $this->assertSame([], $character->invoke(null, $char, $member, false)['skills']);
        // Off in the course: not there.
        regions::save($course->id, ['rules' => ['skills' => 0]]);
        $this->assertSame([], skills::of($char, $course->id));
    }
}
