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
 * A battle by turns (the knots of the tower) goes in turns with their times set here: the Embrollo announces a knot of
 * the mission and the team it goes for (intent), each role gets ready (prep), the knot falls and the class undoes its
 * lazada (strike), and what happened is shown (resolve) before the next turn starts by itself.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class live {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Seconds without a sign of life of the class screen before the phones stop seeing the session. */
    public const IDLE = 900;

    /** @var int Seconds the class screen can be away from its session and still find it (a school morning). */
    public const HOST_IDLE = 4 * HOURSECS;

    /** @var int Seconds that the outcome of a battle is still shown after it ends. */
    public const SHOWN = 300;

    /** @var int The class screen refreshes the sign of life at most this often (seconds). */
    private const HEARTBEAT = 10;

    /** @var int Days a battle is kept, only with how the class did: for who follows the expedition from far. */
    public const KEPT_DAYS = 8;

    /** @var int[] Questions a battle can have. */
    public const COUNTS = [4, 6, 8, 10];

    /** @var int[] Seconds a question can last (0: until the teacher reveals it). */
    public const SECONDS = [0, 20, 30, 45, 60];

    /** @var float Share of the right answers the class needs to beat the Embrollo: winning is not a given. */
    public const TO_WIN = 0.6;

    /** @var float Life the Embrollo gets back, as a share of the whole, after a round the class mostly got wrong. */
    public const COUNTER = 0.1;

    /** @var int Seconds less for each question once the Embrollo is down to half its life and tangles itself more. */
    public const PHASE_SECS = 10;

    /** @var string[] The battle token of each role: one for each player and battle, in an open round, with phones. */
    public const TOKENS = ['sage' => 'glance', 'scout' => 'trail', 'protector' => 'shield', 'healer' => 'cheer'];

    /** @var int Seconds the Embrollo takes to announce the knot of a turn and the team it goes for. */
    public const INTENT = 5;

    /** @var int Seconds to get ready before the knot falls: each role uses its verb. */
    public const PREP = 15;

    /** @var int Seconds what happened in a turn is shown before the next one starts by itself. */
    public const RESOLVE = 10;

    /** @var int Threads more the Embrollo loses when every lane of a knot is undone: the knot breaks. */
    public const BREAK_THREADS = 3;

    /** @var int Threads the Embrollo gets back when it pulls itself together and the class does not stop it. */
    public const RECOMPOSE = 2;

    /** @var int By turns, turns a verb rests after it is used before it can be used again (by the role in the team). */
    public const REST = 2;

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
     * @param bool $final The final battle of the mission: it gives what the mission saves for the end.
     * @param bool $turns By turns, with the knots of the mission, if it has them.
     * @return \stdClass The session.
     */
    public static function start(
        int $courseid,
        string $mode,
        int $count,
        int $secs,
        ?int $now = null,
        bool $final = false,
        bool $turns = false
    ): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!in_array($mode, ['phones', 'aloud'], true) || !in_array($count, self::COUNTS) || !in_array($secs, self::SECONDS)) {
            throw new \invalid_parameter_exception('Not a battle that can be played');
        }
        $plan = $turns ? knots::plan($courseid, $count, $final) : [];
        $questions = $plan ? array_column($plan, 1) : questions::pick_many($courseid, $count);
        if (!$questions) {
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        $DB->set_field_select(self::T . 'live', 'timeend', $now, 'courseid = ? AND timeend = 0', [$courseid]);
        $state = ['mode' => $mode, 'secs' => $secs, 'questions' => $questions, 'rounds' => [], 'open' => false,
            'closesat' => 0, 'hp' => 0, 'maxhp' => 0, 'outcome' => '', 'rewarded' => [], 'final' => $final];
        if ($plan) {
            // A rematch goes on where the last battle lost stopped: the knots broken do not come back, those left hanging
            // come first, and the Embrollo has the threads it escaped with.
            $saved = json_decode((string) (regions::get($courseid)->battlestate ?? ''), true);
            if (is_array($saved) && (bool) ($saved['final'] ?? false) === $final) {
                $plan = self::rematch($courseid, $count, $final, $saved) ?: $plan;
                $state['questions'] = array_column($plan, 1);
                $state['rematch'] = max(0.05, min(1, (float) ($saved['left'] ?? 1)));
            }
            // By turns: the candles of the tower, the teams tangled, the knots broken and those left hanging.
            $state['knots'] = array_column($plan, 0);
            $rules = knots::rules(missions::get($courseid));
            $state += ['candles' => $rules['candles'], 'maxcandles' => $rules['candles'], 'phases' => $rules['phases'],
                'tangled' => [], 'broken' => [], 'hanging' => [], 'dazed' => false];
        }
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
        scenes::playable($courseid, $sceneid);
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
            $state = self::count($live, $state, $now);
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
     * The member of a student in a course with the role of their character, in one query: the phones ask for it on
     * every poll of a battle. Null if they have no character.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $now
     * @return \stdClass|null
     */
    public static function player(int $userid, int $courseid, int $now): ?\stdClass {
        global $DB;
        $member = $DB->get_record_sql(
            'SELECT m.*, c.role
               FROM {' . self::T . 'member} m
               JOIN {' . self::T . 'char} c ON c.id = m.charid
              WHERE c.userid = ? AND m.courseid = ?',
            [$userid, $courseid]
        );
        if ($member) {
            return $member;
        }
        // The first time in the course.
        $char = characters::get($userid);
        if (!$char) {
            return null;
        }
        $member = engine::member($char, $courseid, $now);
        $member->role = $char->role;
        return $member;
    }

    /**
     * The session open in a course, if any: not ended, and with the class screen alive. For the class screen itself, also
     * one it left a while ago (the tab hidden, the computer of the projector asleep): it finds it again when it comes
     * back, and can go on with it or end it.
     *
     * @param int $courseid
     * @param int|null $now
     * @param bool $host For the class screen.
     * @return \stdClass|null
     */
    public static function current(int $courseid, ?int $now = null, bool $host = false): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $where = 'courseid = ? AND timeend = 0 AND timemodified > ?';
        $params = [$courseid, $now - ($host ? self::HOST_IDLE : self::IDLE)];
        $records = $DB->get_records_select(self::T . 'live', $where, $params, 'id DESC', '*', 0, 1);
        return reset($records) ?: null;
    }

    /**
     * The session open in a course or, for a few minutes after it ends, the last one: so that phones and the class
     * screen can still show how it went.
     *
     * @param int $courseid
     * @param int|null $now
     * @param bool $host For the class screen.
     * @return \stdClass|null
     */
    public static function recent(int $courseid, ?int $now = null, bool $host = false): ?\stdClass {
        global $DB;
        $now = $now ?? time();
        $live = self::current($courseid, $now, $host);
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
        // Paused after coming in: no more answers, as when coming in.
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $joined = $DB->get_record(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $member->charid]);
        if (!$joined || $round !== (int) $live->round || !self::is_open($state, $now)) {
            throw new \moodle_exception('roundclosed', 'local_oksigeniatesape');
        }
        $info = $state['rounds'][$round] ?? [];
        $options = (int) ($info['lanes'][(int) $joined->team]['options'] ?? $info['options'] ?? 0);
        if ($answer < 0 || $answer >= $options) {
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
     * By turns, what a player did in the lane of their team when it is not one to choose: the server says whether it
     * undoes it, and keeps it as their answer (1: it does, 0: not). Sorting counts only from the player who sorts for the
     * team in this turn.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int $round
     * @param array $payload
     * @param int|null $now
     */
    public static function strike(\stdClass $live, \stdClass $member, int $round, array $payload, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $joined = $DB->get_record(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $member->charid]);
        if (!$joined || $round !== (int) $live->round || !self::is_open($state, $now)) {
            throw new \moodle_exception('roundclosed', 'local_oksigeniatesape');
        }
        $lane = $state['rounds'][$round]['lanes'][(int) $joined->team] ?? null;
        if (!$lane || empty($lane['engine'])) {
            throw new \invalid_parameter_exception('Not a lane of another engine');
        }
        if (isset($lane['speaker']) && (int) $lane['speaker'] !== (int) $member->charid) {
            throw new \invalid_parameter_exception('Another player sorts for the team in this turn');
        }
        try {
            $DB->insert_record(self::T . 'livein', (object) ['liveid' => $live->id, 'courseid' => $live->courseid,
                'charid' => $member->charid, 'round' => $round, 'team' => $joined->team,
                'answer' => self::lane_right($lane, $payload) ? 1 : 0, 'timecreated' => $now]);
        } catch (\dml_write_exception $e) {
            return;
        }
    }

    /**
     * Runs an action of the class screen on the session of a course with nobody else acting on it at the same time:
     * two taps on a button, or two class screens, would otherwise act twice.
     *
     * @param int $courseid
     * @param callable $action
     * @return mixed What the action returns.
     */
    public static function locked(int $courseid, callable $action) {
        $lock = \core\lock\lock_config::get_lock_factory('local_oksigeniatesape')->get_lock('live-' . $courseid, 10);
        if (!$lock) {
            throw new \moodle_exception('livebusy', 'local_oksigeniatesape');
        }
        try {
            return $action();
        } finally {
            $lock->release();
        }
    }

    /**
     * The next question. The first one sets the life of the Embrollo, from the players there are.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @param int|null $from The round the class screen was showing: if it is not the current one any more (a second
     *     tap, another class screen), nothing happens.
     * @return \stdClass The session.
     */
    public static function next(\stdClass $live, ?int $now = null, ?int $from = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($from !== null && $from !== $round) {
            return $live;
        }
        if ($state['outcome'] !== '' || $round >= count($state['questions'])) {
            throw new \moodle_exception('battleover', 'local_oksigeniatesape');
        }
        if ($round > 0 && empty($state['rounds'][$round]['revealed'])) {
            $state = self::count($live, $state, $now);
        }
        if ($round === 0) {
            $players = $state['mode'] === 'aloud' ? count(self::teams((int) $live->courseid)) : self::players($live);
            $state['maxhp'] = max(1, (int) round($players * count($state['questions']) * self::TO_WIN));
            if (!empty($state['knots'])) {
                // By turns, the threads of the Embrollo: a lane undone takes one, a knot broken three more.
                $lanes = max(1, count(self::playing($live, $state)));
                $state['maxhp'] = max(1, (int) round(count($state['knots']) * ($lanes + self::BREAK_THREADS) * self::TO_WIN));
            }
            $state['hp'] = $state['maxhp'];
            if (!empty($state['rematch'])) {
                $state['hp'] = max(1, (int) round($state['maxhp'] * $state['rematch']));
            }
        }
        $round++;
        if (!empty($state['knots'])) {
            $state = self::which_knot($live, $state, $round);
        }
        $question = questions::get((int) $live->courseid, $state['questions'][$round - 1], self::seed($live, $round));
        if (!$question) {
            // The mission changed in the middle of the battle and the question is gone.
            throw new \moodle_exception('noquestions', 'local_oksigeniatesape');
        }
        // The question stays in the state, so that the phones do not read the mission on every poll; and the name of
        // its chapter, for the trail of the scout.
        $chapter = (string) ($question['chapter'] ?? '');
        $state['rounds'][$round] = ['q' => $question['id'], 'options' => count($question['options']),
            'correct' => $question['correct'], 'question' => array_diff_key($question, ['chapter' => 1, 'objective' => 1]),
            'chapter' => $chapter, 'trail' => self::chapter_name((int) $live->courseid, $chapter), 'revealed' => false,
            'answered' => 0, 'right' => 0, 'teams' => [], 'counter' => 0, 'shielded' => 0];
        $state['open'] = true;
        // Down to half its life, the Embrollo tangles itself more: less time for each question.
        $secs = $state['secs'] && self::phase($state) > 1 ? max(10, $state['secs'] - self::PHASE_SECS) : $state['secs'];
        $state['opensat'] = 0;
        if (!empty($state['knots'])) {
            // By turns: the knot, the team it goes for (each in turn, among those that play), what the Embrollo means to
            // do, the lane of each team, and the time to announce it and get ready before the lanes take answers.
            $state['rounds'][$round]['knot'] = $state['knots'][$round - 1];
            $state['rounds'][$round]['target'] = self::target($live, $state, $round);
            $state['rounds'][$round]['intent'] = self::intent($state, $round);
            $state['rounds'][$round]['lanes'] = self::lanes($live, $state, $round);
            $state['rounds'][$round]['startat'] = $now;
            $state['opensat'] = $now + self::INTENT + self::PREP;
        }
        $state['closesat'] = $secs ? max($now, $state['opensat']) + $secs + 1 : 0;
        return self::save($live, $state, $round, $now);
    }

    /**
     * The answer of the round: no more answers, the right ones take life from the Embrollo.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @param int|null $from The round the class screen was showing: if it is not the current one any more (the time
     *     ran out just as the teacher went on), nothing happens.
     * @return \stdClass The session.
     */
    public static function reveal(\stdClass $live, ?int $now = null, ?int $from = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        if ($round < 1 || !empty($state['rounds'][$round]['revealed']) || ($from !== null && $from !== $round)) {
            return $live;
        }
        $state = self::count($live, $state, $now);
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
            $state = self::count($live, $state, $now);
        }
        $state['open'] = false;
        // Who came in stays with the battle: the answers are cleaned up after some days, the battles they missed are not.
        $in = $DB->get_fieldset_select(self::T . 'livein', 'charid', 'liveid = ? AND round = 0', [$live->id]);
        $state['joined'] = array_map('intval', $in);
        if ($live->kind === 'vote') {
            $state['outcome'] = 'done';
        } else {
            // By turns, the tower in the dark is lost even if the Embrollo has no threads left.
            $state['outcome'] = $round > 0 && $state['hp'] <= 0 && ($state['candles'] ?? 1) > 0 ? 'won' : 'lost';
        }
        $transaction = $DB->start_delegated_transaction();
        if ($state['outcome'] === 'won') {
            $state['rewarded'] = self::reward($live, $state, $actorid, $now);
        }
        if (!empty($state['knots']) && $round > 0) {
            // By turns: lost, the Embrollo escapes as it is, for the rematch; won, there is nothing left to go on with.
            $left = $state['maxhp'] ? $state['hp'] / $state['maxhp'] : 1;
            $escaped = $state['outcome'] === 'lost' ? json_encode(['left' => $left,
                'broken' => array_values(array_unique($state['broken'] ?? [])),
                'hanging' => array_values(array_unique($state['hanging'] ?? [])), 'final' => !empty($state['final'])]) : null;
            $DB->set_field(self::T . 'region', 'battlestate', $escaped, ['courseid' => $live->courseid]);
            regions::forget();
        }
        $live = self::save($live, $state, $round, $now);
        $DB->set_field(self::T . 'live', 'timeend', $now, ['id' => $live->id]);
        $transaction->allow_commit();
        $live->timeend = $now;
        return $live;
    }

    /**
     * Calls off a battle nobody has played yet: it ends without an outcome, and the class screen and the phones forget it.
     *
     * @param \stdClass $live
     * @param int|null $now
     */
    public static function cancel(\stdClass $live, ?int $now = null): void {
        global $DB;
        if ((int) $live->round > 0 || self::state($live)['outcome'] !== '') {
            throw new \moodle_exception('battleover', 'local_oksigeniatesape');
        }
        $DB->set_field(self::T . 'live', 'timeend', $now ?? time(), ['id' => $live->id]);
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
            if (self::shown($live, $state, $now)) {
                $view['question'] = self::question($info);
                if (!empty($info['lanes'])) {
                    $byid = array_column($teams, 'name', 'id');
                    $view['lanes'] = [];
                    foreach ($info['lanes'] as $team => $lane) {
                        $view['lanes'][] = ['teamid' => (int) $team, 'team' => (string) ($byid[$team] ?? ''),
                            'question' => self::question(['question' => $lane['question'], 'revealed' => $info['revealed']]),
                            'done' => !empty($lane['done']), 'right' => (int) ($lane['right'] ?? 0),
                            'answered' => (int) ($lane['answered'] ?? 0)];
                    }
                }
            }
            $view['right'] = (int) $info['right'];
            if ($info['revealed'] && $live->kind === 'vote') {
                $view['tally'] = $info['tally'];
            }
        }
        $view['rewarded'] = count($state['rewarded']);
        // The tokens used in the round, announced on the class screen.
        $names = array_map(fn($t) => $t['name'], $teams ? array_column($teams, null, 'id') : []);
        $used = $round > 0 ? $DB->get_records(self::T . 'livetoken', ['liveid' => $live->id, 'round' => $round], 'id') : [];
        $view['tokens'] = array_values(array_map(fn($t) => ['kind' => $t->kind, 'team' => $names[$t->team] ?? '',
            'target' => $t->target && $t->target != $t->team ? ($names[$t->target] ?? '') : ''], $used));
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
        $mine = $DB->get_records(self::T . 'livein', $conditions, '', 'round, answer, team');
        $view = self::common($live, $state, $now) + ['joined' => array_key_exists(0, $mine), 'mine' => -1,
            'myteam' => (int) ($mine[0]->team ?? 0)];
        if ($round > 0) {
            $info = $state['rounds'][$round];
            $view['mine'] = (int) ($mine[$round]->answer ?? -1);
            $lane = $info['lanes'][(int) ($mine[0]->team ?? 0)] ?? null;
            if (self::shown($live, $state, $now)) {
                $view['question'] = self::question($lane ? ['question' => $lane['question'], 'revealed' => $info['revealed']] :
                    $info);
            }
            if ($lane && $info['revealed']) {
                $view['done'] = !empty($lane['done']);
            }
            if ($lane && !empty($lane['engine']) && self::shown($live, $state, $now)) {
                // A lane of another engine: what it shows, who sorts for the team, and once resolved, the solution.
                $speaker = (int) ($lane['speaker'] ?? 0);
                $view['lane'] = json_encode($lane['shown'] + ['engine' => $lane['engine'],
                    'mine' => $speaker === 0 || $speaker === (int) $member->charid,
                    'speaker' => $speaker ? self::player_name((int) $live->courseid, $speaker) : ''] +
                    ($info['revealed'] ? ['solution' => $lane['solution']] : []));
            }
            if ($info['revealed'] && $live->kind === 'vote') {
                $view['tally'] = $info['tally'];
            }
        }
        if ($state['outcome'] === 'won') {
            $view['prize'] = $state['rewarded'][$member->charid] ?? null;
        }
        if ($live->kind === 'battle' && $state['mode'] === 'phones' && array_key_exists(0, $mine)) {
            $view += self::tokens_for($live, $state, $member, (int) $mine[0]->team);
        }
        return array_filter($view, fn($value) => $value !== null);
    }

    /**
     * A player uses the verb of their role in the open round: the glance of the sage strikes out a wrong option for their
     * team, the trail of the scout tells their team in which chapter the answer is (by turns, also the next knot), the
     * shield of the protector stops a blow, and the cheer of the healer (without turns, each right answer of their team
     * takes double life; by turns, it untangles a team and once a battle lights a candle again). Without turns, once a
     * battle; by turns, again after two turns of rest, and the shield and the cheer go to a team.
     *
     * @param \stdClass $live
     * @param \stdClass $member
     * @param int|null $now
     * @param int $target By turns: the team the shield or the cheer goes to (0 or not a team that plays: their own).
     * @param string $cover By turns: the verb of a role nobody in the team has, used instead of their own (see covers()).
     * @return string The token used.
     */
    public static function token(
        \stdClass $live,
        \stdClass $member,
        ?int $now = null,
        int $target = 0,
        string $cover = ''
    ): string {
        global $DB;
        $now = $now ?? time();
        $state = self::state($live);
        $round = (int) $live->round;
        $kind = $cover !== '' ? $cover : (self::TOKENS[self::role($member)] ?? '');
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        $joined = $DB->get_record(self::T . 'livein', ['liveid' => $live->id, 'round' => 0, 'charid' => $member->charid]);
        $playing = $live->kind === 'battle' && $state['mode'] === 'phones' && $state['outcome'] === '';
        if (!$kind || !$joined || !$playing || $round < 1 || !self::is_live($state, $now)) {
            throw new \moodle_exception('roundclosed', 'local_oksigeniatesape');
        }
        $turns = !empty($state['knots']);
        if ($cover !== '' && (!$turns || !in_array($cover, self::covers($live, (int) $joined->team), true))) {
            // The verb of a role nobody in the team has today, once a battle for the team, by turns.
            throw new \moodle_exception('tokenused', 'local_oksigeniatesape');
        }
        if ($turns) {
            // By turns: the verb rests two turns after it is used, also when another of the same role in the team used it.
            if (self::rest($live, (int) $joined->team, $kind, $member, $round) > 0) {
                throw new \moodle_exception('tokenused', 'local_oksigeniatesape');
            }
            // The shield and the cheer go to a team that plays; the rest, to their own team.
            $teams = self::playing($live, $state);
            if (!in_array($kind, ['shield', 'cheer'], true) || !in_array($target, $teams, true)) {
                $target = (int) $joined->team;
            }
        } else if ($DB->record_exists(self::T . 'livetoken', ['liveid' => $live->id, 'charid' => $member->charid])) {
            // Without turns, once a battle.
            throw new \moodle_exception('tokenused', 'local_oksigeniatesape');
        }
        try {
            $DB->insert_record(self::T . 'livetoken', (object) ['liveid' => $live->id, 'courseid' => $live->courseid,
                'charid' => $member->charid, 'round' => $round, 'team' => $joined->team, 'kind' => $kind,
                'target' => $turns ? $target : 0, 'timecreated' => $now]);
        } catch (\dml_write_exception $e) {
            throw new \moodle_exception('tokenused', 'local_oksigeniatesape');
        }
        return $kind;
    }

    /**
     * By turns, the verbs of the roles nobody in a team who came into the battle has: anyone in the team can use one of
     * them, once a battle for the team, without saying why the role is missing. None once the team has used one.
     *
     * @param \stdClass $live
     * @param int $team
     * @return string[]
     */
    public static function covers(\stdClass $live, int $team): array {
        global $DB;
        if (!$team) {
            return [];
        }
        $roles = $DB->get_fieldset_sql(
            'SELECT c.role
               FROM {' . self::T . 'livein} l
               JOIN {' . self::T . 'char} c ON c.id = l.charid
              WHERE l.liveid = ? AND l.round = 0 AND l.team = ?',
            [$live->id, $team]
        );
        $missing = array_values(array_diff_key(self::TOKENS, array_flip($roles)));
        if (!$missing || count($missing) === count(self::TOKENS)) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal($missing);
        $covered = $DB->record_exists_select(
            self::T . 'livetoken',
            'liveid = ? AND team = ? AND kind ' . $in,
            array_merge([$live->id, $team], $params)
        );
        return $covered ? [] : $missing;
    }

    /**
     * By turns, how many turns a verb still rests for a player: two after the turn it was used in, by them or by another
     * of the same role in their team (a pair of the same role shares the rest).
     *
     * @param \stdClass $live
     * @param int $team
     * @param string $kind
     * @param \stdClass $member
     * @param int $round
     * @return int 0: ready.
     */
    private static function rest(\stdClass $live, int $team, string $kind, \stdClass $member, int $round): int {
        global $DB;
        $where = 'liveid = ? AND kind = ? AND (charid = ? OR (team = ? AND team > 0))';
        $last = (int) $DB->get_field_select(self::T . 'livetoken', 'MAX(round)', $where, [$live->id, $kind, $member->charid,
            $team]);
        return $last ? max(0, $last + self::REST + 1 - $round) : 0;
    }

    /**
     * The token of a player and what the tokens of their team do in the round, for their phone.
     *
     * @param \stdClass $live
     * @param array $state
     * @param \stdClass $member
     * @param int $team The team they came in with (0: none).
     * @return array token, tokenused and, in the round, struck (an option that is not it) and trail (the chapter).
     */
    private static function tokens_for(\stdClass $live, array $state, \stdClass $member, int $team): array {
        global $DB;
        $round = (int) $live->round;
        // Their own tokens and those of their team in the round, in one query.
        $where = 'liveid = ? AND (charid = ? OR (round = ? AND team = ?))';
        $params = [$live->id, $member->charid, $round, $team ?: -1];
        $tokens = $DB->get_records_select(self::T . 'livetoken', $where, $params, '', 'id, charid, round, kind');
        $used = (bool) array_filter($tokens, fn($t) => (int) $t->charid === (int) $member->charid);
        $kind = self::TOKENS[self::role($member)] ?? '';
        $view = ['token' => $kind, 'tokenused' => $used];
        if (!empty($state['knots']) && $kind !== '') {
            // By turns: the verb comes back after its rest; the shield and the cheer choose the team they go to.
            $rest = $round > 0 ? self::rest($live, $team, $kind, $member, $round) : 0;
            $view['tokenused'] = $rest > 0;
            $view['tokenrest'] = $rest;
            // And the verb of a role missing in the team, which anyone in it can use once.
            $view['covers'] = $round > 0 && empty($state['rounds'][$round]['revealed']) ? self::covers($live, $team) : [];
            if (in_array($kind, ['shield', 'cheer'], true) || array_intersect($view['covers'], ['shield', 'cheer'])) {
                $names = self::teams((int) $live->courseid);
                $view['targets'] = array_map(fn($id) => ['id' => $id, 'name' => isset($names[$id]) ?
                    format_string($names[$id]->name) : ''], self::playing($live, $state));
            }
        }
        if ($round < 1 || !empty($state['rounds'][$round]['revealed'])) {
            return $view;
        }
        // Without a team, only one's own token.
        $kinds = [];
        foreach ($tokens as $token) {
            if ((int) $token->round === $round && ($team || (int) $token->charid === (int) $member->charid)) {
                $kinds[] = $token->kind;
            }
        }
        // By turns, what the team plays is its lane; the glance strikes out an option only in one to choose.
        $info = array_merge($state['rounds'][$round], $state['rounds'][$round]['lanes'][$team] ?? []);
        if (in_array('glance', $kinds, true) && $info['options'] > 1 && empty($info['engine'])) {
            // Always the same wrong option for the round, so that the phones of the team agree.
            $wrong = array_values(array_diff(range(0, $info['options'] - 1), [(int) $info['correct']]));
            $view['struck'] = $wrong[self::seed($live, $round) % count($wrong)];
        }
        if (in_array('trail', $kinds, true) && $info['chapter'] !== '') {
            // Battles opened before the name was kept with the round look it up.
            $view['trail'] = $info['trail'] ?? self::chapter_name((int) $live->courseid, $info['chapter']);
        }
        if (in_array('trail', $kinds, true) && !empty($state['knots']) && $round < count($state['knots'])) {
            // By turns, the trail of the scout also sees what comes next: the knot of the next turn and who it goes for.
            $next = knots::get(missions::get((int) $live->courseid), (string) $state['knots'][$round]);
            $teams = self::playing($live, $state);
            $names = self::teams((int) $live->courseid);
            $goes = $teams ? $teams[$round % count($teams)] : 0;
            $view['nextknot'] = (string) ($next['name'] ?? '');
            $view['nexttarget'] = isset($names[$goes]) ? format_string($names[$goes]->name) : '';
        }
        return $view;
    }

    /**
     * The role of the character of a member: it comes with the player, or it is looked up.
     *
     * @param \stdClass $member
     * @return string
     */
    private static function role(\stdClass $member): string {
        global $DB;
        return (string) ($member->role ?? $DB->get_field(self::T . 'char', 'role', ['id' => $member->charid]));
    }

    /**
     * The name a player shows to their classmates (the hero name where the course allows it).
     *
     * @param int $courseid
     * @param int $charid
     * @return string
     */
    private static function player_name(int $courseid, int $charid): string {
        global $DB;
        $char = $DB->get_record(self::T . 'char', ['id' => $charid]);
        $user = $char ? \core_user::get_user((int) $char->userid) : null;
        $region = regions::get($courseid);
        return $user && $region ? view::display_name($user, $char, $region) : '';
    }

    /**
     * The name of a chapter of the mission of a course, as the students see it.
     *
     * @param int $courseid
     * @param string $code
     * @return string|null
     */
    private static function chapter_name(int $courseid, string $code): ?string {
        $mission = $code !== '' ? missions::get($courseid) : null;
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            if ($chapter['code'] === $code) {
                return missions::text($chapter['name'] ?? $chapter['code'], $mission->skin, $mission->variant);
            }
        }
        return null;
    }

    /**
     * A day after a session, what each one answered and got there goes; a week after, the session itself, which then
     * only says how the class did.
     *
     * @param int|null $now
     * @return int Sessions deleted.
     */
    public static function cleanup(?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $old = $DB->get_fieldset_select(self::T . 'live', 'id', 'timecreated < ?', [$now - DAYSECS]);
        foreach (array_chunk($old, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $DB->delete_records_select(self::T . 'livein', "liveid $insql", $params);
            $DB->delete_records_select(self::T . 'livetoken', "liveid $insql", $params);
            foreach ($DB->get_records_select(self::T . 'live', "id $insql", $params, '', 'id, state') as $live) {
                $state = self::state($live);
                if (!empty($state['rewarded'])) {
                    $state['rewarded'] = [];
                    $DB->set_field(self::T . 'live', 'state', json_encode($state), ['id' => $live->id]);
                }
            }
        }
        $gone = $DB->get_fieldset_select(self::T . 'live', 'id', 'timecreated < ?', [$now - self::KEPT_DAYS * DAYSECS]);
        foreach (array_chunk($gone, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk);
            $DB->delete_records_select(self::T . 'live', "id $insql", $params);
        }
        return count($gone);
    }

    /**
     * The battles of the class that a student who follows the expedition from far missed since they last came in: how
     * the class did, and the lantern the final one lit.
     *
     * @param int $courseid
     * @param int $since When they last came in.
     * @param int $charid
     * @return array Newest first, the last three: outcome (won or lost), time, final, lantern (its name, if it lit one).
     */
    public static function missed(int $courseid, int $since, int $charid): array {
        global $DB;
        $where = "courseid = ? AND kind = 'battle' AND timeend > ?";
        $fields = 'id, state, timeend';
        $battles = $DB->get_records_select(self::T . 'live', $where, [$courseid, $since], 'timeend DESC', $fields, 0, 3);
        $missed = [];
        $mission = null;
        foreach ($battles as $live) {
            $state = self::state($live);
            $joined = ['liveid' => $live->id, 'round' => 0, 'charid' => $charid];
            $played = isset($state['joined']) ? in_array($charid, $state['joined'], true) :
                $DB->record_exists(self::T . 'livein', $joined);
            if (!in_array($state['outcome'] ?? '', ['won', 'lost'], true) || $played) {
                continue;
            }
            $lantern = '';
            if ($state['outcome'] === 'won' && !empty($state['final'])) {
                $mission = $mission ?? missions::get($courseid);
                $lantern = (string) self::chapter_name($courseid, (string) ($mission->data['battle']['final']['lantern'] ?? ''));
            }
            $missed[] = ['outcome' => $state['outcome'], 'time' => (int) $live->timeend, 'final' => !empty($state['final']),
                'lantern' => $lantern];
        }
        return $missed;
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
        $open = self::is_open($state, $now);
        $now = self::clock($state, $now);
        return [
            'id' => (int) $live->id,
            'kind' => $live->kind,
            'mode' => $state['mode'],
            'round' => $round,
            'total' => count($state['questions']),
            'open' => $open,
            'paused' => !empty($state['pausedat']),
            'left' => $state['closesat'] ? max(0, $state['closesat'] - 1 - $now) : -1,
            'revealed' => $round > 0 && !empty($state['rounds'][$round]['revealed']),
            'hp' => (int) $state['hp'],
            'maxhp' => (int) $state['maxhp'],
            'outcome' => $state['outcome'],
            'phase' => self::phase($state),
            'final' => !empty($state['final']),
            'rematch' => (int) round(100 * (float) ($state['rematch'] ?? 0)),
            'counter' => $round > 0 ? (int) ($state['rounds'][$round]['counter'] ?? 0) : 0,
            'shielded' => $round > 0 && !empty($state['rounds'][$round]['shielded']),
            // By turns, the candles of the tower from the lobby to the end: the screens know it is a battle by turns.
            'candles' => (int) ($state['candles'] ?? 0),
            'maxcandles' => (int) ($state['maxcandles'] ?? 0),
        ] + self::turn($live, $state, $now);
    }

    /**
     * The turn of a battle by turns, for the views: its stage and the seconds it has left, the knot (who carries it, what
     * the Embrollo claims and, once resolved, its laugh if the attack fell) and the team it goes for.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return array
     */
    private static function turn(\stdClass $live, array $state, int $now): array {
        [$stage, $left] = self::stage($live, $state, $now);
        if ($stage === '') {
            return [];
        }
        $info = $state['rounds'][(int) $live->round];
        $knot = knots::get(missions::get((int) $live->courseid), (string) $info['knot']) ?? ['name' => '', 'claim' => '',
            'laugh' => '', 'why' => ''];
        $teams = self::teams((int) $live->courseid);
        $target = (int) ($info['target'] ?? 0);
        $resolved = $stage === 'resolve';
        return ['stage' => $stage, 'stageleft' => $left, 'knot' => ['name' => $knot['name'], 'claim' => $knot['claim'],
            'laugh' => $resolved && !empty($info['counter']) ? $knot['laugh'] : '',
            'why' => $resolved && !empty($info['broken']) ? $knot['why'] : '', 'broken' => $resolved && !empty($info['broken'])],
            'targetid' => $target, 'target' => isset($teams[$target]) ? format_string($teams[$target]->name) : '',
            'damage' => (int) ($info['damage'] ?? 0), 'intent' => (string) ($info['intent'] ?? 'team'),
            'tangled' => array_map('intval', array_keys($state['tangled'] ?? [])), 'broken' => count($state['broken'] ?? []),
            'recovered' => $resolved && !empty($info['recovered'])];
    }

    /**
     * Whether the lazada of the round can be seen: always, except while a turn is announced and got ready.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return bool
     */
    private static function shown(\stdClass $live, array $state, int $now): bool {
        return !in_array(self::stage($live, $state, $now)[0], ['intent', 'prep'], true);
    }

    /**
     * The phase of the battle: 2 once the Embrollo is down to half its life.
     *
     * @param array $state
     * @return int
     */
    private static function phase(array $state): int {
        return $state['maxhp'] > 0 && 2 * $state['hp'] <= $state['maxhp'] ? 2 : 1;
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
     * @param int|null $now
     * @return array The state.
     */
    private static function count(\stdClass $live, array $state, ?int $now = null): array {
        global $DB;
        $round = (int) $live->round;
        $info = $state['rounds'][$round];
        $answers = $DB->get_records(self::T . 'livein', ['liveid' => $live->id, 'round' => $round], '', 'id, charid, team, answer');
        $info['answered'] = count($answers);
        if ($live->kind === 'vote') {
            $info['tally'] = array_fill(0, (int) $info['options'], 0);
            foreach ($answers as $answer) {
                $info['tally'][(int) $answer->answer]++;
            }
        } else if ($state['mode'] === 'phones' && !empty($info['lanes'])) {
            [$state, $info] = self::resolve($live, $state, $info, $answers);
        } else if ($state['mode'] === 'phones') {
            $tokens = $DB->get_records(self::T . 'livetoken', ['liveid' => $live->id, 'round' => $round], '', 'id, team, kind');
            $cheer = array_map(fn($t) => (int) $t->team, array_filter($tokens, fn($t) => $t->kind === 'cheer'));
            $shield = array_values(array_filter($tokens, fn($t) => $t->kind === 'shield'));
            $damage = 0;
            foreach ($answers as $answer) {
                if ((int) $answer->answer === (int) $info['correct']) {
                    $info['right']++;
                    $info['teams'][$answer->team] = ($info['teams'][$answer->team] ?? 0) + 1;
                    // The cheer of the healer: each right answer of their team takes double life this round.
                    $damage += in_array((int) $answer->team, $cheer, true) ? 2 : 1;
                }
            }
            $state['hp'] = max(0, $state['hp'] - $damage);
            $info['damage'] = $damage;
            if (!empty($info['target'])) {
                // By turns: the attack falls if the team it went for did not undo its lazada (most of who answered got
                // it wrong, or nobody answered); only a shield on that team stops it.
                $target = (int) $info['target'];
                $tried = count(array_filter($answers, fn($a) => (int) $a->team === $target));
                $failed = !$tried || 2 * (int) ($info['teams'][$target] ?? 0) < $tried;
                $shield = array_values(array_filter($shield, fn($t) => (int) $t->team === $target));
            } else {
                // A round the class mostly got wrong.
                $failed = $info['answered'] > 0 && 2 * $info['right'] < $info['answered'];
            }
            // The Embrollo pulls itself together, unless a protector's shield stops it.
            if ($state['hp'] > 0 && $failed) {
                if ($shield) {
                    $info['shielded'] = (int) $shield[0]->team ?: -1;
                } else {
                    $info['counter'] = max(1, (int) round(self::COUNTER * $state['maxhp']));
                    $state['hp'] = min($state['maxhp'], $state['hp'] + $info['counter']);
                }
            }
        }
        $info['revealed'] = true;
        $info['revealedat'] = $now ?? time();
        $state['rounds'][$round] = $info;
        $state['open'] = false;
        $state['closesat'] = 0;
        return $state;
    }

    /**
     * The resolution of a turn by turns: the cheer of a healer untangles the team it goes to (and once a battle lights a
     * candle again); each lane its team undoes (most of its players in got it right) takes a thread from the Embrollo;
     * if every lane is undone, the knot breaks (three threads more,
     * and the Embrollo is dazed the next turn: its guard is broken, what the class undoes counts double). Then what it
     * meant to do: going for a team, the lane of that team not undone puts out a candle and leaves the team tangled,
     * unless a shield is on it; going for everybody, a candle goes out if fewer than half the lanes were undone; pulling
     * itself together, it gets threads back if fewer than half were undone. A team that undoes its lane is not tangled
     * any more. A knot that does not break is left hanging.
     *
     * @param \stdClass $live
     * @param array $state
     * @param array $info The round.
     * @param array $answers The answers of the round (team, answer).
     * @return array [state, round].
     */
    private static function resolve(\stdClass $live, array $state, array $info, array $answers): array {
        global $DB;
        $round = (int) $live->round;
        $fields = 'id, team, kind, target';
        $tokens = $DB->get_records(self::T . 'livetoken', ['liveid' => $live->id, 'round' => $round], '', $fields);
        // The shield and the cheer go to the team they were put on (their own, if none).
        $on = fn($kind) => array_map(fn($t) => (int) ($t->target ?: $t->team), array_filter($tokens, fn($t) => $t->kind === $kind));
        $cheer = $on('cheer');
        $shields = $on('shield');
        // The cheer of the healer: the team it goes to is not tangled any more, and once a battle a candle lights again.
        foreach ($cheer as $team) {
            unset($state['tangled'][$team]);
            if (empty($state['relit']) && $state['candles'] < $state['maxcandles']) {
                $state['candles']++;
                $state['relit'] = true;
                $info['relit'] = true;
            }
        }
        // Who is in each team, for its majority; with «no hurry», only if they answered: not answering does not count
        // against the team.
        $answered = array_map(fn($a) => (int) $a->charid, $answers);
        $present = [];
        $in = $DB->get_records_sql(
            'SELECT l.charid, l.team, m.adapt
               FROM {' . self::T . 'livein} l
               JOIN {' . self::T . 'member} m ON m.charid = l.charid AND m.courseid = l.courseid
              WHERE l.liveid = ? AND l.round = 0',
            [$live->id]
        );
        foreach ($in as $player) {
            $slow = in_array('notime', explode(',', (string) $player->adapt), true);
            if (!$slow || in_array((int) $player->charid, $answered, true)) {
                $present[(int) $player->team] = ($present[(int) $player->team] ?? 0) + 1;
            }
        }
        $damage = 0;
        $done = 0;
        foreach ($info['lanes'] as $team => $lane) {
            $mine = array_filter($answers, fn($a) => (int) $a->team === (int) $team);
            $right = count(array_filter($mine, fn($a) => (int) $a->answer === (int) $lane['correct']));
            // Sorted for the team by one player: that one counts.
            $players = isset($lane['speaker']) ? 1 : (int) ($present[$team] ?? 0);
            $undone = $players > 0 && 2 * $right > $players;
            $info['lanes'][$team] += ['answered' => count($mine), 'right' => $right, 'done' => $undone];
            $info['teams'][$team] = $right;
            $info['right'] += $right;
            if ($undone) {
                $done++;
                $damage++;
                unset($state['tangled'][$team]);
            }
        }
        $all = $info['lanes'] && $done === count($info['lanes']);
        if ($all) {
            $damage += self::BREAK_THREADS;
            $state['broken'][] = (string) $info['knot'];
        } else {
            $state['hanging'][] = (string) $info['knot'];
        }
        $info['broken'] = $all;
        if (($info['intent'] ?? '') === 'dazed') {
            $damage *= 2;
        }
        $state['dazed'] = $all;
        $state['hp'] = max(0, $state['hp'] - $damage);
        $info['damage'] = $damage;
        // A team nobody of which had to answer (all of it with «no hurry», and nobody did) is not in play this turn.
        $inplay = array_filter(array_keys($info['lanes']), fn($team) => !empty($present[$team]) ||
            isset($info['lanes'][$team]['speaker']));
        $few = $inplay && 2 * $done < count($inplay);
        $target = (int) ($info['target'] ?? 0);
        if ($state['hp'] > 0) {
            $intarget = in_array($target, array_map('intval', $inplay), true);
            if (($info['intent'] ?? '') === 'team' && $intarget && empty($info['lanes'][$target]['done'])) {
                if (in_array($target, $shields, true)) {
                    $info['shielded'] = $target;
                } else {
                    $state['candles'] = max(0, $state['candles'] - 1);
                    $state['tangled'][$target] = true;
                    $info['counter'] = 1;
                }
            } else if (($info['intent'] ?? '') === 'all' && $few) {
                $state['candles'] = max(0, $state['candles'] - 1);
                $info['counter'] = 1;
            } else if (($info['intent'] ?? '') === 'recompose' && $few) {
                $state['hp'] = min($state['maxhp'], $state['hp'] + self::RECOMPOSE);
                $info['counter'] = self::RECOMPOSE;
                $info['recovered'] = true;
            }
        }
        return [$state, $info];
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
                // Those without a team play, but there is no team of them to help most.
                if ($team) {
                    $byteam[$team] = ($byteam[$team] ?? 0) + $right;
                }
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
        // By turns there is no team that helped most: the untangled thread is for the class, if it broke half the knots.
        $turns = !empty($state['knots']);
        $classthread = $turns && 2 * count(array_unique($state['broken'] ?? [])) >= max(1, count($state['rounds']));
        // The object of the mission for the team that helped most; more gems if the mission has none, or for those
        // who already have it.
        $mission = missions::get($courseid);
        $code = (string) ($mission->data['battle']['reward'] ?? '');
        $item = $code ? ($mission->data['items'][$code] ?? []) : [];
        // What is left of the Embrollo when the class beats it: only in the final battle.
        $itemid = $code && !empty($state['final']) ?
            missions::item_id($code, $item['name'] ?? null, $item['description'] ?? null, $mission->code) : 0;
        // The final battle gives what the mission saves for the end, once for each player.
        $final = !empty($state['final']) ? ($mission->data['battle']['final'] ?? []) : [];
        if ($final && !empty($final['lantern'])) {
            // The whole class has beaten the Embrollo: the lantern of its place lights for everybody.
            $where = 'missionid = ? AND code = ? AND litat = 0';
            $DB->set_field_select(self::T . 'chapter', 'litat', $now, $where, [$mission->id, $final['lantern']]);
        }
        $rewarded = [];
        foreach ($players as $charid => $team) {
            $member = $DB->get_record(self::T . 'member', ['charid' => $charid, 'courseid' => $courseid]);
            if (!$member || $member->status === 'paused') {
                continue;
            }
            $isbest = $turns ? $classthread : $best > 0 && ($byteam[$team] ?? 0) == $best;
            $got = $isbest && $itemid ? engine::item($member, $itemid, 'battle', $actorid, 0, $now) : null;
            $gems = self::WIN_GEMS + ($isbest && !$got ? self::TEAM_GEMS : 0);
            $first = $final && self::once((int) $charid, $courseid, (int) $mission->id, 'final', 'battle', $now);
            $saber = $first ? (int) ($final['saber'] ?? self::WIN_SABER) : self::WIN_SABER;
            $entry = engine::saber($member, $saber, 'battle', $actorid, 0, (int) $live->id, $now);
            engine::gems($member, $gems, 'battle', $actorid, 0, (int) $live->id, $now);
            $prize = ['saber' => (int) ($entry->amount ?? 0), 'capped' => (int) ($entry->capped ?? 0), 'gems' => $gems,
                'best' => $isbest, 'item' => $got ? (string) ($item['name'] ?? $code) : '', 'final' => '', 'grew' => false];
            if ($first) {
                $prize = array_merge($prize, self::final_prize($member, $mission, $final, $actorid, $now));
            }
            $rewarded[$charid] = $prize;
        }
        if ($final && !empty($final['lantern'])) {
            // The lantern of the place is the class beating the Embrollo, also for whoever was not in class that day: missing
            // the battle does not leave anyone without the badge of the mission.
            $lantern = (string) $final['lantern'];
            $where = 'courseid = ? AND status <> ? AND leftat = 0';
            foreach ($DB->get_records_select(self::T . 'member', $where, [$courseid, 'paused'], '', 'id, charid') as $member) {
                $charid = (int) $member->charid;
                if (!isset($players[$charid]) && self::once($charid, $courseid, (int) $mission->id, 'lantern', $lantern, $now)) {
                    badges::check($courseid, (int) $DB->get_field(self::T . 'char', 'userid', ['id' => $charid]));
                }
            }
        }
        return $rewarded;
    }

    /**
     * What the final battle gives each player the first time: the object of the end, the lantern of its place and the
     * creature growing.
     *
     * @param \stdClass $member
     * @param \stdClass $mission
     * @param array $final item, lantern, creature (grow).
     * @param int $actorid
     * @param int $now
     * @return array final (name of the object), grew.
     */
    private static function final_prize(\stdClass $member, \stdClass $mission, array $final, int $actorid, int $now): array {
        global $DB;
        $prize = ['final' => '', 'grew' => false];
        if (!empty($final['item'])) {
            $code = (string) $final['item'];
            $info = $mission->data['items'][$code] ?? [];
            $itemid = missions::item_id($code, $info['name'] ?? null, $info['description'] ?? null, $mission->code);
            if (engine::item($member, $itemid, 'battle', $actorid, 0, $now)) {
                $prize['final'] = (string) ($info['name'] ?? $code);
            }
        }
        if (!empty($final['lantern'])) {
            $lantern = (string) $final['lantern'];
            self::once((int) $member->charid, (int) $member->courseid, (int) $mission->id, 'lantern', $lantern, $now);
        }
        $char = $DB->get_record(self::T . 'char', ['id' => $member->charid], 'id, userid, creaturestage', MUST_EXIST);
        if (!empty($final['lantern'])) {
            badges::check((int) $member->courseid, (int) $char->userid);
        }
        $grows = $char->creaturestage > 0 && $char->creaturestage < scenes::CREATURE_MAX_STAGE;
        if (($final['creature'] ?? '') === 'grow' && $grows) {
            $DB->set_field(self::T . 'char', 'creaturestage', $char->creaturestage + 1, ['id' => $char->id]);
            $prize['grew'] = true;
        }
        return $prize;
    }

    /**
     * Keeps that a character found something in a mission, once.
     *
     * @param int $charid
     * @param int $courseid
     * @param int $missionid
     * @param string $kind
     * @param string $code
     * @param int $now
     * @return bool It is the first time.
     */
    private static function once(int $charid, int $courseid, int $missionid, string $kind, string $code, int $now): bool {
        global $DB;
        $record = ['charid' => $charid, 'courseid' => $courseid, 'missionid' => $missionid, 'kind' => $kind, 'code' => $code];
        if ($DB->record_exists(self::T . 'found', $record)) {
            return false;
        }
        try {
            $DB->insert_record(self::T . 'found', (object) ($record + ['timecreated' => $now]));
        } catch (\dml_write_exception $e) {
            return false;
        }
        return true;
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
        return self::is_live($state, $now) && $now >= (int) ($state['opensat'] ?? 0);
    }

    /**
     * Whether the round is on: from the start of a turn (the verbs can be used while it is announced) until it closes.
     *
     * @param array $state
     * @param int $now
     * @return bool
     */
    private static function is_live(array $state, int $now): bool {
        return $state['open'] && empty($state['pausedat']) && (!$state['closesat'] || $now < $state['closesat']);
    }

    /**
     * The teacher stops the battle a moment: the time of the turn stands still, and nobody answers or uses a verb until
     * it goes on.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass
     */
    public static function pause(\stdClass $live, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        if ($live->kind !== 'battle' || (int) $live->round < 1 || $state['outcome'] !== '' || !empty($state['pausedat'])) {
            return $live;
        }
        $state['pausedat'] = $now;
        return self::save($live, $state, (int) $live->round, $now);
    }

    /**
     * The battle goes on where it stopped: every time of the turn moves on as long as the pause lasted.
     *
     * @param \stdClass $live
     * @param int|null $now
     * @return \stdClass
     */
    public static function resume(\stdClass $live, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        if (empty($state['pausedat'])) {
            return $live;
        }
        $shift = max(0, $now - (int) $state['pausedat']);
        unset($state['pausedat']);
        $round = (int) $live->round;
        foreach (['startat', 'revealedat'] as $at) {
            if (!empty($state['rounds'][$round][$at])) {
                $state['rounds'][$round][$at] += $shift;
            }
        }
        foreach (['opensat', 'closesat'] as $at) {
            if (!empty($state[$at])) {
                $state[$at] += $shift;
            }
        }
        return self::save($live, $state, $round, $now);
    }

    /**
     * The moment the times of the battle are read at: while it is paused, the one it stopped at.
     *
     * @param array $state
     * @param int $now
     * @return int
     */
    private static function clock(array $state, int $now): int {
        return (int) ($state['pausedat'] ?? 0) ?: $now;
    }

    /**
     * The teams that play a battle: those with players in (all of them, aloud).
     *
     * @param \stdClass $live
     * @param array $state
     * @return int[] Group ids, in order.
     */
    private static function playing(\stdClass $live, array $state): array {
        global $DB;
        $teams = array_map('intval', array_keys(self::teams((int) $live->courseid)));
        if ($state['mode'] === 'phones') {
            $in = array_map('intval', $DB->get_fieldset_select(self::T . 'livein', 'DISTINCT team', 'liveid = ? AND round = 0 ' .
                'AND team > 0', [$live->id]));
            $teams = array_values(array_intersect($teams, $in)) ?: $teams;
        }
        sort($teams);
        return $teams;
    }

    /**
     * The knots of a rematch: those planned, but not those the class broke, and first those left hanging.
     *
     * @param int $courseid
     * @param int $count Turns.
     * @param bool $final
     * @param array $saved How the Embrollo escaped: broken, hanging.
     * @return array List of [knot code, question id] (empty: nothing left to play, the battle starts again).
     */
    private static function rematch(int $courseid, int $count, bool $final, array $saved): array {
        $broken = (array) ($saved['broken'] ?? []);
        $knots = array_column(knots::usable(missions::get($courseid)), null, 'code');
        $planned = array_column(knots::plan($courseid, $count + count($broken), $final), 0);
        $candidates = array_unique(array_merge((array) ($saved['hanging'] ?? []), $planned));
        $codes = array_values(array_filter($candidates, fn($c) => !in_array($c, $broken, true) && isset($knots[$c])));
        if (!$codes) {
            return [];
        }
        $plan = [];
        for ($turn = 0; $turn < $count; $turn++) {
            $code = $codes[intdiv($turn * count($codes), $count)];
            $plan[] = [$code, $knots[$code]['questions'][$turn % count($knots[$code]['questions'])]];
        }
        return $plan;
    }

    /**
     * The knot of a turn by turns: the one planned, unless the class broke it already (a broken knot does not come back:
     * one left hanging does instead); and in the last thread, once, the knot the class could least undo in this battle.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $round The turn that starts.
     * @return array The state, with the knot (and its first question) of the turn.
     */
    private static function which_knot(\stdClass $live, array $state, int $round): array {
        $code = (string) $state['knots'][$round - 1];
        $broken = $state['broken'] ?? [];
        $hanging = array_values(array_filter($state['hanging'] ?? [], fn($k) => !in_array($k, $broken, true)));
        $left = $state['maxhp'] ? 100 * $state['hp'] / $state['maxhp'] : 100;
        $last = $left <= ($state['phases']['last'] ?? 25);
        if ($last && $hanging && empty($state['lastknot'])) {
            $counts = array_count_values($hanging);
            arsort($counts);
            $code = (string) array_key_first($counts);
            $state['lastknot'] = $code;
        } else if (in_array($code, $broken, true) && $hanging) {
            $code = $hanging[0];
        }
        if ($code !== $state['knots'][$round - 1]) {
            $knot = array_column(knots::usable(missions::get((int) $live->courseid)), null, 'code')[$code] ?? null;
            if ($knot && $knot['questions']) {
                $state['knots'][$round - 1] = $code;
                $state['questions'][$round - 1] = $knot['questions'][0];
            }
        }
        return $state;
    }

    /**
     * What the Embrollo means to do in a turn: go for a team; while frayed, also go for everybody or pull itself
     * together; and nothing, dazed, the turn after a knot broke (its guard is broken: what the class undoes counts double).
     *
     * @param array $state
     * @param int $round
     * @return string team, all, recompose or dazed.
     */
    private static function intent(array $state, int $round): string {
        if (!empty($state['dazed'])) {
            return 'dazed';
        }
        $left = $state['maxhp'] ? 100 * $state['hp'] / $state['maxhp'] : 100;
        $phases = $state['phases'] ?? ['frayed' => 60, 'last' => 25];
        if ($left <= $phases['frayed'] && $left > $phases['last']) {
            return ['team', 'all', 'recompose'][$round % 3];
        }
        return 'team';
    }

    /**
     * The lane of each team in a turn, with its question ready for the phones (the right option is not shown until the
     * turn is resolved).
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $round
     * @return array Team id => q, options, correct, question, chapter, trail.
     */
    private static function lanes(\stdClass $live, array $state, int $round): array {
        global $DB;
        $courseid = (int) $live->courseid;
        $mission = missions::get($courseid);
        $knot = array_column(knots::usable($mission), null, 'code')[$state['knots'][$round - 1]] ?? null;
        $lanes = [];
        foreach ($knot ? knots::lanes($knot, self::playing($live, $state), $round - 1) : [] as $team => $lane) {
            $engine = (string) ($lane['engine'] ?? 'choice');
            if ($engine !== 'choice') {
                // Another engine: what it shows goes to the tablets; what solves it stays here until the turn is resolved.
                $lanes[$team] = self::lane_of($lane, $mission) + ['options' => 2, 'correct' => 1];
                if ($engine === 'classify' || $engine === 'order') {
                    // Sorting and putting in order are done by the team: one of its players, by turns, does it; the
                    // others help aloud.
                    $where = 'liveid = ? AND round = 0 AND team = ?';
                    $in = $DB->get_fieldset_select(self::T . 'livein', 'charid', $where, [$live->id, $team]);
                    sort($in);
                    $lanes[$team]['speaker'] = $in ? (int) $in[($round - 1) % count($in)] : 0;
                }
                continue;
            }
            $question = questions::get($courseid, (string) ($lane['question'] ?? ''), self::seed($live, $round) + $team);
            if ($question) {
                $chapter = (string) ($question['chapter'] ?? '');
                $lanes[$team] = ['q' => $question['id'], 'options' => count($question['options']),
                    'correct' => $question['correct'], 'question' => array_diff_key($question, ['chapter' => 1, 'objective' => 1]),
                    'chapter' => $chapter, 'trail' => self::chapter_name($courseid, $chapter)];
            }
        }
        return $lanes;
    }

    /**
     * A lane of another engine than choosing, ready for the turn: what the tablets show (shown) and what solves it
     * (solution), with its prompt as the question the class screen shows.
     *
     * @param array $lane From the knot: classify (prompt, bins, items), order (prompt, steps in their order) or errors
     *     (text).
     * @param \stdClass $mission
     * @return array engine, question, shown, solution.
     */
    private static function lane_of(array $lane, \stdClass $mission): array {
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        if ($lane['engine'] === 'classify') {
            $items = array_map(fn($i) => ['id' => (string) $i['id'], 'label' => $text($i['label'])], $lane['items'] ?? []);
            return ['engine' => 'classify', 'question' => ['id' => 'classify', 'text' => $text($lane['prompt'] ?? ''),
                'options' => []], 'shown' => ['prompt' => $text($lane['prompt'] ?? ''), 'items' => $items,
                'bins' => array_map(fn($b) => ['id' => (string) $b['id'], 'label' => $text($b['label'])], $lane['bins'] ?? [])],
                'solution' => array_column($lane['items'] ?? [], 'bin', 'id')];
        }
        if ($lane['engine'] === 'order') {
            // The steps, each with a letter for an id, shown in an order that is not theirs.
            $steps = [];
            foreach (array_values($lane['steps'] ?? []) as $i => $step) {
                $steps[] = ['id' => chr(ord('a') + $i), 'label' => $text(is_array($step) ? ($step['label'] ?? '') : $step)];
            }
            $shown = $steps;
            if (count($shown) > 1) {
                do {
                    shuffle($shown);
                } while (array_column($shown, 'id') === array_column($steps, 'id'));
            }
            return ['engine' => 'order', 'question' => ['id' => 'order', 'text' => $text($lane['prompt'] ?? ''),
                'options' => []], 'shown' => ['prompt' => $text($lane['prompt'] ?? ''), 'items' => $shown],
                'solution' => array_column($steps, 'id')];
        }
        // The mistakes of the Embrollo in a sheet: plain text and marked words, each a mistake.
        $parts = minigames::parts($text($lane['text'] ?? ''));
        $prompt = get_string('board_lane_errors', 'local_oksigeniatesape');
        $shown = array_map(fn($p) => ['text' => $p['text'], 'mark' => $p['mark'], 'wrong' => $p['wrong']], $parts);
        $marks = array_values(array_filter($parts, fn($p) => $p['mark'] >= 0));
        return ['engine' => 'errors', 'question' => ['id' => 'errors', 'text' => $prompt, 'options' => []],
            'shown' => ['parts' => $shown],
            'solution' => array_map(fn($p) => ['mark' => $p['mark'], 'right' => $p['right'], 'why' => $p['why']], $marks)];
    }

    /**
     * Whether what a player did in a lane of another engine undoes it: sorting, each thing in its group (one slip is
     * forgiven from five things on); putting in order, every step in its place (from five steps on, two next to each
     * other swapped are forgiven); the mistakes, each one touched and at most one word that was right.
     *
     * @param array $lane As lane_of() makes it.
     * @param array $payload classify: placed (item id => bin id); order: order (step ids); errors: marks (numbers), misses
     *     (words right touched).
     * @return bool
     */
    public static function lane_right(array $lane, array $payload): bool {
        if ($lane['engine'] === 'order') {
            $order = array_values(array_map('strval', (array) ($payload['order'] ?? [])));
            $solution = $lane['solution'];
            if (count($order) !== count($solution) || array_diff($solution, $order)) {
                return false;
            }
            // How many pairs are the wrong way round: one swap of two next to each other is one.
            $at = array_flip($order);
            $swapped = 0;
            for ($i = 0; $i < count($solution); $i++) {
                for ($j = $i + 1; $j < count($solution); $j++) {
                    $swapped += $at[$solution[$i]] > $at[$solution[$j]] ? 1 : 0;
                }
            }
            return $swapped <= intdiv(count($solution), 5);
        }
        if ($lane['engine'] === 'classify') {
            $placed = (array) ($payload['placed'] ?? []);
            $wrong = 0;
            foreach ($lane['solution'] as $item => $bin) {
                $wrong += ($placed[$item] ?? '') === $bin ? 0 : 1;
            }
            return $wrong <= intdiv(count($lane['solution']), 5);
        }
        $marks = array_map('intval', (array) ($payload['marks'] ?? []));
        $all = array_column($lane['solution'], 'mark');
        return !array_diff($all, $marks) && (int) ($payload['misses'] ?? 0) <= 1;
    }

    /**
     * The team a knot goes for: each team in turn, among those with players in (all of them, aloud).
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $round
     * @return int Group id (0: no teams).
     */
    private static function target(\stdClass $live, array $state, int $round): int {
        $teams = self::playing($live, $state);
        return $teams ? $teams[($round - 1) % count($teams)] : 0;
    }

    /**
     * The stage of the turn of a battle by turns, and the seconds it has left: intent, prep, strike or resolve.
     *
     * @param \stdClass $live
     * @param array $state
     * @param int $now
     * @return array [stage, seconds left] ('' when it is not a turn being played).
     */
    private static function stage(\stdClass $live, array $state, int $now): array {
        $now = self::clock($state, $now);
        $round = (int) $live->round;
        $info = $state['rounds'][$round] ?? null;
        if (!$info || empty($info['knot']) || $state['outcome'] !== '') {
            return ['', 0];
        }
        if (!empty($info['revealed'])) {
            return ['resolve', max(0, (int) ($info['revealedat'] ?? $now) + self::RESOLVE - $now)];
        }
        $startat = (int) ($info['startat'] ?? $now);
        if ($now < $startat + self::INTENT) {
            return ['intent', $startat + self::INTENT - $now];
        }
        if ($now < (int) $state['opensat']) {
            return ['prep', (int) $state['opensat'] - $now];
        }
        return ['strike', $state['closesat'] ? max(0, $state['closesat'] - 1 - $now) : -1];
    }

    /**
     * Moves a battle by turns on by itself when the time of a stage is over: the lazada closes and is counted, and after
     * the resolution the next turn starts, or the battle ends after the last one. Without a time per question, the teacher
     * leads: nothing moves by itself. Called on the polls of the class screen.
     *
     * @param \stdClass $live
     * @param int $actorid Who ends it, if it ends.
     * @param bool $canfinish They can end it (and give its prizes).
     * @param int|null $now
     * @return \stdClass The session.
     */
    public static function tick(\stdClass $live, int $actorid, bool $canfinish, ?int $now = null): \stdClass {
        $now = $now ?? time();
        $state = self::state($live);
        $paused = !empty($state['pausedat']);
        if ($live->kind !== 'battle' || empty($state['knots']) || !$state['secs'] || $state['outcome'] !== '' || $paused) {
            return $live;
        }
        [$stage, $left] = self::stage($live, $state, $now);
        $round = (int) $live->round;
        if ($stage === 'strike' && $state['closesat'] && $now >= $state['closesat']) {
            return self::reveal($live, $now, $round);
        }
        if ($stage === 'resolve' && $left === 0) {
            if ($round < count($state['questions']) && $state['hp'] > 0 && ($state['candles'] ?? 1) > 0) {
                return self::next($live, $now, $round);
            }
            return $canfinish ? self::finish($live, $actorid, $now) : $live;
        }
        return $live;
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
