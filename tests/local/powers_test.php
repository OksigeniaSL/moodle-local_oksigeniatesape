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
 * Tests for powers: asking with breath, the teacher's answer and what each power does in the game.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\powers
 * @covers     \local_oksigeniatesape\local\engine
 */
#[\PHPUnit\Framework\Attributes\CoversClass(powers::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(engine::class)]
final class powers_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A course with a team of four (protector, healer, sage, scout) at level 5, and a teacher.
     *
     * @return \stdClass
     */
    private function make_team(): \stdClass {
        global $DB;
        $dg = $this->getDataGenerator();
        $w = new \stdClass();
        $w->course = $dg->create_course();
        regions::save($w->course->id, ['enabled' => 1, 'weeklycap' => 0, 'dailyenergylosscap' => 10]);
        $w->teacher = $dg->create_and_enrol($w->course, 'editingteacher');
        $w->group = $dg->create_group(['courseid' => $w->course->id]);
        $w->users = [];
        foreach (['protector', 'healer', 'sage', 'scout'] as $role) {
            $user = $dg->create_and_enrol($w->course, 'student');
            $char = characters::create($user->id, $role, 'm');
            $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'saber' => 140, 'level' => 5]);
            engine::member(characters::get($user->id), $w->course->id);
            $dg->create_group_member(['groupid' => $w->group->id, 'userid' => $user->id]);
            $w->users[$role] = $user;
        }
        return $w;
    }

    /**
     * A power of the course by its code.
     *
     * @param \stdClass $w
     * @param string $code
     * @return \stdClass
     */
    private function power(\stdClass $w, string $code): \stdClass {
        foreach (powers::catalog($w->course->id) as $power) {
            if ($power->code === $code) {
                return $power;
            }
        }
        $this->fail('No power ' . $code);
    }

    /**
     * The character of a role in the course, fresh from the database.
     *
     * @param \stdClass $w
     * @param string $role
     * @return \stdClass
     */
    private function member(\stdClass $w, string $role): \stdClass {
        return engine::member(characters::get($w->users[$role]->id), $w->course->id);
    }

    public function test_catalog_is_made_once(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertCount(count(powers::CATALOG), powers::catalog($course->id));
        powers::catalog($course->id);
        $this->assertSame(count(powers::CATALOG), $DB->count_records(self::T . 'power', ['courseid' => $course->id]));
        $catalog = powers::catalog($course->id);
        $first = reset($catalog);
        $this->assertSame('shield', $first->code);
        $this->assertSame(get_string('power_shield', 'local_oksigeniatesape'), powers::name($first));
    }

    public function test_asking_spends_breath_and_no_gives_it_back(): void {
        $this->resetAfterTest();
        $w = $this->make_team();
        $hint = $this->power($w, 'hint');
        $request = powers::request($w->course->id, $w->users['sage']->id, $hint->id);
        $this->assertSame('pending', $request->status);
        $this->assertSame(engine::BREATH_MAX - $hint->cost, (int) $this->member($w, 'sage')->breath);
        $this->assertSame(1, powers::pending_count($w->course->id));
        try {
            powers::request($w->course->id, $w->users['sage']->id, $hint->id);
            $this->fail('Asked twice');
        } catch (\moodle_exception $e) {
            $this->assertSame('powerpending', $e->errorcode);
        }
        powers::decide((int) $request->id, false, $w->teacher->id);
        $this->assertSame(engine::BREATH_MAX, (int) $this->member($w, 'sage')->breath);
        $this->assertSame(0, powers::pending_count($w->course->id));
        $this->expectException(\moodle_exception::class);
        powers::decide((int) $request->id, true, $w->teacher->id);
    }

    public function test_who_can_ask(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_team();
        $errors = function (callable $ask): string {
            try {
                $ask();
            } catch (\moodle_exception $e) {
                return $e->errorcode;
            }
            return '';
        };
        // Not their role.
        $this->assertSame('powernotyours', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['sage']->id,
            $this->power($w, 'shield')->id,
            $w->users['healer']->id
        )));
        // Too low a level.
        $DB->set_field(self::T . 'char', 'level', 1, ['userid' => $w->users['healer']->id]);
        $this->assertSame('powerlocked', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'revive')->id
        )));
        // Not enough breath.
        $DB->set_field(self::T . 'member', 'breath', 1, ['id' => $this->member($w, 'protector')->id]);
        $this->assertSame('notenoughbreath', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['protector']->id,
            $this->power($w, 'wall')->id
        )));
        // A teammate is needed, and not someone outside the team.
        $outsider = $this->getDataGenerator()->create_and_enrol($w->course, 'student');
        characters::create($outsider->id, 'healer', 'f');
        engine::member(characters::get($outsider->id), $w->course->id);
        $this->assertSame('powertarget', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'breather')->id,
            $outsider->id
        )));
        $this->assertSame('powernoteam', $errors(fn() => powers::request(
            $w->course->id,
            $outsider->id,
            $this->power($w, 'breather')->id,
            $w->users['sage']->id
        )));
        // Worn out, nothing.
        engine::energy($this->member($w, 'scout'), -10, 'commitment', 0);
        $this->assertSame('fallen', $this->member($w, 'scout')->status);
        $this->assertSame('powerwornout', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['scout']->id,
            $this->power($w, 'lookout')->id
        )));
        // Not on oneself, and revive only on someone worn out.
        $this->assertSame('powertarget', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'breather')->id,
            $w->users['healer']->id
        )));
        $DB->set_field(self::T . 'char', 'level', 5, ['userid' => $w->users['healer']->id]);
        $this->assertSame('powertarget', $errors(fn() => powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'revive')->id,
            $w->users['sage']->id
        )));
        $this->assertSame(0, powers::pending_count($w->course->id));
    }

    public function test_breather_cheer_and_revive(): void {
        $this->resetAfterTest();
        $w = $this->make_team();
        // The sage loses energy; the breather gives it back.
        engine::energy($this->member($w, 'sage'), -4, 'commitment', $w->teacher->id);
        $request = powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'breather')->id,
            $w->users['sage']->id
        );
        powers::decide((int) $request->id, true, $w->teacher->id);
        $this->assertSame(engine::ENERGY_MAX - 4 + powers::BREATHER_ENERGY, (int) $this->member($w, 'sage')->energy);

        // Cheer gives breath to the others.
        engine::breath($this->member($w, 'healer'), 5, 'manual', $w->teacher->id);
        powers::request(
            $w->course->id,
            $w->users['protector']->id,
            $this->power($w, 'shield')->id,
            $w->users['sage']->id
        );
        $this->assertSame(engine::BREATH_MAX - 2, (int) $this->member($w, 'protector')->breath);
        $request = powers::request($w->course->id, $w->users['healer']->id, $this->power($w, 'cheer')->id);
        powers::decide((int) $request->id, true, $w->teacher->id);
        $this->assertSame(engine::BREATH_MAX, (int) $this->member($w, 'protector')->breath);
        $this->assertSame(engine::BREATH_MAX - 4, (int) $this->member($w, 'healer')->breath);

        // The scout falls; revive brings them back and the team gets knowledge.
        engine::energy($this->member($w, 'scout'), -10, 'commitment', $w->teacher->id);
        engine::breath($this->member($w, 'healer'), 5, 'manual', $w->teacher->id);
        $saber = (int) characters::get($w->users['sage']->id)->saber;
        $request = powers::request(
            $w->course->id,
            $w->users['healer']->id,
            $this->power($w, 'revive')->id,
            $w->users['scout']->id
        );
        powers::decide((int) $request->id, true, $w->teacher->id);
        $scout = $this->member($w, 'scout');
        $this->assertSame('active', $scout->status);
        $this->assertSame(engine::RECOVERED_ENERGY, (int) $scout->energy);
        $this->assertSame($saber + powers::RESCUE_SABER, (int) characters::get($w->users['sage']->id)->saber);
    }

    public function test_shield_takes_half_of_the_loss(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_team();
        $request = powers::request(
            $w->course->id,
            $w->users['protector']->id,
            $this->power($w, 'shield')->id,
            $w->users['sage']->id
        );
        // Pending: it does not protect yet.
        engine::energy($this->member($w, 'sage'), -1, 'commitment', $w->teacher->id);
        $this->assertSame(engine::ENERGY_MAX - 1, (int) $this->member($w, 'sage')->energy);

        powers::decide((int) $request->id, true, $w->teacher->id);
        $entry = engine::energy($this->member($w, 'sage'), -3, 'commitment', $w->teacher->id);
        $this->assertSame('shield', $entry->reason);
        $this->assertSame(3, (int) $entry->capped);
        $this->assertSame(engine::ENERGY_MAX - 1, (int) $this->member($w, 'sage')->energy);
        $this->assertSame(engine::ENERGY_MAX - 2, (int) $this->member($w, 'protector')->energy);
        $this->assertSame('used', $DB->get_field(self::T . 'powerreq', 'status', ['id' => $request->id]));

        // Used: the next loss is not stopped.
        engine::energy($this->member($w, 'sage'), -1, 'commitment', $w->teacher->id);
        $this->assertSame(engine::ENERGY_MAX - 2, (int) $this->member($w, 'sage')->energy);
    }

    public function test_wall_stops_the_team_today(): void {
        global $DB;
        $this->resetAfterTest();
        $w = $this->make_team();
        $wall = $this->power($w, 'wall');
        powers::save($w->course->id, ['id' => $wall->id, 'autoapprove' => 1]);
        $request = powers::request($w->course->id, $w->users['protector']->id, $wall->id);
        $this->assertSame('approved', $request->status);
        foreach (['protector', 'healer', 'sage', 'scout'] as $role) {
            $entry = engine::energy($this->member($w, $role), -2, 'commitment', $w->teacher->id);
            $this->assertSame('wall', $entry->reason);
            $this->assertSame(engine::ENERGY_MAX, (int) $this->member($w, $role)->energy);
        }
        // Tomorrow it is gone.
        $tomorrow = time() + DAYSECS;
        engine::energy($this->member($w, 'sage'), -2, 'commitment', $w->teacher->id, 0, $tomorrow);
        $this->assertSame(engine::ENERGY_MAX - 2, (int) $this->member($w, 'sage')->energy);
        $this->assertSame(4, $DB->count_records(self::T . 'ledger', ['reason' => 'wall']));
    }

    public function test_teachers_own_privilege(): void {
        $this->resetAfterTest();
        $w = $this->make_team();
        $own = powers::save($w->course->id, ['name' => 'Choose the next reading', 'role' => 'sage', 'cost' => 9,
            'minlevel' => 0]);
        $this->assertSame('own', $own->code);
        $this->assertEquals(engine::BREATH_MAX, $own->cost);
        $this->assertEquals(1, $own->minlevel);
        $this->assertSame('Choose the next reading', powers::name($own));
        $list = powers::for_student(characters::get($w->users['sage']->id), $this->member($w, 'sage'));
        $this->assertContains('Choose the next reading', array_column($list['powers'], 'name'));
        $list = powers::for_student(characters::get($w->users['healer']->id), $this->member($w, 'healer'));
        $this->assertNotContains('Choose the next reading', array_column($list['powers'], 'name'));

        // Off: nobody sees it.
        powers::save($w->course->id, ['id' => $own->id, 'enabled' => 0]);
        $list = powers::for_student(characters::get($w->users['sage']->id), $this->member($w, 'sage'));
        $this->assertNotContains('Choose the next reading', array_column($list['powers'], 'name'));
        $this->expectException(\invalid_parameter_exception::class);
        powers::save($w->course->id, ['name' => '  ']);
    }
}
