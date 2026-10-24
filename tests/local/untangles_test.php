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
 * Tests for the great untangling: the class against a knot with a strand for each team, with the phones.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\untangles
 */
#[\PHPUnit\Framework\Attributes\CoversClass(untangles::class)]
final class untangles_test extends \advanced_testcase {
    /** @var int A fixed moment. */
    private const NOW = 1800014400;

    /**
     * The right option of the micro-challenge a student sees now.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @return array [number, right, wrong]
     */
    private function challenge(\stdClass $live, \stdClass $member): array {
        global $DB;
        $live = $DB->get_record('local_oksigeniatesape_live', ['id' => $live->id]);
        $view = untangles::device($live, $member, self::NOW);
        $item = new \ReflectionMethod(untangles::class, 'item');
        $right = (int) $item->invoke(null, $live, (int) $member->charid, $view['number'])['right'];
        return [$view['number'], $right, ($right + 1) % count($view['item']['options'])];
    }

    public function test_the_class_untangles_the_great_knot(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $c = (int) $course->id;
        regions::save($c, ['enabled' => 1]);
        missions::install($c, 'livingthings', 'valley', 'es', self::NOW);
        $members = [];
        foreach (['Garza' => 2, 'Jaguar' => 1, 'Lince' => 1] as $name => $count) {
            $group = $gen->create_group(['courseid' => $c, 'name' => $name]);
            for ($i = 0; $i < $count; $i++) {
                $user = $gen->create_and_enrol($course, 'student');
                $gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
                $char = characters::create($user->id, 'scout', 'f');
                $members[$name][] = [$user, engine::member($char, $c, self::NOW)];
            }
        }
        $live = untangles::start($c, self::NOW);
        $this->assertSame(['untangle', 'lobby'], [$live->kind, untangles::board($live, self::NOW)['phase']]);
        $this->assertSame((int) $live->id, (int) live::current($c, self::NOW)->id);
        try {
            untangles::go($live, self::NOW);
            $this->fail('Nobody in');
        } catch (\moodle_exception $e) {
            $this->assertSame('untanglenobody', $e->errorcode);
        }
        // The Garza and the Jaguar come in; the Lince does not.
        foreach (['Garza', 'Jaguar'] as $name) {
            foreach ($members[$name] as [$user, $member]) {
                live::join($live, $member, $user->id, self::NOW + 1);
            }
        }
        $live = untangles::go($live, self::NOW + 10);
        $board = untangles::board($live, self::NOW + 10);
        $this->assertSame(['play', untangles::SECS], [$board['phase'], $board['left']]);
        $this->assertSame(['Garza', 'Jaguar'], array_column($board['strands'], 'name'));
        // A view of a battle still works on it.
        $this->assertSame('untangle', live::board_view($live, self::NOW + 10)['kind']);
        // The Garza loosen their strand: six right answers between the two.
        [$ana, $leo] = [$members['Garza'][0][1], $members['Garza'][1][1]];
        for ($i = 0; $i < untangles::STRAND; $i++) {
            $who = $i % 2 ? $leo : $ana;
            [$number, $right] = $this->challenge($live, $who);
            $live = untangles::answer($live, $who, $number, $right, self::NOW + 20 + $i);
        }
        $view = untangles::device($live, $ana, self::NOW + 30);
        $this->assertSame([0, 'Jaguar'], [$view['mine'], $view['helping']]);
        // Twice the same micro-challenge, no.
        try {
            untangles::answer($live, $ana, $view['number'] - 1, 0, self::NOW + 31);
            $this->fail('Answered already');
        } catch (\moodle_exception $e) {
            $this->assertSame('untanglenot', $e->errorcode);
        }
        // A wrong answer of the Jaguar tightens nothing beyond the start; then everybody helps the Jaguar.
        $eva = $members['Jaguar'][0][1];
        [$number, , $wrong] = $this->challenge($live, $eva);
        $live = untangles::answer($live, $eva, $number, $wrong, self::NOW + 32);
        $this->assertSame(untangles::STRAND, untangles::board($live, self::NOW + 32)['strands'][1]['knots']);
        for ($i = 0; $i < untangles::STRAND; $i++) {
            $who = [$ana, $leo, $eva][$i % 3];
            [$number, $right] = $this->challenge($live, $who);
            $live = untangles::answer($live, $who, $number, $right, self::NOW + 40 + $i);
        }
        $board = untangles::board($live, self::NOW + 50);
        $this->assertSame(['end', 'won', 2], [$board['phase'], $board['outcome'], $board['loose']]);
        $this->assertGreaterThan(0, (int) $DB->get_field('local_oksigeniatesape_live', 'timeend', ['id' => $live->id]));
        $this->assertSame([2, 2], untangles::last($c));
        // It weakens the Embrollo for the final battle: 20 points more.
        $strength = expedition::strength($c);
        $this->assertSame([2, 2], $strength['untangled']);
        $this->assertSame((int) round(100 * expedition::MOST * $strength['strength']) + 20, $strength['weaker']);
        // Another one, and the time is up with nothing loose.
        $live = untangles::start($c, self::NOW + 100);
        live::join($live, $eva, $members['Jaguar'][0][0]->id, self::NOW + 101);
        $live = untangles::go($live, self::NOW + 110);
        $live = untangles::tick($live, self::NOW + 110 + untangles::SECS);
        $this->assertSame(['end', 'time'], [untangles::board($live, self::NOW)['phase'], untangles::board($live, self::NOW)['outcome']]);
        $this->assertSame([0, 1], untangles::last($c));
    }
}
