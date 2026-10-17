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
 * Tests for the report of a class.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\report
 */
#[\PHPUnit\Framework\Attributes\CoversClass(report::class)]
final class report_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    public function test_one_row_for_each_student_with_the_first_try_of_each_objective(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es');
        $ana = $gen->create_and_enrol($course, 'student', ['firstname' => 'Ana', 'lastname' => 'Ríos']);
        $leo = $gen->create_and_enrol($course, 'student', ['firstname' => 'Leo', 'lastname' => 'Alba']);
        $eva = $gen->create_and_enrol($course, 'student', ['firstname' => 'Eva', 'lastname' => 'Bosque']);
        foreach ([$ana, $leo] as $user) {
            engine::member(characters::create($user->id, 'sage', 'f'), $course->id);
        }
        $nest = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        // Ana gets the nest right at once; Leo puts three living things of five, and then gets it right.
        $right = [['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo', 'hormiga', 'rana']]];
        $first = [['n' => 'antes', 'pick' => ['buscar']]];
        scenes::play($course->id, $ana->id, $nest->id, array_merge($first, $right), null);
        scenes::play($course->id, $leo->id, $nest->id, array_merge($first, [['n' => 'elegir', 'pick' => ['seta', 'lombriz', 'musgo',
            'cristal', 'humo']]]), null);
        scenes::play($course->id, $leo->id, $nest->id, $right, 'elegir');
        $columns = report::columns($course->id);
        $this->assertSame(get_string('report_lastname', 'local_oksigeniatesape'), reset($columns));
        $objective = array_search('objective:SV-01', array_keys($columns), true);
        $this->assertStringContainsString('SV-01', array_values($columns)[$objective]);
        $rows = report::rows($course->id);
        $this->assertCount(3, $rows);
        $byname = array_column($rows, null, 1);
        // Alphabetical by last name; the first try counts.
        $this->assertSame(['Alba', 'Bosque', 'Ríos'], array_column($rows, 0));
        $this->assertSame(100, $byname['Ana'][$objective]);
        $this->assertSame(60, $byname['Leo'][$objective]);
        $this->assertSame(1, $byname['Leo'][array_search('scenes', array_keys($columns), true)]);
        // Who has not made a character yet has a row with their name and nothing else.
        $this->assertSame('', $byname['Eva'][$objective]);
        // Nothing about behaviour.
        foreach (['energy', 'breath', 'pacts', 'powers'] as $key) {
            $this->assertArrayNotHasKey($key, $columns);
        }
        // The PDF, laid out to be read.
        $this->assertStringStartsWith('%PDF', report::pdf($course));
    }
}
