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
 * Tests for the event of the day and the quick questions of the mission.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\events
 * @covers     \local_oksigeniatesape\local\questions
 */
#[\PHPUnit\Framework\Attributes\CoversClass(events::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(questions::class)]
final class events_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment, at midday, so that the day does not change during a test. */
    private const NOW = 1791720000;

    /**
     * A course with the game on, two teams of two players and, if asked, the sample mission.
     *
     * @param bool $mission
     * @return array [course, members (four), groups (two)]
     */
    private function make_class(bool $mission = false): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $groups = [$gen->create_group(['courseid' => $course->id]), $gen->create_group(['courseid' => $course->id])];
        $members = [];
        foreach ([0, 0, 1, 1] as $i => $team) {
            $user = $gen->create_and_enrol($course, 'student');
            $gen->create_group_member(['groupid' => $groups[$team]->id, 'userid' => $user->id]);
            $members[] = engine::member(characters::create($user->id, 'sage', $i % 2 ? 'f' : 'm'), $course->id, self::NOW);
        }
        if ($mission) {
            missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        }
        return [$course, $members, $groups];
    }

    /**
     * A member as it is in the database.
     *
     * @param \stdClass $member
     * @return \stdClass
     */
    private function member(\stdClass $member): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'member', ['id' => $member->id]);
    }

    public function test_drawing_never_repeats_yesterday_nor_the_card_it_replaces(): void {
        $this->resetAfterTest();
        [$course] = $this->make_class();
        $this->assertNull(events::today($course->id, self::NOW));
        events::draw($course->id, 'calm', self::NOW - DAYSECS);
        for ($i = 0; $i < 20; $i++) {
            $before = events::today($course->id, self::NOW);
            $event = events::draw($course->id, '', self::NOW);
            $this->assertNotEquals('calm', $event->code);
            if ($before) {
                $this->assertNotEquals($before->code, $event->code);
                $this->assertEquals($before->id, $event->id);
            }
        }
        $this->assertEquals('skipped', events::skip($course->id, self::NOW)->status);
        $this->assertNull(events::active($course->id, $event->code, self::NOW));
    }

    public function test_the_deck_of_the_mission_and_its_five_new_cards(): void {
        global $DB;
        $this->resetAfterTest();
        [$plain] = $this->make_class();
        $this->assertSame(events::CORE, events::deck($plain->id));
        [$course, $members] = $this->make_class(true);
        $this->assertCount(13, events::deck($course->id));
        $drawn = [];
        for ($i = 0; $i < 60; $i++) {
            $drawn[events::draw($course->id, '', self::NOW)->code] = true;
        }
        $this->assertNotEmpty(array_intersect(array_keys($drawn), ['minionday', 'owlnight', 'glowworms', 'sniff', 'pageday']));
        // The students see the card of today, with the name of the mission.
        events::draw($course->id, 'glowworms', self::NOW);
        $this->assertSame(
            ['code' => 'glowworms', 'name' => 'Noche de luciérnagas'],
            array_intersect_key(events::for_students($course->id, self::NOW), ['code' => 1, 'name' => 1])
        );
        // A good nose: the creature sniffs once more, if it has hatched.
        $char = characters::get($DB->get_field(self::T . 'char', 'userid', ['id' => $members[0]->charid]));
        $this->assertSame(0, creatures::sniffs($char, $course->id, self::NOW));
        $DB->set_field(self::T . 'char', 'creature', json_encode(scenes::creature($char)), ['id' => $char->id]);
        $char = characters::get((int) $char->userid);
        $base = creatures::sniffs($char, $course->id, self::NOW);
        events::draw($course->id, 'sniff', self::NOW);
        $this->assertSame($base + 1, creatures::sniffs($char, $course->id, self::NOW));
        // Minions out walking: the minion on the road gives twice the gems.
        events::draw($course->id, 'minionday', self::NOW);
        $mission = missions::get($course->id);
        $minion = minions::on_road($char, $mission, 'claro', self::NOW);
        $method = new \ReflectionMethod(minions::class, 'question');
        $knot = array_values(array_filter($mission->data['battle']['knots'], fn($k) => $k['code'] === $minion['code']))[0];
        $right = (int) $method->invoke(null, $char, $mission, $knot, engine::day_start(self::NOW, $course->id))['correct'];
        $done = minions::answer($char, $this->member($members[0]), $minion['code'], $right, self::NOW);
        $this->assertSame(2 * minions::GEMS, $done['gems']);
        // Without a card, nothing to see.
        events::skip($course->id, self::NOW);
        $this->assertNull(events::for_students($course->id, self::NOW));
    }

    public function test_the_embrollo_comes_back_from_the_third_lantern_to_a_place_already_lit(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->make_class(true);
        $mission = missions::get($course->id);
        // Before the third lantern, it does not come back.
        $this->assertFalse(stains::can_come_back($course->id));
        try {
            events::draw($course->id, 'comeback', self::NOW);
            $this->fail('The Embrollo came back too early');
        } catch (\moodle_exception $e) {
            $this->assertSame('cardnotplayable', $e->errorcode);
        }
        foreach (['claro', 'biblioteca', 'bosque'] as $code) {
            $DB->set_field(self::T . 'chapter', 'litat', self::NOW - DAYSECS, ['missionid' => $mission->id, 'code' => $code]);
        }
        $this->assertTrue(stains::can_come_back($course->id));
        $event = events::draw($course->id, 'comeback', self::NOW);
        $stain = $DB->get_record(self::T . 'stain', ['id' => json_decode($event->data, true)['comeback']]);
        $this->assertContains($stain->chapter, ['claro', 'biblioteca', 'bosque']);
        $this->assertSame(0, (int) $stain->groupid);
        // The class sees it in «Today in the valley», on its place.
        $this->assertContains($stain->chapter, array_column(today::get($course->id)['stains'] ?? [], 'chapter'));
        // Another card instead: the stain goes with it, and the day stays decided without a stain.
        events::draw($course->id, 'calm', self::NOW);
        $this->assertSame('', $DB->get_field(self::T . 'stain', 'chapter', ['id' => $stain->id]));
        // It comes back in the place of today's decision, if that decision stained nothing; not if it stained something.
        $again = events::draw($course->id, 'comeback', self::NOW);
        $this->assertSame((int) $stain->id, json_decode($again->data, true)['comeback']);
        $this->assertFalse(stains::can_come_back($course->id, self::NOW));
    }

    public function test_calm_stops_losses_of_energy_for_the_day(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        events::draw($course->id, 'calm', self::NOW);
        $entry = engine::energy($members[0], -3, 'commitment', 2, 0, self::NOW);
        $this->assertEquals(0, $entry->amount);
        $this->assertEquals('calm', $entry->reason);
        $this->assertEquals(3, $entry->capped);
        $this->assertEquals(engine::ENERGY_MAX, $this->member($members[0])->energy);
        // The next day it is over.
        engine::energy($this->member($members[0]), -3, 'commitment', 2, 0, self::NOW + DAYSECS);
        $this->assertEquals(engine::ENERGY_MAX - 3, $this->member($members[0])->energy);
    }

    public function test_breeze_doubles_what_is_completed_today(): void {
        $this->resetAfterTest();
        [$course, $members] = $this->make_class();
        events::draw($course->id, 'breeze', self::NOW);
        $this->assertEquals(20, engine::saber($members[0], 10, 'completion', 0, 0, 0, self::NOW)->amount);
        // Only activities: what the teacher gives by hand stays as it is.
        $this->assertEquals(10, engine::saber($members[1], 10, 'manual', 2, 0, 0, self::NOW)->amount);
    }

    public function test_the_footprint_gives_saber_to_everybody_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members] = $this->make_class(true);
        $event = events::draw($course->id, 'footprint', self::NOW);
        $question = events::export($event)['question'];
        $this->assertNotEmpty($question['text']);
        $this->assertGreaterThanOrEqual(2, count($question['options']));
        $this->assertArrayHasKey($question['correct'], $question['options']);
        // The same order of the options on every screen.
        $this->assertEquals($question, events::export(events::today($course->id, self::NOW))['question']);
        // Without a lantern of the class lit, only questions of the first chapter.
        $this->assertEquals('claro', $question['chapter']);
        events::solve($course->id, true, 2, self::NOW);
        events::solve($course->id, true, 2, self::NOW);
        $this->assertEquals(4, $DB->count_records(self::T . 'ledger', ['courseid' => $course->id, 'reason' => 'event']));
        $this->assertEquals('right', events::export(events::today($course->id, self::NOW))['solved']);
        // Once something has come of it, the card stays.
        $this->expectException(\moodle_exception::class);
        events::draw($course->id, '', self::NOW);
    }

    public function test_a_footprint_the_class_misses_gives_nothing(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->make_class(true);
        events::draw($course->id, 'footprint', self::NOW);
        events::solve($course->id, false, 2, self::NOW);
        $this->assertEquals(0, $DB->count_records(self::T . 'ledger', ['courseid' => $course->id, 'reason' => 'event']));
        $this->assertEquals('wrong', events::export(events::today($course->id, self::NOW))['solved']);
    }

    public function test_in_the_exchange_each_team_gives_once_and_not_to_itself(): void {
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        foreach ($members as $member) {
            engine::breath($member, -2, 'power', 0, 0, self::NOW);
        }
        events::draw($course->id, 'exchange', self::NOW);
        events::gift($course->id, (int) $groups[0]->id, (int) $groups[1]->id, 2, self::NOW);
        events::gift($course->id, (int) $groups[0]->id, (int) $groups[1]->id, 2, self::NOW);
        $this->assertEquals(engine::BREATH_MAX - 2 + events::EXCHANGE_BREATH, $this->member($members[2])->breath);
        $this->assertEquals(engine::BREATH_MAX - 2, $this->member($members[0])->breath);
        $gifts = events::export(events::today($course->id, self::NOW))['gifts'];
        $this->assertEquals([['from' => (int) $groups[0]->id, 'to' => (int) $groups[1]->id]], $gifts);
        $this->expectException(\invalid_parameter_exception::class);
        events::gift($course->id, (int) $groups[1]->id, (int) $groups[1]->id, 2, self::NOW);
    }

    public function test_a_knot_for_a_team_is_cleaned_only_by_that_team_and_counts_for_the_expedition(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class(true);
        $data = events::export(events::draw($course->id, 'knot', self::NOW));
        $this->assertContains($data['target'], [(int) $groups[0]->id, (int) $groups[1]->id]);
        // The teacher sends it to the second team: the knot goes with it, on the place of today.
        $data = events::export(events::aim($course->id, (int) $groups[1]->id, self::NOW));
        $this->assertSame((int) $groups[1]->id, $data['target']);
        $this->assertFalse($data['cleaned']);
        $stain = $DB->get_record(self::T . 'stain', ['courseid' => $course->id, 'groupid' => $groups[1]->id], '*', MUST_EXIST);
        $this->assertSame('claro', $stain->chapter);
        $view = stains::view($stain, missions::get($course->id));
        $done = ['marks' => array_values(array_filter(array_column($view['parts'], 'mark'), fn($m) => $m >= 0)), 'misses' => 0];
        // Somebody of the other team cannot clean it; somebody of the team can.
        try {
            stains::clean($course->id, (int) $stain->id, $members[0], $done, self::NOW);
            $this->fail('Cleaned by another team');
        } catch (\moodle_exception $e) {
            $this->assertSame('nostain', $e->errorcode);
        }
        $this->assertTrue(stains::clean($course->id, (int) $stain->id, $members[2], $done, self::NOW)['clean']);
        $this->assertTrue(events::export(events::today($course->id, self::NOW))['cleaned']);
        $this->assertSame(1, expedition::strength($course->id)['stains']);
        // Once cleaned, the card stays.
        $this->expectException(\moodle_exception::class);
        events::draw($course->id, '', self::NOW);
    }

    public function test_a_knot_changed_or_skipped_goes_away_and_ends_with_the_day(): void {
        global $DB;
        $this->resetAfterTest();
        [$course] = $this->make_class(true);
        $teamknots = fn() => $DB->count_records_select(self::T . 'stain', 'groupid <> 0');
        events::draw($course->id, 'knot', self::NOW);
        $this->assertSame(1, $teamknots());
        events::draw($course->id, 'calm', self::NOW);
        $this->assertSame(0, $teamknots());
        events::draw($course->id, 'knot', self::NOW);
        events::skip($course->id, self::NOW);
        $this->assertSame(0, $teamknots());
        // Not cleaned, it is there until the day ends.
        events::draw($course->id, 'knot', self::NOW);
        $id = (int) $DB->get_field_select(self::T . 'stain', 'id', 'groupid <> 0');
        $this->assertContains($id, array_map('intval', array_column(stains::open($course->id, self::NOW), 'id')));
        $this->assertNotContains($id, array_map('intval', array_column(stains::open($course->id, self::NOW + DAYSECS), 'id')));
    }

    public function test_the_messengers_take_a_hint_to_another_team_once(): void {
        $this->resetAfterTest();
        [$course, , $groups] = $this->make_class();
        events::draw($course->id, 'messengers', self::NOW);
        events::aim($course->id, (int) $groups[0]->id, self::NOW);
        $this->assertFalse(events::brought($course->id, (int) $groups[1]->id, self::NOW));
        try {
            events::deliver($course->id, (int) $groups[0]->id, self::NOW);
            $this->fail('A hint to their own team');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('another team', $e->getMessage());
        }
        $data = events::export(events::deliver($course->id, (int) $groups[1]->id, self::NOW));
        $this->assertSame((int) $groups[1]->id, $data['to']);
        $this->assertTrue(events::brought($course->id, (int) $groups[1]->id, self::NOW));
        $this->assertFalse(events::brought($course->id, (int) $groups[0]->id, self::NOW));
        $this->assertFalse(events::brought($course->id, (int) $groups[1]->id, self::NOW + DAYSECS));
        // Once taken, the card stays.
        $this->expectException(\moodle_exception::class);
        events::aim($course->id, (int) $groups[1]->id, self::NOW);
    }

    public function test_in_the_trade_a_team_lends_an_item_to_another_until_the_day_ends(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $members, $groups] = $this->make_class();
        // The scenes ask for the card of the day as it is now.
        $now = time();
        $lens = $DB->insert_record(self::T . 'item', ['code' => 'lupa', 'name' => 'La lupa', 'timecreated' => $now,
            'timemodified' => $now]);
        engine::item($members[0], $lens, 'challenge', 0, 0, $now);
        $char = fn(int $i) => $DB->get_record(self::T . 'char', ['id' => $members[$i]->charid]);
        events::draw($course->id, 'trade', $now);
        $offers = events::export(events::today($course->id, $now))['offers'];
        $mine = array_values(array_filter($offers, fn($o) => $o['team'] === (int) $groups[0]->id));
        $this->assertSame([['code' => 'lupa', 'name' => 'La lupa']], $mine[0]['items']);
        // Not an item the team carries, nor to itself.
        try {
            events::lend($course->id, (int) $groups[1]->id, (int) $groups[0]->id, 'lupa', $now);
            $this->fail('An item the team does not carry');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('item of the team', $e->getMessage());
        }
        try {
            events::lend($course->id, (int) $groups[0]->id, (int) $groups[0]->id, 'lupa', $now);
            $this->fail('A loan to their own team');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('two teams', $e->getMessage());
        }
        events::lend($course->id, (int) $groups[0]->id, (int) $groups[1]->id, 'lupa', $now);
        $loans = events::export(events::today($course->id, $now))['loans'];
        $this->assertSame([['from' => (int) $groups[0]->id, 'to' => (int) $groups[1]->id, 'item' => 'lupa',
            'name' => 'La lupa']], $loans);
        // In the scenes of today: the other team carries it, and the one that lent it does not.
        [$items] = scenes::context($char(2), $course->id);
        $this->assertContains('lupa', $items);
        [$items] = scenes::context($char(0), $course->id);
        $this->assertNotContains('lupa', $items);
        $this->assertSame(['lupa'], events::traded($course->id, (int) $groups[1]->id, [], $now));
        // Tomorrow it is back.
        $this->assertSame([], events::traded($course->id, (int) $groups[1]->id, [], $now + DAYSECS));
        $this->assertSame(['lupa'], events::traded($course->id, (int) $groups[0]->id, ['lupa'], $now + DAYSECS));
        // Once lent, the card stays.
        $this->expectException(\moodle_exception::class);
        events::draw($course->id, 'calm', $now);
    }

    public function test_the_cards_for_a_team_need_teams(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, ['enabled' => 1]);
        for ($i = 0; $i < 20; $i++) {
            $this->assertNotContains(events::draw($course->id, '', self::NOW)->code, events::TARGETED);
        }
        $this->expectException(\moodle_exception::class);
        events::draw($course->id, 'messengers', self::NOW);
    }

    public function test_a_mission_gives_the_cards_its_own_names(): void {
        $this->resetAfterTest();
        [$course] = $this->make_class(true);
        $calm = events::export(events::draw($course->id, 'calm', self::NOW));
        $this->assertSame('La primera luz', $calm['name']);
        $this->assertStringContainsString('ánimo', $calm['text']);
        $this->assertSame('', events::export(events::draw($course->id, 'exchange', self::NOW))['name']);
    }
}
