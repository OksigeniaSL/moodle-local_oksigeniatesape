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
 * Tests for the board as a game board: the fog of the Embrollo and the tokens of the teams.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\fog
 */
#[\PHPUnit\Framework\Attributes\CoversClass(fog::class)]
final class fog_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    /**
     * A try of a scene of a place, with how many answers were right.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @param \stdClass $scene
     * @param int $ok
     * @param int $total
     * @param int $at
     */
    private function attempt(\stdClass $char, int $courseid, \stdClass $scene, int $ok, int $total, int $at): void {
        global $DB;
        $DB->insert_record(self::T . 'sceneattempt', (object) ['sceneid' => $scene->id, 'charid' => $char->id,
            'courseid' => $courseid, 'teamplay' => 0, 'path' => '{}', 'score' => $ok,
            'objectives' => json_encode(['SV-01' => ['ok' => $ok, 'total' => $total]]), 'ending' => 'fin', 'attempt' => 1,
            'timestarted' => $at, 'timefinished' => $at]);
    }

    public function test_the_fog_covers_what_the_class_forgot_and_the_tokens_of_the_teams(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es', self::NOW);
        $mission = missions::get($c);
        $team = $gen->create_group(['courseid' => $c, 'name' => 'Garzas']);
        $chars = [];
        foreach (['f', 'm'] as $form) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $team->id, 'userid' => $user->id]);
            $chars[] = characters::create($user->id, 'scout', $form);
            engine::member(end($chars), $c, self::NOW);
        }
        $scenes = [];
        foreach (['C1-2', 'C2-2', 'C3-1', 'C4-1'] as $code) {
            $scenes[$code] = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => $code]);
        }
        // The class lit the clearing and is in the library: in the clearing it got little right; in the library, a lot.
        $DB->set_field(self::T . 'chapter', 'litat', self::NOW, ['missionid' => $mission->id, 'code' => 'claro']);
        foreach ($chars as $char) {
            $this->attempt($char, $c, $scenes['C1-2'], 1, 4, self::NOW);
            $this->attempt($char, $c, $scenes['C2-2'], 4, 4, self::NOW + 10);
        }
        $this->assertSame(['claro'], fog::places($c));
        // The team is where it played last.
        $this->assertSame([['teamid' => (int) $team->id, 'team' => 'Garzas', 'place' => 'biblioteca']], fog::tokens($c));
        // Played again and right: the fog goes away by itself.
        foreach ($chars as $char) {
            $this->attempt($char, $c, $scenes['C1-2'], 4, 4, self::NOW + 20);
        }
        $this->assertSame([], fog::places($c));
        // A place not opened yet is never in the fog, even with wrong answers.
        $this->attempt($chars[0], $c, $scenes['C4-1'], 0, 5, self::NOW + 30);
        $this->assertSame([], fog::places($c));
        // With the fog over three places, a battle by turns has a knot more.
        foreach (['claro', 'biblioteca', 'bosque'] as $code) {
            $DB->set_field(self::T . 'chapter', 'litat', self::NOW, ['missionid' => $mission->id, 'code' => $code]);
        }
        foreach ($chars as $char) {
            $this->attempt($char, $c, $scenes['C1-2'], 0, 4, self::NOW + 40);
            $this->attempt($char, $c, $scenes['C2-2'], 0, 4, self::NOW + 40);
            $this->attempt($char, $c, $scenes['C3-1'], 0, 4, self::NOW + 40);
        }
        // The garden is the place of today now: its wrong try counts too.
        $this->assertSame(['claro', 'biblioteca', 'bosque', 'jardin'], fog::places($c));
        $live = live::start($c, 'phones', 4, 30, self::NOW + 50, false, true);
        $state = json_decode($live->state, true);
        $this->assertTrue($state['grown']);
        $this->assertCount(5, $state['knots']);
    }
}
