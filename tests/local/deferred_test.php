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
 * Tests for the turn in deferred, for whoever follows from far.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\deferred
 */
#[\PHPUnit\Framework\Attributes\CoversClass(deferred::class)]
final class deferred_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_whoever_follows_from_far_plays_the_battle_in_deferred(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es');
        $team = $gen->create_group(['courseid' => $c]);
        $users = [];
        $members = [];
        foreach (['f', 'm'] as $i => $form) {
            $users[$i] = $gen->create_and_enrol($course, 'student', ['firstname' => $i ? 'Mateo' : 'Lucía']);
            $gen->create_group_member(['groupid' => $team->id, 'userid' => $users[$i]->id]);
            $members[$i] = engine::member(characters::create($users[$i]->id, 'scout', $form), $c);
        }
        // Lucía follows from far; Mateo was in the battle, which the class lost.
        $DB->set_field(self::T . 'member', 'remote', 1, ['id' => $members[0]->id]);
        $live = live::start($c, 'phones', 4, 30, time() - 600, false, true);
        live::join($live, $members[1], $users[1]->id, time() - 600);
        $state = json_decode($live->state, true);
        $state['outcome'] = 'lost';
        $DB->update_record(self::T . 'live', (object) ['id' => $live->id, 'state' => json_encode($state), 'timeend' => time() - 300]);
        $DB->set_field(self::T . 'region', 'battlestate', json_encode(['left' => 0.5, 'broken' => [], 'hanging' => [],
            'final' => false]), ['courseid' => $c]);
        regions::forget();
        $member = engine::member(characters::get($users[0]->id), $c);
        $pending = deferred::pending($c, characters::get($users[0]->id), $member);
        $this->assertSame((int) $live->id, $pending['liveid']);
        $this->assertSame('lost', $pending['outcome']);
        $this->assertCount(deferred::QUESTIONS, $pending['questions']);
        $this->assertArrayNotHasKey('correct', $pending['questions'][0]);
        // Who was in the battle, or does not follow from far, has none.
        $this->assertNull(deferred::pending($c, characters::get($users[1]->id), engine::member(characters::get($users[1]->id), $c)));
        // Three right and one wrong: gems, threads off the Embrollo for the rematch, and once.
        $answers = [];
        foreach ($pending['questions'] as $i => $q) {
            $correct = questions::get($c, $q['id'], (int) $live->id)['correct'];
            $answers[] = $i < 3 ? $correct : ($correct + 1) % count($q['options']);
        }
        $result = deferred::play($c, $users[0]->id, (int) $live->id, $answers);
        $this->assertSame(['right' => 3, 'total' => 4, 'gems' => 3, 'helped' => true], $result);
        regions::forget();
        $left = json_decode((string) regions::get($c)->battlestate, true)['left'];
        $this->assertEqualsWithDelta(0.5 - 3 * deferred::THREADS, $left, 0.0001);
        $this->assertNull(deferred::pending($c, characters::get($users[0]->id), $member));
        try {
            deferred::play($c, $users[0]->id, (int) $live->id, $answers);
            $this->fail('Twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('nodeferred', $e->errorcode);
        }
        // The class screen says from whom the strength came.
        $this->assertSame([['name' => 'Lucía', 'right' => 3]], deferred::from_far($c));
    }
}
