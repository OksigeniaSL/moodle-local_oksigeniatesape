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
 * Tests for the campaigns of several courses: each plays its mission, and their lanterns are seen together.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\campaigns
 */
#[\PHPUnit\Framework\Attributes\CoversClass(campaigns::class)]
final class campaigns_test extends \advanced_testcase {
    public function test_several_courses_are_one_expedition(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $science = $gen->create_course(['fullname' => 'Ciencias 5A']);
        $language = $gen->create_course(['fullname' => 'Lengua 5A']);
        $other = $gen->create_course(['fullname' => 'Otro']);
        foreach ([$science, $language, $other] as $course) {
            regions::save((int) $course->id, ['enabled' => 1]);
            missions::install((int) $course->id, 'livingthings', 'valley', 'es');
        }
        $s = (int) $science->id;
        $l = (int) $language->id;
        $this->assertNull(campaigns::view($s));
        // Science makes it; Language joins with its code, written as it comes.
        $code = campaigns::create($s, '  Quinto A  ');
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code);
        campaigns::join($l, ' ' . strtolower($code) . ' ');
        // Two lanterns lit in Science, one place skipped in Language.
        $mission = missions::get($s);
        $chapters = array_values($DB->get_records('local_oksigeniatesape_chapter', ['missionid' => $mission->id], 'sortorder'));
        foreach (array_slice($chapters, 0, 2) as $chapter) {
            $DB->set_field('local_oksigeniatesape_chapter', 'litat', 100, ['id' => $chapter->id]);
        }
        $skipped = $DB->get_records('local_oksigeniatesape_chapter', ['missionid' => missions::get($l)->id], 'sortorder', '*', 0, 1);
        $DB->set_field('local_oksigeniatesape_chapter', 'skippedat', 100, ['id' => reset($skipped)->id]);
        $DB->set_field('local_oksigeniatesape_chapter', 'litat', 100, ['id' => reset($skipped)->id]);
        $places = count($chapters);
        $view = campaigns::view($s, true);
        $this->assertSame(['Quinto A', $code, 2, 2 * $places - 1], [$view['name'], $view['code'], $view['lit'], $view['total']]);
        $this->assertSame([['Ciencias 5A', 2, $places, true], ['Lengua 5A', 0, $places - 1, false]], array_map(
            fn($r) => [$r['name'], $r['lit'], $r['total'], $r['here']],
            $view['regions']
        ));
        // Students see it, without the code; the class screen too.
        $user = $gen->create_and_enrol($language, 'student');
        $this->assertSame('', view::world($l, (int) $user->id)['campaign']['code']);
        $this->assertTrue(view::world($l, (int) $user->id)['campaign']['regions'][1]['here']);
        $this->assertSame(2, view::board($l)['campaign']['lit']);
        $this->assertSame($code, view::region(regions::get($l))['campaign']['code']);
        // A wrong code.
        try {
            campaigns::join((int) $other->id, 'ZZZZZZ');
            $this->fail('A wrong code joins nothing');
        } catch (\moodle_exception $e) {
            $this->assertSame('nocampaign', $e->errorcode);
        }
        // Language leaves; Science, alone, still has it. When the course goes, so does the campaign.
        campaigns::leave($l);
        $this->assertNull(campaigns::view($l));
        $this->assertCount(1, campaigns::view($s)['regions']);
        delete_course($science, false);
        $this->assertSame(0, $DB->count_records('local_oksigeniatesape_campaign'));
    }
}
