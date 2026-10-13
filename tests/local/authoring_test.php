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
 * Tests for the missions a teacher makes from scratch.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\authoring
 */
#[\PHPUnit\Framework\Attributes\CoversClass(authoring::class)]
final class authoring_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A draft with three places, the four kinds of moment and five questions.
     *
     * @param int $courseid
     * @param string $code
     * @return array The draft kept.
     */
    private function draft(int $courseid, string $code): array {
        $o = fn($label, $correct) => ['label' => $label, 'correct' => $correct, 'feedback' => ''];
        $draft = authoring::save($courseid, $code, [
            'title' => 'Las fracciones', 'objectives' => [['name' => 'Reconocer'], ['name' => 'Comparar']],
            'places' => [['name' => 'La entrada'], ['name' => 'Las frutas'], ['name' => 'La balanza']],
        ]);
        [$p1, $p2, $p3] = array_column($draft['places'], 'code');
        [$a, $b] = array_column($draft['objectives'], 'code');
        $draft['scenes'] = [
            ['place' => $p1, 'title' => 'La mitad', 'text' => 'Una manzana partida.', 'moments' => [['type' => 'one',
                'prompt' => '¿Qué es?', 'objective' => $a, 'options' => [$o('Un medio', true), $o('Un tercio', false)]]]],
            ['place' => $p2, 'title' => 'Los cuartos', 'text' => 'Cuatro trozos.', 'moments' => [
                ['type' => 'many', 'prompt' => '¿Cuáles?', 'objective' => $a,
                    'options' => [$o('1/4', true), $o('0,25', true), $o('1/3', false)]],
                ['type' => 'classify', 'prompt' => 'Más o menos', 'objective' => $b,
                    'bins' => [['label' => 'Menos'], ['label' => 'Más']],
                    'items' => [['label' => '1/4', 'bin' => 1], ['label' => '3/4', 'bin' => 2]]]]],
            ['place' => $p3, 'title' => 'En orden', 'text' => 'La balanza.', 'moments' => [['type' => 'order',
                'prompt' => 'Ordena', 'objective' => $b, 'steps' => ['1/4', '1/2', '3/4']]]],
        ];
        for ($i = 1; $i <= 4; $i++) {
            $draft['questions'][] = ['place' => $p1, 'text' => "¿Pregunta $i?", 'options' => ['Buena', 'Mala']];
        }
        return authoring::save($courseid, $code, $draft);
    }

    public function test_the_checker_says_what_is_missing(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $own = authoring::create($course->id, '');
        $this->assertTrue(authoring::is_own($own->code));
        $keys = array_column(authoring::check(authoring::drafts($course->id)[0]->data), 'key');
        foreach (['title', 'objectives', 'places', 'questions'] as $missing) {
            $this->assertContains('authoring_check_' . $missing, $keys);
        }
        $draft = $this->draft($course->id, $own->code);
        $this->assertSame([], authoring::check($draft));
        // A choose one with two right options, and a scene without moments.
        $draft['scenes'][0]['moments'][0]['options'][1]['correct'] = true;
        $draft['scenes'][2]['moments'] = [];
        $keys = array_column(authoring::check($draft), 'key');
        $this->assertContains('authoring_check_options', $keys);
        $this->assertContains('authoring_check_scenemoments', $keys);
        // And it cannot be played while something is missing.
        authoring::save($course->id, $own->code, $draft);
        $this->expectException(\moodle_exception::class);
        authoring::publish($course->id, $own->code);
    }

    public function test_a_mission_from_scratch_as_a_package_and_back(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        $own = authoring::create($course->id, 'Las fracciones');
        $this->draft($course->id, $own->code);
        authoring::publish($course->id, $own->code);
        // A package as those of the catalog, with a code of its own: the site takes it as any other mission.
        $package = authoring::export($course->id, $own->code);
        $mission = missions::upload($package['path']);
        $this->assertFalse(authoring::is_own($mission['code']));
        $this->assertSame('site', missions::package_origin($mission['code']));
        $other = $dg->create_course();
        regions::save($other->id, ['enabled' => 1]);
        missions::install($other->id, $mission['code'], authoring::WORLD, 'gen');
        $this->assertSame($mission['code'], missions::get($other->id)->code);
        // And, loaded in another course, it is a draft to change.
        $third = $dg->create_course();
        $copy = authoring::import($third->id, $package['path']);
        $this->assertSame([], authoring::check(authoring::drafts($third->id)[0]->data));
        $this->assertSame('Las fracciones', authoring::drafts($third->id)[0]->data['title']);
        // Duplicated in its course, another draft with the same scenes.
        authoring::duplicate($third->id, $copy->code);
        $drafts = authoring::drafts($third->id);
        $this->assertCount(2, $drafts);
        $this->assertCount(3, $drafts[1]->data['scenes']);
    }

    public function test_a_mission_from_scratch_is_played_as_any_other(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $dg = $this->getDataGenerator();
        $course = $dg->create_course();
        regions::save($course->id, ['enabled' => 1, 'weeklycap' => 0]);
        $own = authoring::create($course->id, 'Las fracciones');
        $this->draft($course->id, $own->code);
        authoring::publish($course->id, $own->code);
        $mission = missions::get($course->id);
        $this->assertSame($own->code, $mission->code);
        $this->assertSame('own', $mission->origin);
        $this->assertSame(3, $DB->count_records(self::T . 'chapter', ['missionid' => $mission->id]));
        $this->assertNotSame(0, badges::find($course->id, $mission));
        // Its places take the spots and the landscapes of the valley, and its map is the one of the valley.
        $this->assertSame('livingthings', $mission->data['map']);
        $this->assertSame(['claro', 'biblioteca', 'bosque'], array_column($mission->data['chapters'], 'art'));
        // A scene of the draft can be tried before: as the game plays it, with the landscape of its place.
        $scene = array_keys(authoring::scenes($course->id, $own->code))[1];
        $preview = authoring::preview($course->id, $own->code, $scene);
        $this->assertSame('biblioteca', $preview['place']);
        $this->assertArrayHasKey('fin', json_decode($preview['definition'], true)['nodes']);
        // A student plays every scene well: the lanterns of the first two places, and the creature.
        $user = $dg->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        engine::member($char, $course->id);
        foreach ($DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code') as $scene) {
            [$items, $roles, $flags, $role] = scenes::context(characters::get($user->id), $course->id);
            $events = autoplayer::play(json_decode($scene->definition, true), $items, $roles, $flags, $role);
            $this->assertSame('success', scenes::play($course->id, $user->id, (int) $scene->id, $events, null)['outcome']);
        }
        $lanterns = $DB->count_records(self::T . 'found', ['charid' => $char->id, 'kind' => 'lantern']);
        $this->assertSame(2, $lanterns);
        $this->assertGreaterThan(0, (int) characters::get($user->id)->creaturestage);
        // Made again, a new version of the same mission, with what the student did.
        authoring::publish($course->id, $own->code);
        $this->assertSame(2, (int) missions::get($course->id)->version);
        $this->assertSame($lanterns, $DB->count_records(self::T . 'found', ['charid' => $char->id, 'kind' => 'lantern']));
    }
}
