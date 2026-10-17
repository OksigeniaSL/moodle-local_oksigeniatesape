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

use core_ai\aiactions\responses\response_generate_text;

/**
 * Tests for the plan for the next class made by the AI of the site.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\nextclass
 */
#[\PHPUnit\Framework\Attributes\CoversClass(nextclass::class)]
final class nextclass_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string A plan with the shape asked for. */
    private const PLAN = '{"summary": "Confunden lo que se mueve con lo que está vivo.", "review": [{"point": "Las tres ' .
        'funciones vitales", "how": "Tres carteles en la pizarra."}], "example": "La seta del parque.", "opener": ' .
        '"¿Está vivo el fuego?", "challenge": %d, "challengewhy": "Sale al patio a buscarlos."}';

    /**
     * A course with the sample mission, its classroom challenges and a student who mixed things up in the first scene.
     *
     * @return \stdClass course, user, teacher.
     */
    private function make_class(): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $mission = missions::install($w->course->id, 'livingthings', 'valley', 'py');
        $w->user = $dg->create_and_enrol($w->course, 'student', ['firstname' => 'Zacarías', 'lastname' => 'Quiroga']);
        $w->teacher = $dg->create_and_enrol($w->course, 'editingteacher');
        characters::create($w->user->id, 'sage', 'm');
        engine::member(characters::get($w->user->id), $w->course->id);
        $scene = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => 'C1-2']);
        $events = [
            ['n' => 'antes', 'pick' => ['buscar']],
            ['n' => 'elegir', 'pick' => ['seta', 'cristal', 'humo', 'pompa', 'piedra']],
            ['n' => 'rana', 'pick' => ['charca']],
        ];
        scenes::play($w->course->id, $w->user->id, $scene->id, $events, null);
        return $w;
    }

    public function test_the_context_has_counts_and_no_names(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $context = nextclass::context($w->course->id);
        $this->assertSame('Paraguay', $context['place']);
        $this->assertArrayHasKey('SV-01', $context['objectives']);
        $this->assertSame('¿Qué cinco cosas llevamos al nido?', $context['mixups'][0]['question']);
        $this->assertNotEmpty($context['challenges']);
        $prompt = nextclass::prompt($context, 'es');
        $this->assertStringContainsString('in Spanish', $prompt);
        $this->assertStringContainsString('SV-01', $prompt);
        $this->assertStringNotContainsString('Zacarías', $prompt);
        $this->assertStringNotContainsString('Quiroga', $prompt);
        // The editorial line goes to the AI too.
        $this->assertStringContainsString('never climate change', $prompt);
        $this->assertStringContainsString('never framed by their sex', $prompt);
        // And the brand: the whole class, with «ustedes».
        $this->assertStringContainsString('«ustedes» and never «vosotros»', $prompt);
    }

    public function test_nothing_to_go_on_before_anybody_plays(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        missions::install($course->id, 'livingthings', 'valley', 'es');
        $this->assertNull(nextclass::context($course->id));
    }

    public function test_the_answer_is_read_and_checked(): void {
        $plan = nextclass::parse("Aquí tienes el plan:\n```json\n" . sprintf(self::PLAN, 7) . "\n```", [7, 8]);
        $this->assertSame('¿Está vivo el fuego?', $plan['opener']);
        $this->assertSame(7, $plan['challenge']);
        $this->assertCount(1, $plan['review']);
        // A challenge that is not in the course is dropped, with its reason.
        $plan = nextclass::parse(sprintf(self::PLAN, 99), [7]);
        $this->assertSame(0, $plan['challenge']);
        $this->assertSame('', $plan['challengewhy']);
        // Without what to review or the question, it is no plan.
        $this->assertNull(nextclass::parse('{"summary": "Algo", "review": [], "opener": "¿Y?"}', []));
        $this->assertNull(nextclass::parse('No sé.', []));
    }

    public function test_without_ai_on_the_site_it_says_so(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $this->assertFalse(nextclass::status($w->course->id, $w->teacher->id)['ai']);
        $this->expectException(\moodle_exception::class);
        nextclass::generate($w->course->id, $w->teacher->id);
    }

    public function test_the_plan_is_asked_again_once_and_kept(): void {
        global $DB;
        if ((new \ReflectionMethod(\core_ai\manager::class, 'is_action_available'))->isStatic()) {
            $this->markTestSkipped('The AI manager of Moodle 4.5 cannot be replaced in a test.');
        }
        $this->resetAfterTest();
        $w = $this->make_class();
        $where = "courseid = ? AND kind = 'classroom'";
        $challenge = (int) $DB->get_field_select(self::T . 'challenge', 'MIN(id)', $where, [$w->course->id]);
        $answers = [];
        foreach (['Lo siento, no puedo.', sprintf(self::PLAN, $challenge)] as $text) {
            $response = new response_generate_text(true);
            $response->set_response_data(['generatedcontent' => $text]);
            $answers[] = $response;
        }
        $methods = ['is_action_available', 'process_action'];
        if (method_exists(\core_ai\manager::class, 'is_action_enabled_in_context')) {
            $methods[] = 'is_action_enabled_in_context';
        }
        $manager = $this->getMockBuilder(\core_ai\manager::class)->disableOriginalConstructor()->onlyMethods($methods)
            ->getMock();
        $manager->method('is_action_available')->willReturn(true);
        if (in_array('is_action_enabled_in_context', $methods, true)) {
            $manager->method('is_action_enabled_in_context')->willReturn(true);
        }
        $manager->expects($this->exactly(2))->method('process_action')->willReturnOnConsecutiveCalls(...$answers);
        \core\di::set(\core_ai\manager::class, $manager);

        // First the policy of the site.
        try {
            nextclass::generate($w->course->id, $w->teacher->id);
            $this->fail('Without the policy accepted there is no plan');
        } catch (\moodle_exception $e) {
            $this->assertSame('nextclass_policy', $e->errorcode);
        }
        nextclass::accept_policy($w->course->id, $w->teacher->id);
        $plan = nextclass::generate($w->course->id, $w->teacher->id);
        $this->assertSame('¿Está vivo el fuego?', $plan['opener']);
        $this->assertSame($challenge, $plan['challenge']);
        $this->assertNotSame('', $plan['challengename']);
        $this->assertSame(1, $DB->count_records(self::T . 'nextclass', ['courseid' => $w->course->id]));
        // A minute later the same plan is still good: the AI is not asked again.
        $this->assertSame($plan, nextclass::generate($w->course->id, $w->teacher->id));
        $this->assertSame('¿Está vivo el fuego?', nextclass::opener($w->course->id, time()));
        $this->assertSame('', nextclass::opener($w->course->id, time() + (nextclass::OPENER_DAYS + 1) * DAYSECS));
    }

    public function test_whoever_may_not_use_the_ai_still_sees_the_last_plan(): void {
        global $DB;
        if ((new \ReflectionMethod(\core_ai\manager::class, 'is_action_available'))->isStatic()) {
            $this->markTestSkipped('The AI manager of Moodle 4.5 cannot be replaced in a test.');
        }
        $this->resetAfterTest();
        $w = $this->make_class();
        $methods = ['is_action_available'];
        if (method_exists(\core_ai\manager::class, 'is_action_enabled_in_context')) {
            $methods[] = 'is_action_enabled_in_context';
        }
        $manager = $this->getMockBuilder(\core_ai\manager::class)->disableOriginalConstructor()->onlyMethods($methods)
            ->getMock();
        $manager->method('is_action_available')->willReturn(true);
        if (in_array('is_action_enabled_in_context', $methods, true)) {
            $manager->method('is_action_enabled_in_context')->willReturn(true);
        }
        \core\di::set(\core_ai\manager::class, $manager);
        // A public account of a demo site: it may not accept the policy of the AI.
        $context = \context_course::instance($w->course->id);
        $role = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('moodle/ai:acceptpolicy', CAP_PROHIBIT, $role, $context->id);
        $status = nextclass::status($w->course->id, $w->teacher->id);
        $this->assertTrue($status['ai']);
        $this->assertFalse($status['canuse']);
        // The plan someone made before, frozen in the demo, is still shown, with nothing to accept.
        $plan = ['summary' => 'Setas y plantas', 'review' => [['point' => 'Las setas', 'how' => 'Mirar una']],
            'example' => '', 'opener' => '¿Está viva una seta?', 'challenge' => 0, 'challengewhy' => ''];
        $DB->insert_record(self::T . 'nextclass', (object) ['courseid' => $w->course->id, 'data' => json_encode($plan),
            'timecreated' => time()]);
        $this->setUser($w->teacher);
        $result = \local_oksigeniatesape\external\next_class::execute($w->course->id, 'state');
        $this->assertFalse($result['canuse']);
        $this->assertSame('', $result['policytext']);
        $this->assertSame('¿Está viva una seta?', $result['plan']['opener']);
        // Nor can it accept the policy by calling the web service itself.
        $this->expectException(\required_capability_exception::class);
        \local_oksigeniatesape\external\next_class::execute($w->course->id, 'accept');
    }

    public function test_a_provider_that_cannot_be_reached_gives_a_plain_message(): void {
        if ((new \ReflectionMethod(\core_ai\manager::class, 'is_action_available'))->isStatic()) {
            $this->markTestSkipped('The AI manager of Moodle 4.5 cannot be replaced in a test.');
        }
        $this->resetAfterTest();
        $w = $this->make_class();
        $methods = ['is_action_available', 'process_action'];
        if (method_exists(\core_ai\manager::class, 'is_action_enabled_in_context')) {
            $methods[] = 'is_action_enabled_in_context';
        }
        $manager = $this->getMockBuilder(\core_ai\manager::class)->disableOriginalConstructor()->onlyMethods($methods)
            ->getMock();
        $manager->method('is_action_available')->willReturn(true);
        if (in_array('is_action_enabled_in_context', $methods, true)) {
            $manager->method('is_action_enabled_in_context')->willReturn(true);
        }
        $manager->method('process_action')->willThrowException(new \RuntimeException('cURL error 6: Could not resolve host'));
        \core\di::set(\core_ai\manager::class, $manager);
        nextclass::accept_policy($w->course->id, $w->teacher->id);
        try {
            nextclass::generate($w->course->id, $w->teacher->id);
            $this->fail('A provider that cannot be reached makes no plan');
        } catch (\moodle_exception $e) {
            $this->assertSame('nextclass_failed', $e->errorcode);
            $this->assertStringContainsString('cURL', $e->debuginfo);
        }
    }
}
