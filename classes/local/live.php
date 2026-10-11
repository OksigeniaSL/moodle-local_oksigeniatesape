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
 * Live sessions of the class screen: the battle against the Embrollo, played with the phones of the students or, without
 * devices, by teams answering aloud while the teacher marks who got it right.
 *
 * Adapted from the live sessions of Oksigenia Classtools: the state of a session lives in one row and is read by
 * polling; every phone that comes in and every answer is a row, and a unique index keeps anybody from answering twice.
 * Unlike there, players always have their Moodle account (the battle gives saber), the team comes from the groups of
 * the course, and the count of each round is kept when it is revealed instead of being read again on every poll.
 *
 * Each right answer takes life from the Embrollo; a wrong one takes nothing from anybody. If the class wins, saber and
 * gems for everybody who played, and the object of the mission (or more gems) for the team that helped most.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class live {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Seconds without a sign of life of the class screen before the session is over. */
    public const IDLE = 900;

    /** @var int Seconds that the outcome of a battle is still shown after it ends. */
    public const SHOWN = 300;

    /** @var int The class screen refreshes the sign of life at most this often (seconds). */
    private const HEARTBEAT = 10;

    /** @var int[] Questions a battle can have. */
    public const COUNTS = [4, 6, 8, 10];

    /** @var int[] Seconds a question can last (0: until the teacher reveals it). */
    public const SECONDS = [0, 20, 30, 45, 60];

    /** @var float Share of the right answers the class needs to beat the Embrollo. */
    public const TO_WIN = 0.5;

    /** @var int Saber for each player when the class wins. */
    public const WIN_SABER = 10;

    /** @var int Gems for each player when the class wins. */
    public const WIN_GEMS = 2;

    /** @var int More gems for each member of the team that helped most, when the mission has no object for it. */
    public const TEAM_GEMS = 2;

    /**
     * Opens a battle in a course and closes the one that was open, if any.
     *
     * @param int $courseid
     * @param string $mode phones or aloud.
     * @param int $count Questions.
     * @param int $secs Seconds per question (0: no limit).
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function start(int $courseid, string $mode, int $count, int $secs, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!in_array($mode, ['phones', 'aloud'], true) || !in_array($count, self::COUNTS) || !in_array($secs, self::SECONDS)) {
            throw new \invalid_parameter_exception('Not a battle that can be played');
        }
        $questions = questions::pick_many($courseid, $count);
        if (!$questions) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $DB->set_field_select(self::T . 'live', 'timeend', $now, 'courseid = ? AND timeend = 0', [$courseid]);
        $state = ['mode' => $mode, 'secs' => $secs, 'questions' => $questions, 'rounds' => [], 'open' => false,
            'closesat' => 0, 'hp' => 0, 'maxhp' => 0, 'outcome' => '', 'rewarded' => []];
        $record = (object) ['courseid' => $courseid, 'kind' => 'battle', 'state' => json_encode($state), 'round' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'timeend' => 0];
        $id = $DB->insert_record(self::T . 'live', $record);
        return $DB->get_record(self::T . 'live', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * Opens a vote of the class with the phones, for a scene played on the class screen, and closes the session that was
     * open, if any.
     *
     * @param int $courseid
     * @param int $sceneid
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function start_vote(int $courseid, int $sceneid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        scenes::get($courseid, $sceneid);
        $DB->set_field_select(self::T . 'live', 'timeend', $now, 'courseid = ? AND timeend = 0', [$courseid]);
        $state = ['mode' => 'phones', 'secs' => 0, 'sceneid' => $sceneid, 'questions' => [], 'rounds' => [], 'open' => false,
            'closesat' => 0, 'hp' => 0, 'maxhp' => 0, 'outcome' => '', 'rewarded' => []];
        $record = (object) ['courseid' => $courseid, 'kind' => 'vote', 'state' => json_encode($state), 'round' => 0,
            'timecreated' => $now, 'timemodified' => $now, 'timeend' => 0];
        $id = $DB->insert_record(self::T . 'live', $record);
        return $DB->get_record(self::T . 'live', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A decision of the scene put to the vote: the class screen sends what it says and its options.
     *
     * @param \stdClass $live
     * @param string $text
     * @param string[] $options Two to six.
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function vote(\stdClass $live, string $text, array $options, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        if ($live->kind !== 'vote' || count($options) < 2 || count($options) > 6) {
            throw new \invalid_parameter_exception('Not a vote that can be held');
        }
        $round = (int) $live->round;
        if ($round > 0 && empty($state['rounds'][$round]['revealed'])) {
            $state = self::count($live, $state);
        }
        $round++;
        $options = array_values(array_map(fn($o) => clean_param((string) $o, PARAM_TEXT), $options));
        $question = ['id' => 'v' . $round, 'text' => clean_param($text, PARAM_TEXT), 'options' => $options];
        $state['rounds'][$round] = ['q' => $question['id'], 'options' => count($options), 'correct' => -1,
            'question' => $question, 'revealed' => false, 'answered' => 0, 'right' => 0, 'teams' => [], 'tally' => []];
        $state['open'] = true;
        $state['closesat'] = 0;
        return self::save($live, $state, $round, $now);
    }

    /**
     * Who came in with their phone, by character.
     *
     * @param \stdClass $live
     * @return int[]
     */
    public static function joined(\stdClass $live): array {
        global $DB;
        $charids = $DB->get_fieldset_select(self::T . 'livein', 'charid', 'liveid = ? AND round = 0', [$live->id]);
        return array_map('intval', $charids);
    }

    /**
     * The session open in a course, if any: not ended, and with the class screen alive.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function current(int $courseid, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $where = 'courseid = ? AND timeend = 0 AND timemodified > ?';
        $records = $DB->get_records_select(self::T . 'live', $where, [$courseid, $now - self::IDLE], 'id DESC', '*', 0, 1);
        return reset($records) ?: null;
    }

    /**
     * The session open in a course or, for a few minutes after it ends, the last one: so that phones and the class
     * screen can still show how it went.
     *
     * @param int $courseid
     * @param int|null $now
     * @return \stdClass|null
     */
    public static function recent(int $courseid, ?int $now = null): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $live = self::current($courseid, $now);
        if ($live) {
            return $live;
        }
        $params = [$courseid, $now - self::SHOWN];
        $records = $DB->get_records_select(self::T . 'live', 'courseid = ? AND timeend > ?', $params, 'timeend DESC', '*', 0, 1);
        $last = reset($records);
        return $last && self::state($last)['outcome'] !== '' ? $last : null;
    }

    /**
     * The class screen is still there.
     *
     * @param \stdClass $live
     * @param int $now
     */
    public static function heartbeat(\stdClass $live, int $now): void {
        global $DB;
        if ($now - $live->timemodified >= self::HEARTBEAT) {
            $DB->set_field(self::T . 'live', 'timemodified', $now, ['id' => $live->id]);
            $live->timemodified = $now;
        }
    }

    /**
     * A student comes into the session with their phone. Paused students do not play.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $userid
     * @param int|null $now
     */
    public static function join(\stdClass $live, \stdClass $member, int $userid, ?int $now = null): void {
        global $DB;
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        if ($DB->record_exists(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $member->charid])) {
            return;
        }
        $team = teams::of((int) $live->courseid, $userid);
        try {
            $record = (object) ['liveid' => $live->id, 'courseid' => $live->courseid, 'charid' => $member->charid,
                'round' => 0, 'team' => (int) ($team->id ?? 0), 'answer' => -1, 'timecreated' => $now ?? time()];
            $DB->insert_record(self::T . 'livein', $record);
        } catch (\dml_write_exception $e) {
            // Two taps at once: the first one counts.
            return;
        }
    }

    /**
     * A student answers the question of the round. The first answer stays.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $round
     * @param int $answer Index of the option, as shown.
     * @param int|null $now
     */
    public static function answer(\stdClass $live, \stdClass $member, int $round, int $answer, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        $joined = $DB->get_record(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $member->charid]);
        if (!$joined || $round !== (int) $live->round || !self::is_open($state, $now)) {
            throw new \moodle_exception('roundclosed', 'local_oksigeniatesape');
        }
        if ($answer < 0 || $answer >= (int) ($state['rounds'][$round]['options'] ?? 0)) {
            throw new \invalid_parameter_exception('Not an option of the question');
        }
        try {
            $record = (object) ['liveid' => $live->id, 'courseid' => $live->courseid, 'charid' => $member->charid,
                'round' => $round, 'team' => $joined->team, 'answer' => $answer, 'timecreated' => $now];
            $DB->insert_record(self::T . 'livein', $record);
        } catch (\dml_write_exception $e) {
            return;
        }
    }

    /**
     * The next question. The first one sets the life of the Embrollo, from the players there are.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function next(\stdClass $live, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($state['outcome'] !== '' || $round >= count($state['questions'])) {
            throw new \moodle_exception('battleover', 'local_oksigeniatesape');
        }
        if ($round > 0 && empty($state['rounds'][$round]['revealed'])) {
            $state = self::count($live, $state);
        }
        if ($round === 0) {
            $players = $state['mode'] === 'aloud' ? count(self::teams((int) $live->courseid)) : self::players($live);
            $state['maxhp'] = max(1, (int) round($players * count($state['questions']) * self::TO_WIN));
            $state['hp'] = $state['maxhp'];
        }
        $round++;
        $question = questions::get((int) $live->courseid, $state['questions'][$round - 1], self::seed($live, $round));
        // The question stays in the state, so that the phones do not read the mission on every poll.
        $state['rounds'][$round] = ['q' => $question['id'], 'options' => count($question['options']),
            'correct' => $question['correct'], 'question' => array_diff_key($question, ['chapter' => 1, 'objective' => 1]),
            'revealed' => false, 'answered' => 0, 'right' => 0, 'teams' => []];
        $state['open'] = true;
        $state['closesat'] = $state['secs'] ? $now + $state['secs'] + 1 : 0;
        return self::save($live, $state, $round, $now);
    }

    /**
     * The answer of the round: no more answers, the right ones take life from the Embrollo.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function reveal(\stdClass $live, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($round < 1 || !empty($state['rounds'][$round]['revealed'])) {
            return $live;
        }
        $state = self::count($live, $state);
        return self::save($live, $state, $round, $now);
    }

    /**
     * Without devices: a team got the question of the round right, or not. Once the answer is revealed.
     *
     * @param \stdClass $live
     * @param int $groupid
     * @param bool $right
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function mark(\stdClass $live, int $groupid, bool $right, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($state['mode'] !== 'aloud' || empty($state['rounds'][$round]['revealed']) || $state['outcome'] !== '') {
            throw new \moodle_exception('roundclosed', 'local_oksigeniatesape');
        }
        if (!array_key_exists($groupid, self::teams((int) $live->courseid))) {
            throw new \invalid_parameter_exception('Not a team of the course');
        }
        $before = !empty($state['rounds'][$round]['teams'][$groupid]);
        if ($before !== $right) {
            $state['rounds'][$round]['teams'][$groupid] = $right ? 1 : 0;
            $state['rounds'][$round]['right'] += $right ? 1 : -1;
            $state['hp'] = min($state['maxhp'], max(0, $state['hp'] + ($right ? -1 : 1)));
        }
        return self::save($live, $state, $round, $now);
    }

    /**
     * The end of the battle: won if the Embrollo has no life left. Winning gives saber and gems to everybody who
     * played, and more gems to the team that helped most; losing takes nothing from anybody.
     *
     * @param \stdClass $live
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass The session, ended.
     */
    public static function finish(\stdClass $live, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($state['outcome'] !== '') {
            return $live;
        }
        if ($round > 0 && empty($state['rounds'][$round]['revealed'])) {
            $state = self::count($live, $state);
        }
        $state['open'] = false;
        if ($live->kind === 'vote') {
            $state['outcome'] = 'done';
        } else {
            $state['outcome'] = $round > 0 && $state['hp'] <= 0 ? 'won' : 'lost';
        }
        $transaction = $DB->start_delegated_transaction();
        if ($state['outcome'] === 'won') {
            $state['rewarded'] = self::reward($live, $state, $actorid, $now);
        }
        $live = self::save($live, $state, $round, $now);
        $DB->set_field(self::T . 'live', 'timeend', $now, ['id' => $live->id]);
        $transaction->allow_commit();
        $live->timeend = $now;
        return $live;
    }

    /**
     * What the class screen shows: the question with its answer once revealed, who is in, how many answered, the life
     * of the Embrollo, the teams and the outcome.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return array
     */
    public static function board_view(\stdClass $live, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        $players = $DB->get_records_menu(self::T . 'livein', ['liveid' => $live->id, 'round' => 0], '', 'charid, team');
        $joined = [];
        foreach ($players as $team) {
            $joined[$team] = ($joined[$team] ?? 0) + 1;
        }
        $teams = [];
        foreach (self::teams((int) $live->courseid) as $group) {
            $teams[] = ['id' => (int) $group->id, 'name' => format_string($group->name), 'joined' => $joined[$group->id] ?? 0,
                'right' => array_sum(array_map(fn($r) => (int) ($r['teams'][$group->id] ?? 0), $state['rounds'])),
                'mark' => (int) ($state['rounds'][$round]['teams'][$group->id] ?? -1)];
        }
        $view = self::common($live, $state, $now) + ['joined' => count($players), 'teams' => $teams, 'answered' => 0];
        if ($round > 0) {
            $info = $state['rounds'][$round];
            $view['answered'] = $info['revealed'] ? $info['answered'] :
                $DB->count_records(self::T . 'livein', ['liveid' => $live->id, 'round' => $round]);
            $view['question'] = self::question($info);
            $view['right'] = (int) $info['right'];
            if ($info['revealed'] && $live->kind === 'vote') {
                $view['tally'] = $info['tally'];
            }
        }
        $view['rewarded'] = count($state['rewarded']);
        return $view;
    }

    /**
     * What the phone of a student shows: never the right answer before it is revealed.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int|null $now
     * @return array
     */
    public static function device_view(\stdClass $live, \stdClass $member, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        $conditions = ['liveid' => $live->id, 'charid' => $member->charid];
        $mine = $DB->get_records_menu(self::T . 'livein', $conditions, '', 'round, answer');
        $view = self::common($live, $state, $now) + ['joined' => array_key_exists(0, $mine), 'mine' => -1];
        if ($round > 0) {
            $info = $state['rounds'][$round];
            $view['mine'] = (int) ($mine[$round] ?? -1);
            $view['question'] = self::question($info);
            if ($info['revealed'] && $live->kind === 'vote') {
                $view['tally'] = $info['tally'];
            }
        }
        if ($state['outcome'] === 'won') {
            $view['prize'] = $state['rewarded'][$member->charid] ?? null;
        }
        $view['preview'] = self::glance($live, $state, $member, array_key_exists(0, $mine), $now);
        return array_filter($view, fn($value) => $value !== null);
    }

    /**
     * The glance (a power of the role of clues): between two questions of a battle, the next one shows on the phone
     * of the student before anybody else sees it. Using it is kept as a row of the round, in negative, so that it
     * stays shown until the question opens; the request is then used.
     *
     * @param \stdClass $live
     * @param array $state
     * @param \stdClass $member
     * @param bool $joined
     * @param int $now
     * @return array|null text and options of the next question.
     */
    private static function glance(\stdClass $live, array $state, \stdClass $member, bool $joined, int $now): ?array {
        global $DB;
        $round = (int) $live->round;
        $between = $round === 0 || !empty($state['rounds'][$round]['revealed']);
        if ($live->kind !== 'battle' || !$joined || $state['outcome'] !== '' || !$between || $round >= count($state['questions'])) {
            return null;
        }
        $key = ['liveid' => $live->id, 'round' => -($round + 1), 'charid' => $member->charid];
        if (!$DB->record_exists(self::T . 'livein', $key)) {
            $request = powers::glance($member, $now);
            if (!$request) {
                return null;
            }
            try {
                $row = $key + ['courseid' => $live->courseid, 'team' => 0, 'answer' => -1, 'timecreated' => $now];
                $DB->insert_record(self::T . 'livein', (object) $row);
            } catch (\dml_write_exception $e) {
                // Two polls at once: the first one counts.
                return null;
            }
            $DB->set_field(self::T . 'powerreq', 'status', 'used', ['id' => $request->id]);
        }
        $question = questions::get((int) $live->courseid, $state['questions'][$round], self::seed($live, $round + 1));
        return ['text' => $question['text'], 'options' => $question['options']];
    }

    /**
     * Deletes the sessions of more than a day ago and what was answered in them.
     *
     * @param int|null $now
     * @return int Sessions deleted.
     */
    public static function cleanup(?int $now = null): int {
        global $DB;
        $old = $DB->get_fieldset_select(self::T . 'live', 'id', 'timecreated < ?', [($now ?? time()) - DAYSECS]);
        foreach (array_chunk($old, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $DB->delete_records_select(self::T . 'livein', "liveid $insql", $params);
            $DB->delete_records_select(self::T . 'live', "id $insql", $params);
        }
        return count($old);
    }

    /**
     * What the class screen and the phones both show.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return array
     */
    private static function common(\stdClass $live, array $state, int $now): array {
        $round = (int) $live->round;
        return [
            'id' => (int) $live->id,
            'kind' => $live->kind,
            'mode' => $state['mode'],
            'round' => $round,
            'total' => count($state['questions']),
            'open' => self::is_open($state, $now),
            'left' => $state['closesat'] ? max(0, $state['closesat'] - 1 - $now) : -1,
            'revealed' => $round > 0 && !empty($state['rounds'][$round]['revealed']),
            'hp' => (int) $state['hp'],
            'maxhp' => (int) $state['maxhp'],
            'outcome' => $state['outcome'],
        ];
    }

    /**
     * The question of a round, with the right option and why only once revealed.
     *
     * @param array $info The round, from the state.
     * @return array
     */
    private static function question(array $info): array {
        $question = $info['question'];
        if (empty($info['revealed'])) {
            unset($question['correct'], $question['why']);
        }
        return $question;
    }

    /**
     * Closes the round and keeps its count; on phones, the right answers take life from the Embrollo.
     *
     * @param \stdClass $live
     * @param array $state
     * @return array The state.
     */
    private static function count(\stdClass $live, array $state): array {
        global $DB;
        $round = (int) $live->round;
        $info = $state['rounds'][$round];
        $answers = $DB->get_records(self::T . 'livein', ['liveid' => $live->id, 'round' => $round], '', 'id, team, answer');
        $info['answered'] = count($answers);
        if ($live->kind === 'vote') {
            $info['tally'] = array_fill(0, (int) $info['options'], 0);
            foreach ($answers as $answer) {
                $info['tally'][(int) $answer->answer]++;
            }
        } else if ($state['mode'] === 'phones') {
            foreach ($answers as $answer) {
                if ((int) $answer->answer === (int) $info['correct']) {
                    $info['right']++;
                    $info['teams'][$answer->team] = ($info['teams'][$answer->team] ?? 0) + 1;
                }
            }
            $state['hp'] = max(0, $state['hp'] - $info['right']);
        }
        $info['revealed'] = true;
        $state['rounds'][$round] = $info;
        $state['open'] = false;
        $state['closesat'] = 0;
        return $state;
    }

    /**
     * Saber and gems for everybody who played, and more gems for the team that helped most.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $actorid
     * @param int $now
     * @return array Character id => what they got.
     */
    private static function reward(\stdClass $live, array $state, int $actorid, int $now): array {
        global $DB;
        $courseid = (int) $live->courseid;
        if ($state['mode'] === 'phones') {
            $players = $DB->get_records_menu(self::T . 'livein', ['liveid' => $live->id, 'round' => 0], '', 'charid, team');
        } else {
            $players = [];
            foreach (array_keys(self::teams($courseid)) as $groupid) {
                foreach (teams::members($courseid, $groupid) as $member) {
                    $players[$member->charid] = $groupid;
                }
            }
        }
        $byteam = [];
        foreach ($state['rounds'] as $info) {
            foreach ($info['teams'] as $team => $right) {
                $byteam[$team] = ($byteam[$team] ?? 0) + $right;
            }
        }
        if ($state['mode'] === 'phones') {
            // With phones, what each team got right for each of its players who came in: a team does not help most
            // just for having more players in class that day.
            $sizes = array_count_values(array_map('intval', $players));
            foreach ($byteam as $team => $right) {
                $byteam[$team] = $right / ($sizes[$team] ?? 1);
            }
        }
        $best = $byteam ? max($byteam) : 0;
        // The object of the mission for the team that helped most; more gems if the mission has none, or for those
        // who already have it.
        $mission = missions::get($courseid);
        $code = (string) ($mission->data['battle']['reward'] ?? '');
        $item = $code ? ($mission->data['items'][$code] ?? []) : [];
        $itemid = $code ? missions::item_id($code, $item['name'] ?? null, $item['description'] ?? null, $mission->code) : 0;
        $rewarded = [];
        foreach ($players as $charid => $team) {
            $member = $DB->get_record(self::T . 'member', ['charid' => $charid, 'courseid' => $courseid]);
            if (!$member || $member->status === 'paused') {
                continue;
            }
            $isbest = $best > 0 && ($byteam[$team] ?? 0) == $best;
            $got = $isbest && $itemid ? engine::item($member, $itemid, 'battle', $actorid, 0, $now) : null;
            $gems = self::WIN_GEMS + ($isbest && !$got ? self::TEAM_GEMS : 0);
            $entry = engine::saber($member, self::WIN_SABER, 'battle', $actorid, 0, (int) $live->id, $now);
            engine::gems($member, $gems, 'battle', $actorid, 0, (int) $live->id, $now);
            $rewarded[$charid] = ['saber' => (int) ($entry->amount ?? 0), 'capped' => (int) ($entry->capped ?? 0),
                'gems' => $gems, 'best' => $isbest, 'item' => $got ? (string) ($item['name'] ?? $code) : ''];
        }
        return $rewarded;
    }

    /**
     * Players who came in with their phone.
     *
     * @param \stdClass $live
     * @return int
     */
    private static function players(\stdClass $live): int {
        global $DB;
        return $DB->count_records(self::T . 'livein', ['liveid' => $live->id, 'round' => 0]);
    }

    /**
     * Whether the round takes answers.
     *
     * @param array $state
     * @param int $now
     * @return bool
     */
    private static function is_open(array $state, int $now): bool {
        return $state['open'] && (!$state['closesat'] || $now < $state['closesat']);
    }

    /**
     * The order of the options of a round, the same on every screen.
     *
     * @param \stdClass $live
     * @param int $round
     * @return int
     */
    private static function seed(\stdClass $live, int $round): int {
        return (int) $live->id * 100 + $round;
    }

    /**
     * The state of a session.
     *
     * @param \stdClass $live
     * @return array
     */
    private static function state(\stdClass $live): array {
        return json_decode($live->state, true) ?: [];
    }

    /**
     * Saves the state and the round of a session.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $round
     * @param int $now
     * @return \stdClass The session.
     */
    private static function save(\stdClass $live, array $state, int $round, int $now): \stdClass {
        global $DB;
        $live->state = json_encode($state);
        $live->round = $round;
        $live->timemodified = $now;
        $DB->update_record(self::T . 'live', $live);
        return $live;
    }

    /**
     * The teams of a course: the groups of its grouping.
     *
     * @param int $courseid
     * @return array Group id => group.
     */
    private static function teams(int $courseid): array {
        $region = regions::get($courseid);
        return groups_get_all_groups($courseid, 0, (int) ($region->groupingid ?? 0));
    }
}
