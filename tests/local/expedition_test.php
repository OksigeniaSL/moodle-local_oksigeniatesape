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
 * Tests for the strength of the expedition: the lanterns, the stains cleaned and the tasks of the places weaken the
 * Embrollo before the final battle.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\expedition
 */
#[\PHPUnit\Framework\Attributes\CoversClass(expedition::class)]
final class expedition_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1800000000;

    public function test_what_the_class_did_weakens_the_embrollo_of_the_final_battle(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $users = [];
        foreach ([1, 2] as $i) {
            $users[$i] = $gen->create_and_enrol($course, 'student');
            engine::member(characters::create($users[$i]->id, 'sage', 'f'), $course->id, self::NOW);
        }
        $this->assertSame(0.0, expedition::strength($course->id)['strength']);
        // Three lanterns of six: 0.3.
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder');
        foreach (array_slice($chapters, 0, 3) as $chapter) {
            $DB->set_field(self::T . 'chapter', 'litat', self::NOW, ['id' => $chapter->id]);
        }
        $x = expedition::strength($course->id);
        $this->assertSame([3, 6], $x['lanterns']);
        $this->assertEquals(0.3, $x['strength']);
        // Six stains cleaned: 0.2 more; one task of a place done by half of the class: a sixth of 0.2.
        foreach (range(1, 6) as $day) {
            $DB->insert_record(self::T . 'stain', (object) ['courseid' => $course->id, 'day' => 20261000 + $day,
                'chapter' => 'claro', 'game' => 'g', 'sheet' => 's', 'kind' => 'stain', 'cleanedat' => self::NOW,
                'timecreated' => self::NOW]);
        }
        $task = $DB->get_records_select(self::T . 'challenge', 'courseid = ? AND chapterid > 0', [$course->id], 'id', '*', 0, 1);
        classroom::validate((int) reset($task)->id, [$users[1]->id], 'done', null, 0, self::NOW);
        $x = expedition::strength($course->id);
        $this->assertSame(6, $x['stains']);
        $this->assertSame(1, $x['tasks'][0]);
        $this->assertEquals(0.53, $x['strength']);
        $this->assertSame(16, $x['weaker']);
        // The final battle starts with the Embrollo that much weaker; another battle, as it was.
        foreach ([true, false] as $final) {
            $live = live::start($course->id, 'phones', 4, 30, self::NOW + ($final ? 0 : 10), $final);
            foreach ($users as $user) {
                live::join($live, engine::member(characters::get($user->id), $course->id), $user->id, self::NOW);
            }
            $state = json_decode(live::next($live, self::NOW + 1)->state, true);
            $this->assertSame($final ? max(1, (int) round($state['maxhp'] * 0.84)) : $state['maxhp'], $state['hp']);
            live::finish($live, 0, self::NOW + 2);
        }
    }

    public function test_the_embrollo_keeps_for_the_end_the_knots_the_class_mixes_up(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $this->assertSame([], expedition::known($course->id));
        // What the class answered in the scenes on the objective of the knot of the heron (SV-06).
        $attempt = fn(int $ok, int $total, int $when) => $DB->insert_record(self::T . 'sceneattempt', (object) [
            'sceneid' => 1, 'charid' => 1, 'courseid' => $course->id, 'score' => 0, 'attempt' => 1,
            'objectives' => json_encode(['SV-06' => ['ok' => $ok, 'total' => $total]]),
            'timestarted' => $when, 'timefinished' => $when]);
        $attempt(1, 2, self::NOW);
        $this->assertSame([], expedition::known($course->id), 'Two answers do not tell');
        $attempt(2, 6, self::NOW + 1);
        $known = expedition::known($course->id);
        $this->assertSame(['garza'], array_column($known, 'code'));
        $this->assertNotSame('', $known[0]['name']);
        // The final battle by turns keeps it for its last turn; another battle does not.
        $live = live::start($course->id, 'phones', 4, 30, self::NOW + 2, true, true);
        $state = json_decode($live->state, true);
        $this->assertSame(['garza'], $state['known']);
        $this->assertSame('garza', end($state['knots']));
        $this->assertSame(1, count(array_keys($state['knots'], 'garza', true)));
        live::finish($live, 0, self::NOW + 2);
        $practice = live::start($course->id, 'phones', 4, 30, self::NOW + 3, false, true);
        $this->assertSame([], json_decode($practice->state, true)['known']);
        // Broken in a practice battle, the Embrollo does not know it any more...
        $state = json_decode($practice->state, true);
        $state['broken'] = ['garza'];
        $state['hp'] = 5;
        $state['rounds'][1] = ['revealed' => true];
        $DB->update_record(self::T . 'live', (object) ['id' => $practice->id, 'state' => json_encode($state), 'round' => 1]);
        live::finish($DB->get_record(self::T . 'live', ['id' => $practice->id]), 0, self::NOW + 4);
        $this->assertSame([], expedition::known($course->id));
        // ... until the class misses it again.
        $attempt(0, 1, self::NOW + 5);
        $this->assertSame(['garza'], array_column(expedition::known($course->id), 'code'));
    }
}
