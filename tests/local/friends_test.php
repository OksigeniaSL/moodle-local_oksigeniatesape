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
 * Tests for the friends of the expedition: what each one tells, by the pages given back from what is theirs, and their
 * presents, once.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_oksigeniatesape\local\friends
 */
#[\PHPUnit\Framework\Attributes\CoversClass(friends::class)]
final class friends_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int A fixed moment. */
    private const NOW = 1791720000;

    public function test_a_friend_tells_more_with_more_of_their_pages_and_gives_once(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        regions::save($course->id, ['enabled' => 1]);
        $mission = missions::install($course->id, 'livingthings', 'valley', 'es', self::NOW);
        $user = $gen->create_and_enrol($course, 'student');
        $char = characters::create($user->id, 'sage', 'f');
        engine::member($char, $course->id, self::NOW);
        $page = fn(string $code) => $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id,
            'courseid' => $course->id, 'missionid' => $mission->id, 'kind' => 'page', 'code' => $code,
            'timecreated' => self::NOW]);
        $buho = fn() => array_values(array_filter(
            view::world($course->id, $user->id)['mission']['friends'],
            fn($f) => $f['code'] === 'buho'
        ))[0];
        $this->assertSame([0, 5], [$buho()['pages'], $buho()['of']]);
        $this->assertSame('', $buho()['talks'][0]['text']);
        // One page of the owl's: not yet. The animal of the trail of the owl comes at random: another of them, as it comes
        // to who plays the scene again, counts as the same one.
        $page('caracol');
        $page('culebra');
        $this->assertSame(1, $buho()['pages']);
        try {
            friends::listen($course->id, $char, 'buho', 2, self::NOW);
            $this->fail('A talk with too few pages');
        } catch (\moodle_exception $e) {
            $this->assertSame('nofriendtalk', $e->errorcode);
        }
        // Two, and the first talk opens; listened to, its gem, once. A page of someone else's does not count.
        $page('grupos');
        $page('vertebrados');
        $talk = $buho()['talks'][0];
        $this->assertTrue($talk['open']);
        $this->assertFalse($talk['read']);
        $this->assertNotSame('', $talk['text']);
        $gems = (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]);
        $this->assertSame(1, friends::listen($course->id, $char, 'buho', 2, self::NOW)['gems']);
        $this->assertSame(0, friends::listen($course->id, $char, 'buho', 2, self::NOW)['gems']);
        $this->assertSame($gems + 1, (int) $DB->get_field(self::T . 'char', 'gems', ['id' => $char->id]));
        $this->assertTrue($buho()['talks'][0]['read']);
        $this->assertFalse($buho()['talks'][1]['open']);
        // A talk that is not there.
        $this->expectException(\invalid_parameter_exception::class);
        friends::listen($course->id, $char, 'buho', 3, self::NOW);
    }
}
