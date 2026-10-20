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
 * Tests for Defensa del nido: the levels of each step, counting a play on the server, the reward once and what the
 * teacher sees.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\minigames
 */
#[\PHPUnit\Framework\Attributes\CoversClass(minigames::class)]
final class minigames_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the pilot mission and a student whose creature has hatched.
     *
     * @param string $variant
     * @return \stdClass
     */
    private function make_class(string $variant = 'es'): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1]);
        $w->mission = missions::get(missions::install($w->course->id, 'livingthings', 'valley', $variant)->courseid);
        $w->user = $dg->create_and_enrol($w->course, 'student');
        $char = characters::create($w->user->id, 'scout', 'f');
        $DB->set_field(self::T . 'char', 'creature', json_encode(scenes::creature($char)), ['id' => $char->id]);
        $DB->set_field(self::T . 'char', 'creaturestage', 1, ['id' => $char->id]);
        $w->member = engine::member(characters::get($w->user->id), $w->course->id);
        return $w;
    }

    /**
     * The events of a play: every animal with its right net, except those given.
     *
     * @param array $game Of minigames::config().
     * @param array $wrong Animal => net thrown.
     * @return array
     */
    private function events(array $game, array $wrong = []): array {
        return array_map(fn($a) => ['a' => $a['id'], 'net' => $wrong[$a['id']] ?? $a['net']], $game['animals']);
    }

    public function test_levels_by_step(): void {
        $this->resetAfterTest();
        $w = $this->make_class('py');
        // The first step, by traits: what the body is covered with, and those without bones.
        $first = minigames::config($w->mission, 'nestdefence', 1);
        $this->assertSame(['fur', 'feathers', 'scales', 'wetskin', 'invertebrate'], array_column($first['nets'], 'id'));
        $animals = array_column($first['animals'], null, 'id');
        $this->assertSame('fur', $animals['felino']['net']);
        $this->assertSame('yaguareté', $animals['felino']['label']);
        $this->assertSame('scales', $animals['lagarto']['net']);
        $this->assertSame('scales', $animals['pezrio']['net']);
        $this->assertSame('wetskin', $animals['rana']['net']);
        $this->assertSame('invertebrate', $animals['lombriz']['net']);

        $core = minigames::config($w->mission, 'nestdefence', 2);
        $this->assertCount(6, $core['nets']);
        $animals = array_column($core['animals'], null, 'id');
        $this->assertSame('mammal', $animals['murcielago']['net']);
        $this->assertSame('invertebrate', $animals['arana']['net']);
        $this->assertNotEmpty($animals['murcielago']['why']);

        $expert = minigames::config($w->mission, 'nestdefence', 4);
        $this->assertSame('arachnid', array_column($expert['animals'], null, 'id')['arana']['net']);
        $this->assertGreaterThan($core['speed'], $expert['speed']);
        $this->assertNull(minigames::config($w->mission, 'nogame', 2));
    }

    public function test_each_minigame_opens_with_two_lines_of_scene(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        foreach (array_keys($w->mission->data['games']) as $code) {
            $scene = minigames::config($w->mission, $code, 2)['scene'];
            $this->assertNotSame('', $scene['embrollo'], $code);
            $this->assertStringContainsString('{{creature}}', $scene['creature'], $code);
        }
    }

    public function test_the_protector_holds_one_slip_more(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $step = (int) $w->member->step;
        $plain = minigames::config($w->mission, 'nestdefence', $step, 'scout');
        $this->assertSame('scout', $plain['passive']);
        $this->assertSame(minigames::STREAK, $plain['streak']);
        $guard = minigames::config($w->mission, 'nestdefence', $step, 'protector');
        $this->assertSame($plain['shield'] + 1, $guard['shield']);
        $this->assertSame($plain['goal'] - 1, $guard['goal']);
        $misses = minigames::config($w->mission, 'quienestavivo', $step)['misses'];
        $this->assertSame($misses + 1, minigames::config($w->mission, 'quienestavivo', $step, 'protector')['misses']);
        // As many animals in as the shield holds: lost as a scout, won as a protector.
        $wrong = [];
        foreach (array_slice($plain['animals'], 0, $plain['shield']) as $a) {
            $wrong[$a['id']] = '';
        }
        $this->assertFalse(minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($plain, $wrong))['success']);
        $DB->set_field(self::T . 'char', 'role', 'protector', ['userid' => $w->user->id]);
        $this->assertTrue(minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($plain, $wrong))['success']);
    }

    public function test_the_healer_gets_the_first_slip_back_after_a_streak(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', (int) $w->member->step, 'healer');
        $ids = array_column($game['animals'], 'id');
        // Three in at the start, a streak, and one more at the end: four in all, as many as the shield holds.
        $wrong = [];
        foreach ([0, 1, 2, count($ids) - 1] as $i) {
            $wrong[$ids[$i]] = '';
        }
        $events = $this->events($game, $wrong);
        $this->assertFalse(minigames::play($w->course->id, $w->user->id, 'nestdefence', $events)['success']);
        $DB->set_field(self::T . 'char', 'role', 'healer', ['userid' => $w->user->id]);
        $this->assertTrue(minigames::play($w->course->id, $w->user->id, 'nestdefence', $events)['success']);
        // With the four at the end, no streak comes after a slip: nothing to get back.
        $late = [];
        foreach (array_slice($ids, -4) as $id) {
            $late[$id] = '';
        }
        $this->assertFalse(minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game, $late))['success']);
    }

    public function test_winning_gives_the_net_once(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $result = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $this->assertTrue($result['success']);
        $this->assertTrue($result['first']);
        $this->assertTrue($result['coat']);
        $this->assertSame(['net'], $result['items']);
        $this->assertSame(count($game['animals']), $result['caught']);
        $netid = $DB->get_field(self::T . 'item', 'id', ['code' => 'net']);
        $this->assertTrue($DB->record_exists(self::T . 'inventory', ['charid' => $w->member->charid, 'itemid' => $netid]));

        $again = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $this->assertTrue($again['success']);
        $this->assertFalse($again['first']);
        $this->assertSame([], $again['items']);
        $this->assertSame(2, $again['tries']);
    }

    public function test_winning_again_gives_a_gem_a_few_times_a_day_then_training(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $char = characters::get($w->user->id);
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $now = time();
        $first = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game), $now);
        $this->assertTrue($first['first']);
        $this->assertFalse(minigames::training($char, $w->mission, 'nestdefence', $now));
        // Won again: a gem each time, up to the cap of the day, in all the minigames together.
        for ($i = 0; $i < minigames::TRAINING; $i++) {
            $again = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game), $now + $i + 1);
            $this->assertSame(minigames::AGAIN_GEMS, $again['gems']);
            $this->assertFalse($again['training']);
        }
        // Then, training: no gems, the same day, in this minigame or another one won already.
        $this->assertTrue(minigames::training($char, $w->mission, 'nestdefence', $now + 10));
        $more = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game), $now + 10);
        $this->assertTrue($more['success']);
        $this->assertSame(0, $more['gems']);
        $this->assertTrue($more['training']);
        // A play lost gives nothing and is not training; a minigame not won yet gives its gems the first time.
        $none = array_fill_keys(array_column($game['animals'], 'id'), '');
        $lost = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game, $none), $now + 11);
        $this->assertFalse($lost['success']);
        $this->assertSame(0, $lost['gems']);
        $this->assertFalse($lost['training']);
        $this->assertFalse(minigames::training($char, $w->mission, 'quienestavivo', $now + 12));
        // The next day, gems again.
        $tomorrow = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game), $now + DAYSECS);
        $this->assertSame(minigames::AGAIN_GEMS, $tomorrow['gems']);
    }

    public function test_losing_keeps_the_mix_ups(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $wrong = ['murcielago' => 'bird', 'pinguino' => 'fish', 'salamandra' => 'reptile', 'caballitodemar' => ''];
        $result = minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game, $wrong));
        $this->assertFalse($result['success']);
        $this->assertSame(4, $result['missed']);
        $this->assertSame([], $result['items']);
        $this->assertFalse(minigames::has_coat(characters::get($w->user->id), $w->course->id));

        $results = minigames::results($w->mission);
        $this->assertSame(1, $results[0]['players']);
        $this->assertSame(0, $results[0]['won']);
        $this->assertSame('bosque', $results[0]['chapter']);
        $texts = array_column($results[0]['confusions'], 'text');
        $this->assertContains('murciélago → Ave', $texts);
        $this->assertCount(minigames::CONFUSIONS, $texts);
    }

    public function test_what_does_not_fit_is_refused(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $cases = [
            [['a' => 'unicorn', 'net' => 'mammal']],
            [['a' => 'felino', 'net' => 'mammal'], ['a' => 'felino', 'net' => 'mammal']],
            [['a' => 'felino', 'net' => 'insect']],
        ];
        foreach ($cases as $events) {
            try {
                minigames::play($w->course->id, $w->user->id, 'nestdefence', $events);
                $this->fail('Accepted: ' . json_encode($events));
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidgameplay', $e->errorcode);
            }
        }
        // Paused, no play.
        engine::pause($w->member, 'A break', 0);
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
    }

    public function test_coat_of_the_creature(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        try {
            minigames::set_coat($w->course->id, $w->user->id, 'fur');
            $this->fail('A coat before winning');
        } catch (\moodle_exception $e) {
            $this->assertSame('nocoat', $e->errorcode);
        }
        $game = minigames::config($w->mission, 'nestdefence', 2);
        minigames::play($w->course->id, $w->user->id, 'nestdefence', $this->events($game));
        $char = minigames::set_coat($w->course->id, $w->user->id, 'feathers');
        $traits = json_decode($char->creature, true);
        $this->assertSame('feathers', $traits['coat']);
        $this->assertNotEmpty($traits['name']);
        $this->assertSame('', json_decode(minigames::set_coat($w->course->id, $w->user->id, '')->creature, true)['coat']);
        $this->expectException(\moodle_exception::class);
        minigames::set_coat($w->course->id, $w->user->id, 'glitter');
    }

    public function test_a_sheet_of_the_hunt_for_mistakes_is_cut_into_text_and_mistakes(): void {
        $parts = minigames::parts('El erizo es un [reptil|mamífero|tiene pelo y púas]. Come [piedras|insectos/caracoles].');
        $marks = array_values(array_filter($parts, fn($p) => $p['mark'] >= 0));
        $this->assertCount(2, $marks);
        $this->assertSame('reptil', $marks[0]['wrong']);
        $this->assertSame(['mamífero'], $marks[0]['right']);
        $this->assertSame('tiene pelo y púas', $marks[0]['why']);
        $this->assertSame(['insectos', 'caracoles'], $marks[1]['right']);
        $this->assertSame('', $marks[1]['why']);
        $this->assertSame('El erizo es un ', $parts[0]['text']);
        $this->assertSame('.', end($parts)['text']);
    }

    public function test_the_hunt_for_mistakes_of_the_embrollo(): void {
        $this->resetAfterTest();
        $w = $this->make_class('gen');
        $char = characters::get($w->user->id);
        $member = engine::member($char, $w->course->id);
        $game = minigames::config($w->mission, 'enredoclaro', (int) $member->step);
        $this->assertSame('errors', $game['type']);
        $this->assertNotEmpty($game['sheets']);
        // Each sheet with the drawing of the being it talks about, if it has one.
        $pictures = array_column($game['sheets'], 'picture', 'id');
        $this->assertSame('being.girasol', $pictures['lo-que-engana']);
        $this->assertSame('being.seta', $pictures['notas-del-rastreo']);
        // Each mistake found once, and a word touched that was right: a blot.
        $found = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['parts'] as $part) {
                if ($part['mark'] >= 0) {
                    $found[] = ['s' => $sheet['id'], 'm' => $part['mark']];
                }
            }
        }
        $this->assertCount($game['goal'], $found);
        $blot = ['s' => $game['sheets'][0]['id'], 'x' => 1];
        $result = minigames::play($w->course->id, $w->user->id, 'enredoclaro', array_merge([$blot], $found));
        $this->assertTrue($result['success']);
        $this->assertSame($game['goal'], $result['caught']);
        $this->assertSame(1, $result['missed']);
        $this->assertSame(5, $result['gems']);
        // Won once: its gems, once; won again, a gem.
        $again = minigames::play($w->course->id, $w->user->id, 'enredoclaro', $found);
        $this->assertSame(minigames::AGAIN_GEMS, $again['gems']);
        // Too many blots lose, and the mistakes not found are what the teacher sees.
        $blots = array_fill(0, $game['misses'], $blot);
        $lost = minigames::play($w->course->id, $w->user->id, 'enredoclaro', array_merge($blots, [$found[0]]));
        $this->assertFalse($lost['success']);
        $results = array_column(minigames::results($w->mission), null, 'code');
        $this->assertNotEmpty($results['enredoclaro']['confusions']);
        $this->assertStringContainsString('→', $results['enredoclaro']['confusions'][0]['text']);
        // A mistake found twice, or one that does not exist, is not a play.
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'enredoclaro', [$found[0], $found[0]]);
    }

    public function test_a_page_of_the_two_circles_from_its_plain_text(): void {
        $venn = minigames::venn("# Animales\nPone huevos: gallina, ornitorrinco\nTiene pelo; con comas: conejo; ornitorrinco\n" .
            "? Ornitorrinco: pone huevos y tiene pelo\nTercer círculo: nada");
        $this->assertSame(['Pone huevos', 'Tiene pelo; con comas'], $venn['groups']);
        $zones = array_column($venn['items'], 'zone', 'label');
        $this->assertSame(['gallina' => 'a', 'ornitorrinco' => 'ab', 'conejo' => 'b'], $zones);
        $this->assertSame('pone huevos y tiene pelo', $venn['items'][1]['why']);
    }

    /**
     * A game of the two circles in the mission of a class: the pilot mission has them as scenes now, and the engine of
     * the minigame stays for the missions that want one.
     *
     * @param \stdClass $w The class, whose mission is read again.
     */
    private function with_two_circles(\stdClass $w): void {
        global $DB;
        $data = json_decode($DB->get_field('local_oksigeniatesape_mission', 'definition', ['id' => $w->mission->id]), true);
        $data['games']['circuloslago'] = [
            'type' => 'venn',
            'title' => 'Los dos círculos del lago',
            'intro' => 'Lleva cada ficha a su círculo. Si es de los dos, va al centro, donde se cruzan.',
            'misses' => [1 => 4, 2 => 3, 3 => 3, 4 => 3],
            'reward' => ['gems' => 5],
            'sheets' => [
                ['id' => 'que-come', 'title' => '¿Qué come?', 'steps' => [1, 2, 3, 4], 'text' => [
                    'es' => "Come plantas: conejo, caracol, jabalí, ser humano\nCome animales: búho, lince ibérico, jabalí, " .
                        "ser humano\n? jabalí: come raíces y también lombrices: es omnívoro.",
                    'py' => "Come plantas: conejo, caracol, pecarí, ser humano\nCome animales: búho, yaguareté, pecarí, " .
                        "ser humano\n? pecarí: come raíces y también pequeños animales: es omnívoro.",
                ]],
                ['id' => 'productores', 'title' => 'Los productores', 'steps' => [1, 2, 3, 4], 'text' =>
                    "Fabrica su alimento: alga, hierba\nVive en el agua: alga, renacuajo"],
            ],
        ];
        $DB->set_field('local_oksigeniatesape_mission', 'definition', json_encode($data), ['id' => $w->mission->id]);
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'missions')->purge();
        $w->mission = missions::get($w->course->id);
    }

    public function test_the_two_circles(): void {
        $this->resetAfterTest();
        $w = $this->make_class('py');
        $this->with_two_circles($w);
        $member = engine::member(characters::get($w->user->id), $w->course->id);
        $game = minigames::config($w->mission, 'circuloslago', (int) $member->step);
        $this->assertSame('venn', $game['type']);
        $this->assertCount(2, $game['sheets'][0]['groups']);
        // The local examples of the variant.
        $labels = array_column($game['sheets'][0]['items'], 'label');
        $this->assertContains('yaguareté', $labels);
        $this->assertSame(array_sum(array_map(fn($s) => count($s['items']), $game['sheets'])), $game['goal']);
        // Every card in its place, but one: won, with a slip.
        $events = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['items'] as $i => $item) {
                $events[] = ['s' => $sheet['id'], 'i' => $i, 'z' => $item['zone']];
            }
        }
        $wrong = $events;
        $wrong[0]['z'] = $wrong[0]['z'] === 'ab' ? 'a' : 'ab';
        $result = minigames::play($w->course->id, $w->user->id, 'circuloslago', $wrong);
        $this->assertTrue($result['success']);
        $this->assertSame($game['goal'] - 1, $result['caught']);
        $this->assertSame(1, $result['missed']);
        $this->assertSame(5, $result['gems']);
        // Cards left without a place do not win.
        $half = minigames::play($w->course->id, $w->user->id, 'circuloslago', array_slice($events, 0, 3));
        $this->assertFalse($half['success']);
        // The teacher sees which card went where.
        $results = array_column(minigames::results($w->mission), null, 'code');
        $this->assertStringContainsString('→', $results['circuloslago']['confusions'][0]['text']);
        // A card placed twice, or in a place that does not exist, is not a play.
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'circuloslago', [$events[0], $events[0]]);
    }

    public function test_a_riddle_of_who_am_i_from_its_plain_text(): void {
        $riddle = minigames::riddle("# Un mamífero que vuela\n= murciélago / murciélagos\nDuermo cabeza abajo.\nVuelo de noche.");
        $this->assertSame('murciélago', $riddle['answer']);
        $this->assertSame(['murciélagos'], $riddle['accept']);
        $this->assertSame(['Duermo cabeza abajo.', 'Vuelo de noche.'], $riddle['clues']);
    }

    public function test_who_am_i(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $member = engine::member(characters::get($w->user->id), $w->course->id);
        $game = minigames::config($w->mission, 'sombrastorre', (int) $member->step);
        $this->assertSame('whoami', $game['type']);
        $this->assertSame(count($game['riddles']), $game['goal']);
        foreach ($game['riddles'] as $riddle) {
            // Four answers to choose from, the right one among them; the clues, hardest first.
            $this->assertCount(4, array_unique($riddle['options']));
            $this->assertContains($riddle['answer'], $riddle['options']);
            $this->assertGreaterThanOrEqual(2, count($riddle['clues']));
        }
        // Every shadow found; one with another clue asked for and a wrong guess first: won.
        $first = $game['riddles'][0];
        $other = array_values(array_diff($first['options'], [$first['answer']]))[0];
        $events = [['r' => $first['id'], 'c' => 1], ['r' => $first['id'], 'g' => $other]];
        foreach ($game['riddles'] as $riddle) {
            $events[] = ['r' => $riddle['id'], 'g' => $riddle['answer']];
        }
        $result = minigames::play($w->course->id, $w->user->id, 'sombrastorre', $events);
        $this->assertTrue($result['success']);
        $this->assertSame($game['goal'], $result['caught']);
        $this->assertSame(1, $result['missed']);
        $this->assertSame(5, $result['gems']);
        // A shadow that gets away (a wrong guess with no clue left) does not win.
        $away = [];
        for ($i = 0; $i < count($first['clues']); $i++) {
            $away[] = ['r' => $first['id'], 'g' => $other];
        }
        $lost = minigames::play($w->course->id, $w->user->id, 'sombrastorre', $away);
        $this->assertFalse($lost['success']);
        $this->assertSame(0, $lost['caught']);
        // The teacher sees who was taken for whom.
        $results = array_column(minigames::results($w->mission), null, 'code');
        $mix = get_string('minigame_whoami_mix', 'local_oksigeniatesape', (object) ['answer' => $first['answer'], 'guess' => $other]);
        $this->assertSame($mix, $results['sombrastorre']['confusions'][0]['text']);
        // A guess after the shadow got away is not a play.
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'sombrastorre', array_merge($away, [['r' => $first['id'], 'g' => $other]]));
    }

    /**
     * The events of a play of Guess who that finds the hidden one: the question that splits best each time, then its
     * name.
     *
     * @param array $game Of minigames::config().
     * @return array
     */
    private function hunt(array $game): array {
        $up = array_column($game['cards'], 'id');
        $events = [['n' => $game['seed']]];
        $asked = [];
        while (count($up) > 1) {
            $best = null;
            $score = PHP_INT_MAX;
            foreach ($game['questions'] as $q) {
                $yes = count(array_intersect($up, $q['yes']));
                if (!isset($asked[$q['id']]) && $yes > 0 && $yes < count($up) && max($yes, count($up) - $yes) < $score) {
                    $best = $q;
                    $score = max($yes, count($up) - $yes);
                }
            }
            $asked[$best['id']] = true;
            $events[] = ['q' => $best['id']];
            $answer = in_array($game['secret'], $best['yes'], true);
            $up = array_values(array_filter($up, fn($id) => in_array($id, $best['yes'], true) === $answer));
        }
        $events[] = ['g' => $game['secret']];
        return $events;
    }

    public function test_guess_who(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $char = characters::get($w->user->id);
        $step = (int) $w->member->step;
        $config = fn($plays) => minigames::config(
            $w->mission,
            'adivinabiblioteca',
            $step,
            'scout',
            [],
            0,
            [],
            (int) $char->id,
            $plays
        );
        $game = $config(0);
        $this->assertSame('guesswho', $game['type']);
        $this->assertCount(12, $game['cards']);
        $this->assertSame($step <= 1 ? 5 : 4, $game['golden']);
        $ids = array_column($game['cards'], 'id');
        foreach ($game['questions'] as $q) {
            // Every question splits the cards, and the catalog answers it for all of them.
            $this->assertNotEmpty($q['yes']);
            $this->assertLessThan(12, count($q['yes']));
            $this->assertNotSame('', $q['text']);
        }
        $feathers = array_column($game['questions'], 'yes', 'id')['cover_plumas'];
        $this->assertSame(['paloma'], $feathers);
        // Play after play, a being after another: all twelve before one comes back.
        $secrets = array_map(fn($n) => $config($n)['secret'], range(0, 11));
        $this->assertEqualsCanonicalizing($ids, $secrets);
        $this->assertSame($secrets[0], $config(12)['secret']);
        // Found asking well: the card goes to the field guide, golden with few questions, and the gems once.
        $events = $this->hunt($game);
        $asked = count($events) - 2;
        $result = minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $events);
        $this->assertTrue($result['success']);
        $this->assertSame($asked, $result['missed']);
        $this->assertSame($asked <= $game['golden'], $result['golden']);
        $this->assertTrue($result['newcard']);
        $this->assertSame(5, $result['gems']);
        $guide = [];
        foreach (encounters::guide($char, $w->mission) as $place) {
            foreach ($place['beings'] as $being) {
                $guide[$being['id']] = $being['golden'];
            }
        }
        $this->assertSame($result['golden'], $guide[$game['secret']] ?? null);
        // The next play hides another; a wrong name turns its card over and counts as a question.
        $next = $config(1);
        $this->assertNotSame($game['secret'], $next['secret']);
        $other = array_values(array_diff($ids, [$next['secret']]))[0];
        $events = $this->hunt($next);
        array_splice($events, 1, 0, [['g' => $other]]);
        $again = minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $events);
        $this->assertTrue($again['success']);
        $this->assertSame(count($events) - 2, $again['missed']);
        $this->assertSame(minigames::AGAIN_GEMS, $again['gems']);
        // A play kept on the device and sent later is counted with the being that hid when it began.
        $late = minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $this->hunt($next));
        $this->assertTrue($late['success']);
        $this->assertSame(array_column($next['cards'], 'name', 'id')[$next['secret']], $late['card']);
        // The teacher sees what the class asks first.
        $results = array_column(minigames::results($w->mission), null, 'code');
        $first = array_column($game['questions'], 'text', 'id')[$this->hunt($game)[1]['q']];
        $texts = array_column($results['adivinabiblioteca']['confusions'], 'text');
        $this->assertContains(get_string('minigame_guesswho_first', 'local_oksigeniatesape', $first), $texts);
    }

    public function test_guess_who_refuses_what_cannot_be(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $char = characters::get($w->user->id);
        $game = minigames::config($w->mission, 'adivinabiblioteca', (int) $w->member->step, 'scout', [], 0, [], (int) $char->id);
        $q = $game['questions'][0];
        $answer = in_array($game['secret'], $q['yes'], true);
        $out = array_values(array_filter(array_column($game['cards'], 'id'), fn($id) => in_array($id, $q['yes'], true) !== $answer));
        $bad = [
            'the same question twice' => [['q' => $q['id']], ['q' => $q['id']]],
            'a name of a card turned over' => [['q' => $q['id']], ['g' => $out[0]]],
            'something after it was found' => [['g' => $game['secret']], ['q' => $q['id']]],
            'a look without looks' => [['l' => $out[0]]],
            'a question that is not' => [['q' => 'colour_green']],
        ];
        foreach ($bad as $what => $events) {
            try {
                minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $events);
                $this->fail('Accepted: ' . $what);
            } catch (\moodle_exception $e) {
                $this->assertSame('invalidgameplay', $e->errorcode, $what);
            }
        }
    }

    public function test_guess_who_with_the_passives(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $char = characters::get($w->user->id);
        $step = (int) $w->member->step;
        // The protector: golden with a question more. The sage: a look turns over two that are not the one.
        $protector = minigames::config($w->mission, 'adivinabiblioteca', $step, 'protector', [], 0, [], (int) $char->id);
        $scout = minigames::config($w->mission, 'adivinabiblioteca', $step, 'scout', [], 0, [], (int) $char->id);
        $this->assertSame($scout['golden'] + 1, $protector['golden']);
        global $DB;
        $DB->set_field(self::T . 'char', 'role', 'sage', ['id' => $char->id]);
        $sage = minigames::config($w->mission, 'adivinabiblioteca', $step, 'sage', [], 0, [], (int) $char->id);
        $this->assertSame(1, $sage['looks']);
        $two = array_slice(array_values(array_diff(array_column($sage['cards'], 'id'), [$sage['secret']])), 0, 2);
        $events = [['n' => 0], ['l' => $two[0]], ['l' => $two[1]], ['g' => $sage['secret']]];
        $result = minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $events);
        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['missed']);
        // The healer: the first question that turns nothing over does not count.
        $DB->set_field(self::T . 'char', 'role', 'healer', ['id' => $char->id]);
        $healer = minigames::config($w->mission, 'adivinabiblioteca', $step, 'healer', [], 0, [], (int) $char->id, 1);
        $events = $this->hunt($healer);
        $q = $events[1]['q'];
        $answer = in_array($healer['secret'], array_column($healer['questions'], 'yes', 'id')[$q], true);
        // After the first question, another that answers the same for all that are left turns nothing over.
        $left = array_values(array_filter(
            array_column($healer['cards'], 'id'),
            fn($id) => in_array($id, array_column($healer['questions'], 'yes', 'id')[$q], true) === $answer
        ));
        $idle = null;
        foreach ($healer['questions'] as $other) {
            $yes = count(array_intersect($left, $other['yes']));
            if ($other['id'] !== $q && ($yes === 0 || $yes === count($left)) && !in_array(['q' => $other['id']], $events, true)) {
                $idle = $other['id'];
                break;
            }
        }
        $this->assertNotNull($idle);
        array_splice($events, 2, 0, [['q' => $idle]]);
        $result = minigames::play($w->course->id, $w->user->id, 'adivinabiblioteca', $events);
        $this->assertTrue($result['success']);
        $this->assertSame(count($events) - 3, $result['missed']);
    }

    public function test_who_is_alive(): void {
        $this->resetAfterTest();
        $w = $this->make_class('py');
        // The things of each step, with the local examples of the variant.
        $first = minigames::config($w->mission, 'quienestavivo', 1);
        $this->assertSame('whack', $first['type']);
        $this->assertContains('lapacho', array_column($first['things'], 'label'));
        $this->assertNotEquals($first['things'], minigames::config($w->mission, 'quienestavivo', 4)['things']);
        $member = engine::member(characters::get($w->user->id), $w->course->id);
        $game = minigames::config($w->mission, 'quienestavivo', (int) $member->step);
        $living = array_values(array_filter($game['things'], fn($x) => $x['alive']));
        $inert = array_values(array_filter($game['things'], fn($x) => !$x['alive']));
        $this->assertGreaterThanOrEqual($game['goal'], count($living));
        $this->assertGreaterThanOrEqual($game['misses'], count($inert));
        // Each one with the key of its drawing, from the catalog of the mission: being.<id> or thing.<id>; a being named
        // by the country of the course, by its name (in Paraguay, the tree is the lapacho).
        foreach ($game['things'] as $thing) {
            $named = is_array($w->mission->data['things'][$thing['id']]['label'] ?? null) && $thing['alive'];
            $plain = preg_replace('/[^a-z]/', '', \core_text::strtolower(\core_text::specialtoascii($thing['label'])));
            $key = $named ? 'being.' . $plain : ($thing['alive'] ? 'being.' : 'thing.') . $thing['id'];
            $this->assertSame($key, $thing['picture']);
            $this->assertArrayHasKey($thing['id'], $w->mission->data['things']);
        }
        $this->assertSame('being.lapacho', minigames::picture($w->mission, 'arbol', true));
        // Every living thing touched, and one thing that is not: won, with a slip.
        $touch = array_map(fn($x) => ['i' => $x['id']], $living);
        $events = array_merge($touch, [['i' => $inert[0]['id']]]);
        $result = minigames::play($w->course->id, $w->user->id, 'quienestavivo', $events);
        $this->assertTrue($result['success']);
        $this->assertSame(count($living), $result['caught']);
        $this->assertSame(1, $result['missed']);
        $this->assertSame(5, $result['gems']);
        // Too many slips lose; a living thing let go is not caught.
        $slips = array_map(fn($x) => ['i' => $x['id']], array_slice($inert, 0, $game['misses']));
        $lost = minigames::play($w->course->id, $w->user->id, 'quienestavivo', array_merge(array_slice($touch, 1), $slips));
        $this->assertFalse($lost['success']);
        $this->assertSame(count($living) - 1, $lost['caught']);
        // The teacher sees what was taken for alive most.
        $results = array_column(minigames::results($w->mission), null, 'code');
        $taken = get_string('minigame_thoughtalive', 'local_oksigeniatesape', $inert[0]['label']);
        $this->assertSame($taken, $results['quienestavivo']['confusions'][0]['text']);
        // A thing touched twice, or one that is not in the game, is not a play.
        $this->expectException(\moodle_exception::class);
        minigames::play($w->course->id, $w->user->id, 'quienestavivo', [$touch[0], $touch[0]]);
    }
}
