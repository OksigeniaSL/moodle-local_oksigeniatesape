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
 * Tests for the state of the game: saber and its caps, gems, energy and falling, breath, items, pause, undo and the
 * daily refill.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\engine
 */
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
final class engine_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with the game on and a student with a character in it.
     *
     * @param array $settings Settings of the region.
     * @return array [course, char, member]
     */
    private function make_player(array $settings = []): array {
        $course = $this->getDataGenerator()->create_course();
        regions::save($course->id, $settings + ['enabled' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $char = characters::create($user->id, 'sage', 'f');
        return [$course, $char, engine::member($char, $course->id)];
    }

    /**
     * The character as it is in the database.
     *
     * @param \stdClass $char
     * @return \stdClass
     */
    private function char(\stdClass $char): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'char', ['id' => $char->id]);
    }

    /**
     * The character in a course as it is in the database.
     *
     * @param \stdClass $member
     * @return \stdClass
     */
    private function member(\stdClass $member): \stdClass {
        global $DB;
        return $DB->get_record(self::T . 'member', ['id' => $member->id]);
    }

    public function test_joining_a_course(): void {
        $this->resetAfterTest();
        [$course, $char, $member] = $this->make_player();
        $this->assertEquals(engine::ENERGY_MAX, $member->energy);
        $this->assertEquals(engine::BREATH_MAX, $member->breath);
        $this->assertEquals('active', $member->status);
        $this->assertEquals(2, $member->step);
        // Joining again gives the same one.
        $this->assertEquals($member->id, engine::member($char, $course->id)->id);
    }

    public function test_saber_levels_and_renown(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player();
        $entry = engine::saber($member, 25, 'scene', 0);
        $this->assertEquals(25, $entry->amount);
        $this->assertEquals(0, $entry->capped);
        $char = $this->char($char);
        $this->assertEquals(25, $char->saber);
        $this->assertEquals(2, $char->level);
        $this->assertEquals(25, $this->member($member)->renown);
    }

    public function test_weekly_cap(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player(['weeklycap' => 50]);
        $monday = engine::week_start(time());
        engine::saber($member, 30, 'completion', 0, 0, 0, $monday + HOURSECS);
        $entry = engine::saber($member, 30, 'completion', 0, 0, 0, $monday + 2 * HOURSECS);
        $this->assertEquals(20, $entry->amount);
        $this->assertEquals(10, $entry->capped);
        $entry = engine::saber($member, 15, 'completion', 0, 0, 0, $monday + 3 * HOURSECS);
        $this->assertEquals(0, $entry->amount);
        $this->assertEquals(15, $entry->capped);
        // The mission goes under no cap.
        $this->assertEquals(20, engine::saber($member, 20, 'scene', 0, 0, 0, $monday + 4 * HOURSECS)->amount);
        // A new week, a new cap.
        $entry = engine::saber($member, 15, 'completion', 0, 0, 0, $monday + WEEKSECS + HOURSECS);
        $this->assertEquals(15, $entry->amount);
        $this->assertEquals(85, $this->char($char)->saber);
    }

    public function test_the_weekly_cap_of_the_site_counts_every_course(): void {
        $this->resetAfterTest();
        set_config('weeklycap', 50, 'local_oksigeniatesape');
        [$course, $char, $member] = $this->make_player();
        $other = $this->getDataGenerator()->create_course();
        regions::save($other->id, ['enabled' => 1]);
        $there = engine::member($char, $other->id);
        $monday = engine::week_start(time());
        $this->assertEquals(30, engine::saber($member, 30, 'completion', 0, 0, 0, $monday + HOURSECS)->amount);
        // What the mission gives neither goes under the cap nor fills it.
        $this->assertEquals(40, engine::saber($member, 40, 'battle', 0, 0, 0, $monday + 2 * HOURSECS)->amount);
        $this->assertEquals(40, engine::saber($member, 40, 'classroom', 2, 0, 0, $monday + 3 * HOURSECS, true)->amount);
        $entry = engine::saber($there, 30, 'completion', 0, 0, 0, $monday + 4 * HOURSECS);
        $this->assertEquals(20, $entry->amount);
        $this->assertEquals(10, $entry->capped);
        $this->assertEquals(130, $this->char($char)->saber);
        // 0: no cap.
        set_config('weeklycap', 0, 'local_oksigeniatesape');
        $this->assertEquals(30, engine::saber($there, 30, 'completion', 0, 0, 0, $monday + 5 * HOURSECS)->amount);
        $this->assertNotEmpty($course);
    }

    public function test_undone_saber_frees_the_weekly_cap(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player(['weeklycap' => 40]);
        $now = engine::week_start(time()) + DAYSECS;
        $entry = engine::saber($member, 40, 'manual', 2, 0, 0, $now);
        engine::undo($entry->id, 2, $now + 1);
        $this->assertEquals(0, $this->char($char)->saber);
        $this->assertEquals(0, $this->member($member)->renown);
        $this->assertEquals(40, engine::saber($member, 40, 'scene', 0, 0, 0, $now + 2)->amount);
    }

    public function test_daily_manual_cap(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player(['dailymanualcap' => 20]);
        $now = engine::day_start(time()) + HOURSECS;
        $this->assertEquals(15, engine::saber($member, 15, 'manual', 2, 0, 0, $now)->amount);
        $this->assertEquals(5, engine::saber($member, 15, 'manual', 2, 0, 0, $now + 1)->amount);
        // Saber from playing is not manual.
        $this->assertEquals(15, engine::saber($member, 15, 'scene', 0, 0, 0, $now + 2)->amount);
        // Tomorrow, again.
        $this->assertEquals(15, engine::saber($member, 15, 'manual', 2, 0, 0, $now + DAYSECS)->amount);
    }

    public function test_saber_is_never_taken(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player();
        $this->expectException(\coding_exception::class);
        engine::saber($member, -5, 'manual', 2);
    }

    public function test_paused_students_win_nothing(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player();
        $member = engine::pause($member, 'Needs a break', 2);
        $this->assertNull(engine::saber($member, 20, 'scene', 0));
        $this->assertNull(engine::gems($member, 5, 'quest', 0));
        $this->assertNull(engine::energy($member, -2, 'commitment', 2));
        $this->assertEquals(0, $this->char($char)->saber);
        $member = engine::resume($member);
        $this->assertEquals('active', $member->status);
        $this->assertNull($this->member($member)->statusreason);
        $this->assertEquals(20, engine::saber($member, 20, 'scene', 0)->amount);
    }

    public function test_gems(): void {
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player();
        engine::gems($member, 10, 'quest', 0);
        engine::gems($member, -4, 'shop', $char->userid);
        $this->assertEquals(6, $this->char($char)->gems);
        $this->expectException(\moodle_exception::class);
        engine::gems($member, -7, 'shop', $char->userid);
    }

    public function test_energy_loss_cap_and_falling(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player(['dailyenergylosscap' => 3]);
        $now = engine::day_start(time()) + HOURSECS;
        $entry = engine::energy($member, -2, 'commitment', 2, 0, $now);
        $this->assertEquals(-2, $entry->amount);
        $entry = engine::energy($member, -2, 'commitment', 2, 0, $now + 1);
        $this->assertEquals(-1, $entry->amount);
        $this->assertEquals(1, $entry->capped);
        $this->assertEquals(7, $this->member($member)->energy);
        // Without a cap, down to 0: worn out in this course, without losing saber or level.
        $member = $this->member($member);
        regions::save($member->courseid, ['dailyenergylosscap' => 0]);
        engine::energy($member, -20, 'commitment', 2, 0, $now + 2);
        $member = $this->member($member);
        $this->assertEquals(0, $member->energy);
        $this->assertEquals('fallen', $member->status);
        // Their team rescues them.
        $entry = engine::recover($member, 'rescue', 3, $now + 3);
        $this->assertEquals(engine::RECOVERED_ENERGY, $entry->amount);
        $member = $this->member($member);
        $this->assertEquals('active', $member->status);
        $this->assertEquals(engine::RECOVERED_ENERGY, $member->energy);
    }

    public function test_breath(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player();
        engine::breath($member, -3, 'power', 0);
        $this->assertEquals(2, $this->member($member)->breath);
        // It never goes over the top.
        $entry = engine::breath($member, 10, 'explorer', 2);
        $this->assertEquals(3, $entry->amount);
        $this->expectException(\moodle_exception::class);
        engine::breath($member, -6, 'power', 0);
    }

    public function test_items_once(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $char, $member] = $this->make_player();
        $item = $this->getDataGenerator()->get_plugin_generator('local_oksigeniatesape')->create_item(['code' => 'net']);
        $entry = engine::item($member, $item->id, 'minigame', 0);
        $this->assertNotNull($entry);
        $this->assertNull(engine::item($member, $item->id, 'minigame', 0));
        engine::undo($entry->id, 2);
        $this->assertFalse($DB->record_exists(self::T . 'inventory', ['charid' => $char->id, 'courseid' => $course->id]));
    }

    public function test_undo(): void {
        global $DB;
        $this->resetAfterTest();
        [, $char, $member] = $this->make_player();
        $entry = engine::saber($member, 30, 'scene', 0);
        $undo = engine::undo($entry->id, 2);
        $this->assertEquals(-30, $undo->amount);
        $this->assertEquals('undo', $undo->reason);
        $this->assertEquals($entry->id, $undo->refid);
        $this->assertEquals($undo->id, $DB->get_field(self::T . 'ledger', 'undoneby', ['id' => $entry->id]));
        $char = $this->char($char);
        $this->assertEquals(0, $char->saber);
        $this->assertEquals(1, $char->level);
        // Not twice, and an undo is not undone.
        try {
            engine::undo($entry->id, 2);
            $this->fail('Undone twice');
        } catch (\moodle_exception $e) {
            $this->assertEquals('cannotundo', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        engine::undo($undo->id, 2);
    }

    public function test_undoing_the_loss_that_made_them_fall(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player(['dailyenergylosscap' => 0]);
        $entry = engine::energy($member, -10, 'commitment', 2);
        $this->assertEquals('fallen', $this->member($member)->status);
        engine::undo($entry->id, 2);
        $member = $this->member($member);
        $this->assertEquals('active', $member->status);
        $this->assertEquals(engine::ENERGY_MAX, $member->energy);
    }

    public function test_undoing_the_recovery_leaves_them_worn_out(): void {
        $this->resetAfterTest();
        [, , $member] = $this->make_player(['dailyenergylosscap' => 0]);
        engine::energy($member, -10, 'commitment', 2);
        $member = $this->member($member);
        $entry = engine::recover($member, 'recovery', 2);
        $this->assertEquals('active', $this->member($member)->status);
        engine::undo($entry->id, 2);
        $member = $this->member($member);
        $this->assertEquals(0, $member->energy);
        $this->assertEquals('fallen', $member->status);
    }

    public function test_the_days_of_a_course_follow_its_time_zone(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setTimezone('Europe/Madrid');
        [$course, , $member] = $this->make_player(['timezone' => 'America/Asuncion']);
        $now = 1791720000;
        $asuncion = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('America/Asuncion'));
        $madrid = (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('Europe/Madrid'));
        $this->assertEquals($asuncion->setTime(0, 0)->getTimestamp(), engine::day_start($now, $course->id));
        $this->assertEquals($madrid->setTime(0, 0)->getTimestamp(), engine::day_start($now));
        // Energy and breath come back when the day starts in Asunción, not in Madrid.
        $today = engine::day_start($now, $course->id);
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'energy' => 4, 'lastregen' => $today - 1]);
        $this->assertEquals(1, engine::regenerate($now));
        $this->assertEquals(0, engine::regenerate($now));
        $this->expectException(\invalid_parameter_exception::class);
        regions::save($course->id, ['timezone' => 'Mars/Olympus']);
    }

    public function test_regenerate(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, , $member] = $this->make_player(['dailyenergylosscap' => 0]);
        $yesterday = engine::day_start(time()) - HOURSECS;
        $DB->update_record(self::T . 'member', (object) ['id' => $member->id, 'energy' => 4, 'breath' => 1,
            'lastregen' => $yesterday]);
        // A paused student in the same course, and a student in a course where the game is off.
        [, , $paused] = $this->make_player();
        $DB->set_field(self::T . 'member', 'courseid', $course->id, ['id' => $paused->id]);
        $DB->update_record(self::T . 'member', (object) ['id' => $paused->id, 'status' => 'paused', 'energy' => 4,
            'lastregen' => $yesterday]);
        [$offcourse, , $off] = $this->make_player();
        regions::save($offcourse->id, ['enabled' => 0]);
        $DB->update_record(self::T . 'member', (object) ['id' => $off->id, 'energy' => 4, 'lastregen' => $yesterday]);

        $this->assertEquals(1, engine::regenerate());
        $member = $this->member($member);
        $this->assertEquals(engine::ENERGY_MAX, $member->energy);
        $this->assertEquals(engine::BREATH_MAX, $member->breath);
        $this->assertEquals(4, $this->member($paused)->energy);
        $this->assertEquals(4, $this->member($off)->energy);
        // Once a day.
        $this->assertEquals(0, engine::regenerate());
    }
}
