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
 * Tests for the little knots: the fight of the creature of a student with a minion on the road.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\fights
 */
#[\PHPUnit\Framework\Attributes\CoversClass(fights::class)]
final class fights_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment, at midday. */
    private const NOW = 1800014400;

    /** @var int The course. */
    private int $courseid;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /**
     * A student of the course, with their creature at a stage.
     *
     * @param int $stage
     * @return array [char, member]
     */
    private function student(int $stage): array {
        global $DB;
        $user = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $char = characters::create($user->id, 'scout', 'f');
        $DB->set_field(self::T . 'char', 'creaturestage', $stage, ['id' => $char->id]);
        $char = characters::get($user->id);
        return [$char, engine::member($char, $this->courseid, self::NOW)];
    }

    /**
     * The net of the minion and a wrong one not struck out; the right option and a wrong one.
     *
     * @param \stdClass $fight
     * @return array [goodnet, badnet, right, wrong]
     */
    private function answers(\stdClass $fight): array {
        global $DB;
        $fight = $DB->get_record(self::T . 'fight', ['id' => $fight->id]);
        $s = json_decode($fight->state, true);
        $good = (int) array_search($fight->minion, $s['nets'], true);
        $bad = -1;
        foreach ($s['nets'] as $i => $net) {
            if ($i !== $good && !in_array($i, $s['struck'], true)) {
                $bad = $i;
            }
        }
        $q = questions::get($this->courseid, $s['questions'][$s['turn']], $s['seed'] + $s['turn']);
        return [$good, $bad, (int) $q['correct'], ((int) $q['correct'] + 1) % count($q['options'])];
    }

    /**
     * Where the thread falls now, and a safe place.
     *
     * @param \stdClass $fight
     * @return array [aim, safe]
     */
    private function holes(\stdClass $fight): array {
        global $DB;
        $aim = (int) json_decode($DB->get_field(self::T . 'fight', 'state', ['id' => $fight->id]), true)['aim'];
        return [$aim, ($aim + 1) % fights::HOLES];
    }

    public function test_the_creature_fights_the_minion_on_the_road(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->course = $course;
        $this->courseid = (int) $course->id;
        regions::save($this->courseid, ['enabled' => 1]);
        missions::install($this->courseid, 'livingthings', 'valley', 'es', self::NOW);
        $mission = missions::get($this->courseid);
        // A young creature: no moves yet.
        [$char, $member] = $this->student(1);
        $road = minions::on_road($char, $mission, $mission->data['chapters'][0]['code'], self::NOW);
        $code = $road['code'];
        $fight = fights::start($char, $member, $code, self::NOW);
        $view = fights::view($fight);
        $this->assertSame(['net', 3, 3, []], [$view['phase'], $view['threads'], $view['bonds'], $view['moves']]);
        $this->assertContains(knots::get($mission, $code)['weakness'], array_column($view['nets'], 'text'));
        try {
            fights::act($char, $member, $fight->id, 'sniff', 0, self::NOW);
            $this->fail('A young creature does not sniff');
        } catch (\moodle_exception $e) {
            $this->assertSame('fightnotnow', $e->errorcode);
        }
        // Going on later: the same fight, not a new one.
        $this->assertSame((int) $fight->id, (int) fights::start($char, $member, $code, self::NOW + 5)->id);
        $road = minions::on_road($char, $mission, $mission->data['chapters'][0]['code'], self::NOW + 5);
        $this->assertSame([(int) $fight->id, false], [$road['fight'], $road['tried']]);
        // Turn 1: the right net and the right answer cut two; the thread dodged.
        [$good, , $right] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $good, self::NOW + 10);
        $fight = fights::act($char, $member, $fight->id, 'strike', $right, self::NOW + 12);
        $view = fights::view($fight);
        $this->assertSame(['dodge', 1, 'hit', 2], [$view['phase'], $view['threads'], $view['last']['kind'], $view['last']['cut']]);
        [, $safe] = $this->holes($fight);
        $view = fights::view(fights::act($char, $member, $fight->id, 'dodge', $safe, self::NOW + 14));
        $this->assertSame(['net', 1, 3, 'dodged'], [$view['phase'], $view['turn'], $view['bonds'], $view['last']['kind']]);
        // Turn 2: another net, right answer: one thread, the last one. Untangled, with its gem.
        $gems = (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]);
        [, $bad, $right] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $bad, self::NOW + 20);
        $view = fights::view(fights::act($char, $member, $fight->id, 'strike', $right, self::NOW + 22));
        $this->assertSame(['end', 'won', 'weak', 1], [$view['phase'], $view['outcome'], $view['last']['kind'], $view['gems']]);
        $this->assertNotSame('', $view['weakness']);
        $this->assertSame($gems + 1, (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]));
        $this->assertTrue($DB->record_exists(self::T . 'found', ['charid' => $char->id, 'kind' => 'untangled', 'code' => $code]));
        try {
            fights::start($char, $member, $code, self::NOW + 30);
            $this->fail('Untangled, it is no longer on the road');
        } catch (\moodle_exception $e) {
            $this->assertSame('nominion', $e->errorcode);
        }

        // Stage 4: it sniffs a wrong net, and the double strike untangles it at once.
        [$char, $member] = $this->student(4);
        $fight = fights::start($char, $member, $code, self::NOW);
        $this->assertSame(['sniff', 'leap', 'double'], fights::view($fight)['moves']);
        $view = fights::view(fights::act($char, $member, $fight->id, 'sniff', 0, self::NOW + 2));
        $this->assertCount(1, array_filter(array_column($view['nets'], 'struck')));
        $this->assertFalse($view['cansniff']);
        [$good, , $right] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $good, self::NOW + 4);
        $view = fights::view(fights::act($char, $member, $fight->id, 'strike', $right, self::NOW + 6));
        $this->assertSame(['won', 3, 0], [$view['outcome'], $view['last']['cut'], $view['threads']]);

        // Stage 3: wrong answers; the creature leaps from the first thread, the second catches it, and a tangled
        // creature cuts one at most. Three turns, and the minion escapes until tomorrow.
        [$char, $member] = $this->student(3);
        $fight = fights::start($char, $member, $code, self::NOW);
        [$good, , , $wrong] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $good, self::NOW + 2);
        $view = fights::view(fights::act($char, $member, $fight->id, 'strike', $wrong, self::NOW + 4));
        $this->assertSame(['miss', 3], [$view['last']['kind'], $view['threads']]);
        [$aim] = $this->holes($fight);
        $view = fights::view(fights::act($char, $member, $fight->id, 'dodge', $aim, self::NOW + 6));
        $this->assertSame(['leapt', 3], [$view['last']['kind'], $view['bonds']]);
        [$good, , , $wrong] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $good, self::NOW + 10);
        fights::act($char, $member, $fight->id, 'strike', $wrong, self::NOW + 12);
        // Too late, even on a safe place.
        [, $safe] = $this->holes($fight);
        $view = fights::view(fights::act($char, $member, $fight->id, 'dodge', $safe, self::NOW + 12 + fights::DODGE + fights::MARGIN + 1));
        $this->assertSame(['caught', 2, true], [$view['last']['kind'], $view['bonds'], $view['tangled']]);
        [$good, , $right] = $this->answers($fight);
        fights::act($char, $member, $fight->id, 'net', $good, self::NOW + 30);
        $view = fights::view(fights::act($char, $member, $fight->id, 'strike', $right, self::NOW + 32));
        $this->assertSame(['weak', 1, 2], [$view['last']['kind'], $view['last']['cut'], $view['threads']]);
        [, $safe] = $this->holes($fight);
        $view = fights::view(fights::act($char, $member, $fight->id, 'dodge', $safe, self::NOW + 34));
        $this->assertSame(['end', 'escaped', 0], [$view['phase'], $view['outcome'], $view['gems']]);
        $road = minions::on_road($char, $mission, $mission->data['chapters'][0]['code'], self::NOW + 40);
        $this->assertSame([0, true], [$road['fight'], $road['tried']]);
        // The next day, it is back.
        $this->assertNotSame((int) $fight->id, (int) fights::start($char, $member, $code, self::NOW + DAYSECS)->id);
    }
}
