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
 * The whole pilot mission played by a made-up class, from the first scene to the final battle, and what the class ends
 * with: lanterns, badge, final object, evidence in the competencies, gradebook and report.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class mission_complete_test extends \advanced_testcase {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * Plays a scene for a student, well or with mix-ups.
     *
     * @param int $courseid
     * @param \stdClass $user
     * @param \stdClass $scene
     * @param string|null $tangled Seed of the mix-ups; null to play it well.
     * @return array The result of the play.
     */
    private function play(int $courseid, \stdClass $user, \stdClass $scene, ?string $tangled = null): array {
        [$items, $roles, $flags, $role] = scenes::context(characters::get((int) $user->id), $courseid);
        $definition = json_decode($scene->definition, true);
        $events = $tangled === null ? autoplayer::play($definition, $items, $roles, $flags, $role) :
            autoplayer::tangled($definition, $tangled, 100, $items, $roles, $flags, $role);
        return scenes::play($courseid, (int) $user->id, (int) $scene->id, $events, null);
    }

    /**
     * The places a student has lit.
     *
     * @param \stdClass $mission
     * @param \stdClass $user
     * @return string[]
     */
    private function lit(\stdClass $mission, \stdClass $user): array {
        global $DB;
        $where = 'charid = ? AND missionid = ? AND kind = ?';
        $params = [characters::get((int) $user->id)->id, $mission->id, 'lantern'];
        return $DB->get_fieldset_select(self::T . 'found', 'code', $where, $params);
    }

    public function test_a_class_plays_the_whole_mission(): void {
        global $CFG, $DB;
        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        $class = demo::create_class('WHOLE', 8, 4, 'storyheroes', 0, 7, 'livingthings');
        $courseid = (int) $class->course->id;
        $mission = missions::get($courseid);
        // The teacher turns on the badge and the gradebook.
        $badgeid = badges::find($courseid, $mission);
        (new \core_badges\badge($badgeid))->set_status(BADGE_STATUS_ACTIVE);
        regions::save($courseid, ['gradebook' => 1]);
        $chapters = array_values($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder'));
        $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code');
        $first = $class->students[0];
        // Every student, every scene of their step, chapter after chapter. The first one gets each scene wrong first, and
        // plays it again until it comes out well.
        foreach ($class->students as $i => $user) {
            $key = ['charid' => characters::get((int) $user->id)->id, 'courseid' => $courseid];
            $step = (int) $DB->get_field(self::T . 'member', 'step', $key);
            foreach ($chapters as $chapter) {
                $mine = array_values(array_filter($scenes, fn($sc) => $sc->chapterid == $chapter->id && empty($sc->quest)));
                foreach (scenes::for_step($mine, $step) as $scene) {
                    if ($i === 0) {
                        $this->play($courseid, $user, $scene, 'wrong-' . $scene->code);
                    }
                    $outcome = $this->play($courseid, $user, $scene)['outcome'];
                    $this->assertSame('success', $outcome, $scene->code . ', step ' . $step);
                }
            }
            // Every place but the one of the final battle.
            $this->assertCount(count($chapters) - 1, $this->lit($mission, $user), $user->username);
        }
        // Quests give gems and pages, never saber.
        $saber = (int) characters::get((int) $first->id)->saber;
        foreach (scenes::quests(array_values($scenes)) as $quest) {
            $this->play($courseid, $first, $quest);
        }
        $this->assertSame($saber, (int) characters::get((int) $first->id)->saber);
        // The final battle, won with the phones of the whole class.
        $live = live::start($courseid, 'phones', 6, 0, null, true);
        $members = [];
        foreach ($class->students as $user) {
            $members[(int) $user->id] = engine::member(characters::get((int) $user->id), $courseid);
            live::join($live, $members[(int) $user->id], (int) $user->id);
        }
        for ($round = 1; $round <= 6; $round++) {
            $live = live::next($live);
            $right = (int) json_decode($live->state, true)['rounds'][$live->round]['correct'];
            foreach ($members as $userid => $member) {
                live::answer($live, $member, $round, $right);
            }
            $live = live::reveal($live);
        }
        $live = live::finish($live, (int) $class->teacher->id);
        $this->assertSame('won', json_decode($live->state, true)['outcome']);
        // What the class ends with: every lantern, the badge, the object of the end and evidence in the competencies.
        $lens = (int) $DB->get_field(self::T . 'item', 'id', ['code' => 'naturalistlens']);
        foreach ($class->students as $user) {
            $this->assertCount(count($chapters), $this->lit($mission, $user), $user->username);
            $this->assertTrue((new \core_badges\badge($badgeid))->is_issued($user->id), $user->username);
            $key = ['charid' => characters::get((int) $user->id)->id, 'itemid' => $lens, 'courseid' => $courseid];
            $this->assertTrue($DB->record_exists(self::T . 'inventory', $key), $user->username);
        }
        $classlit = $DB->count_records_select(self::T . 'chapter', 'missionid = ? AND litat > 0', [$mission->id]);
        $this->assertSame(count($chapters), $classlit);
        [$insql, $params] = $DB->get_in_or_equal(array_map(fn($u) => (int) $u->id, $class->students));
        $evidence = $DB->count_records_sql('SELECT COUNT(1) FROM {competency_evidence} e
            JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid WHERE uc.userid ' . $insql, $params);
        $this->assertGreaterThan(50, $evidence);
        // The teacher rates: to the competencies and to the gradebook. And the report has the whole class.
        $this->setUser($class->teacher);
        competencies::rate($courseid, (int) $first->id, 'SV-01', 4);
        $item = \grade_item::fetch(['courseid' => $courseid, 'idnumber' => 'tesape_livingthings_SV-01']);
        $this->assertEquals(4, $item->get_grade($first->id, false)->finalgrade);
        $this->assertCount(count($class->students), report::rows($courseid));
    }
}
