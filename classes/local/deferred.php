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
 * The turn in deferred: whoever follows the expedition from far (home, the hospital) and was not in a battle of the class
 * plays its questions afterwards, when they can, in the next days. What they get right adds to the class: a gem for each
 * one, a thread less for the Embrollo in the rematch if the class lost, and a line on the class screen: from far came the
 * strength of… Once a battle.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class deferred {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Days after a battle in which it can be played in deferred. */
    public const DAYS = 3;

    /** @var int Questions of the battle played in deferred. */
    public const QUESTIONS = 4;

    /** @var int Most gems a turn in deferred gives. */
    public const GEMS = 3;

    /** @var float Share of the threads of the Embrollo each right answer takes off for the rematch. */
    public const THREADS = 0.05;

    /**
     * The battle a student who follows from far can play in deferred: the last one of the class in the last days that
     * they were not in and did not play yet.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param \stdClass $member
     * @param int|null $now
     * @return array|null liveid, outcome, final, questions (id, text, options).
     */
    public static function pending(int $courseid, \stdClass $char, \stdClass $member, ?int $now = null): ?array {
        global $DB;
        $now = $now ?? time();
        if (empty($member->remote)) {
            return null;
        }
        $where = "courseid = ? AND kind = 'battle' AND timeend > ?";
        $battles = $DB->get_records_select(
            self::T . 'live',
            $where,
            [$courseid, $now - self::DAYS * DAYSECS],
            'timeend DESC',
            '*',
            0,
            3
        );
        foreach ($battles as $live) {
            $state = json_decode((string) $live->state, true) ?: [];
            if (!in_array($state['outcome'] ?? '', ['won', 'lost'], true) || !empty($state['rehearsal'])) {
                continue;
            }
            $joined = $DB->record_exists(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $char->id]);
            $played = $DB->record_exists_select(self::T . 'found', "charid = ? AND kind = 'deferred' AND " .
                $DB->sql_like('code', '?'), [$char->id, $live->id . ':%']);
            if ($joined || $played) {
                continue;
            }
            $questions = [];
            foreach (array_slice((array) ($state['questions'] ?? []), 0, self::QUESTIONS) as $id) {
                $q = questions::get($courseid, (string) $id, (int) $live->id);
                if ($q) {
                    $questions[] = ['id' => (string) $q['id'], 'text' => (string) $q['text'], 'options' => $q['options']];
                }
            }
            if ($questions) {
                return ['liveid' => (int) $live->id, 'outcome' => (string) $state['outcome'], 'final' => !empty($state['final']),
                    'questions' => $questions];
            }
        }
        return null;
    }

    /**
     * A student plays a battle in deferred: what they got right adds to the class, once.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $liveid
     * @param int[] $answers Position of the option chosen for each question, in order.
     * @param int|null $now
     * @return array right, total, gems, helped (the Embrollo has threads less for the rematch).
     */
    public static function play(int $courseid, int $userid, int $liveid, array $answers, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $member = $char ? engine::member($char, $courseid, $now) : null;
        $pending = $member ? self::pending($courseid, $char, $member, $now) : null;
        if (!$pending || $pending['liveid'] !== $liveid) {
            throw new \moodle_exception('nodeferred', 'local_oksigeniatesape');
        }
        $right = 0;
        foreach ($pending['questions'] as $i => $question) {
            $q = questions::get($courseid, $question['id'], $liveid);
            $right += $q && isset($answers[$i]) && (int) $answers[$i] === (int) $q['correct'] ? 1 : 0;
        }
        $mission = missions::get($courseid);
        $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
            'missionid' => $mission ? (int) $mission->id : 0, 'kind' => 'deferred', 'code' => $liveid . ':' . $right,
            'timecreated' => $now]);
        $gems = 0;
        if ($right) {
            $entry = engine::gems($member, min($right, self::GEMS), 'deferred', 0, 0, $liveid, $now);
            $gems = $entry ? (int) $entry->amount : 0;
        }
        // Lost, the Embrollo escaped: what was right from far takes threads off it for the rematch.
        $helped = false;
        $region = regions::get($courseid);
        $saved = json_decode((string) ($region->battlestate ?? ''), true);
        if ($right && $pending['outcome'] === 'lost' && is_array($saved) && isset($saved['left'])) {
            $saved['left'] = max(0.05, (float) $saved['left'] - self::THREADS * $right);
            $DB->set_field(self::T . 'region', 'battlestate', json_encode($saved), ['courseid' => $courseid]);
            regions::forget();
            $helped = true;
        }
        return ['right' => $right, 'total' => count($pending['questions']), 'gems' => $gems, 'helped' => $helped];
    }

    /**
     * Who brought their strength from far in the last days, for the class screen: by the name the class sees.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array Each: name, right.
     */
    public static function from_far(int $courseid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $rows = $DB->get_records_sql(
            'SELECT f.id, f.code, c.userid, c.nickname
               FROM {' . self::T . 'found} f
               JOIN {' . self::T . "char} c ON c.id = f.charid
              WHERE f.courseid = ? AND f.kind = 'deferred' AND f.timecreated > ?
           ORDER BY f.timecreated",
            [$courseid, $now - self::DAYS * DAYSECS]
        );
        $region = regions::get($courseid);
        $out = [];
        foreach ($rows as $row) {
            $right = (int) (explode(':', (string) $row->code)[1] ?? 0);
            $user = \core_user::get_user((int) $row->userid);
            if ($right && $user && $region) {
                $out[] = ['name' => view::display_name($user, $row, $region), 'right' => $right];
            }
        }
        return $out;
    }
}
