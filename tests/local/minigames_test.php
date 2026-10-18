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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
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

    public function test_losing_keeps_the_mix_ups(): void {
        $this->resetAfterTest();
        $w = $this->make_class();
        $game = minigames::config($w->mission, 'nestdefence', 2);
        $wrong = ['murcielago' => 'bird', 'pinguino' => 'fish', 'salamandra' => 'reptile', 'caballito' => ''];
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
        // Won once: the gems, once.
        $again = minigames::play($w->course->id, $w->user->id, 'enredoclaro', $found);
        $this->assertSame(0, $again['gems']);
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

    public function test_the_two_circles(): void {
        $this->resetAfterTest();
        $w = $this->make_class('py');
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
