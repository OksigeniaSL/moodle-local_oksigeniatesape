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
 * The shop of the naturalist, «Do you want to know more?»: short readings of the mission, outside what is essential,
 * bought with gems. Read, and with their questions right, they give the gems back and some more, a seal of curiosity
 * and a golden card of their topic: curiosity does not come dear. They give no knowledge.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class curiosities {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string What a curiosity bought is called among the purchases. */
    private const BOUGHT = 'curio-';

    /** @var string What a curiosity read and understood is called among what was found. */
    public const DONE = 'curio';

    /** @var string What a curiosity whose fact was told to the class is called among what was found. */
    public const TOLD = 'curiofact';

    /** @var int Books of the library of a team that bring it the lantern of its banner. */
    public const LIBRARY_BANNER = 3;

    /** @var int Breath for each one of the team when one of them tells the class the fact of the explorer. */
    public const FACT_BREATH = 1;

    /**
     * The curiosities of the mission of a course, for a student: each one with whether they bought it and read it.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @return array code, title, teaser, price, back, bought, done.
     */
    public static function for_student(int $courseid, \stdClass $char): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || empty($mission->data['curiosities'])) {
            return [];
        }
        $bought = $DB->get_fieldset_select(self::T . 'purchase', 'code', 'charid = ? AND courseid = ? AND ' .
            $DB->sql_like('code', '?'), [$char->id, $courseid, self::BOUGHT . '%']);
        $done = self::done($courseid, (int) $char->id);
        $out = [];
        foreach ($mission->data['curiosities'] as $code => $c) {
            $out[] = [
                'code' => (string) $code,
                'title' => self::text($mission, $c['title'] ?? $code),
                'teaser' => self::text($mission, $c['teaser'] ?? ''),
                'price' => (int) ($c['price'] ?? 0),
                'back' => (int) ($c['back'] ?? 0),
                'bought' => in_array(self::BOUGHT . $code, $bought, true),
                'done' => in_array((string) $code, $done, true),
            ];
        }
        return $out;
    }

    /**
     * The codes of the curiosities a student has read with their questions right in a course: their seals.
     *
     * @param int $courseid
     * @param int $charid
     * @return string[]
     */
    public static function done(int $courseid, int $charid): array {
        global $DB;
        // In the order they were read.
        return $DB->get_fieldset_sql(
            'SELECT code FROM {' . self::T . 'found} WHERE charid = ? AND courseid = ? AND kind = ? ORDER BY id',
            [$charid, $courseid, self::DONE]
        );
    }

    /**
     * A student buys a curiosity with their gems.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param int|null $now
     */
    public static function buy(int $courseid, int $userid, string $code, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        [$mission, $char, $member, $c] = self::of($courseid, $userid, $code);
        engine::one_at_a_time((int) $char->id, function () use ($DB, $courseid, $code, $char, $member, $c, $now) {
            if (
                $DB->record_exists(self::T . 'purchase', ['charid' => $char->id, 'courseid' => $courseid,
                    'code' => self::BOUGHT . $code])
            ) {
                throw new \moodle_exception('alreadyowned', 'local_oksigeniatesape');
            }
            $price = (int) $c['price'];
            if ((int) characters::get((int) $char->userid)->gems < $price) {
                throw new \moodle_exception('notenoughgems', 'local_oksigeniatesape');
            }
            $transaction = $DB->start_delegated_transaction();
            engine::gems($member, -$price, 'curiosity', 0, 0, 0, $now);
            $DB->insert_record(self::T . 'purchase', (object) ['charid' => $char->id, 'courseid' => $courseid,
                'groupid' => 0, 'code' => self::BOUGHT . $code, 'amount' => $price, 'timecreated' => $now]);
            $transaction->allow_commit();
        });
    }

    /**
     * A curiosity to read, once bought: its pages and its questions, with their answers shuffled.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @return array code, title, pages, questions (prompt, options, answer, why), fact, back, done.
     */
    public static function read(int $courseid, int $userid, string $code): array {
        global $DB;
        [$mission, $char, , $c] = self::of($courseid, $userid, $code);
        if (
            !$DB->record_exists(self::T . 'purchase', ['charid' => $char->id, 'courseid' => $courseid,
                'code' => self::BOUGHT . $code])
        ) {
            throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
        }
        $questions = [];
        foreach ($c['questions'] ?? [] as $q) {
            $options = array_map(fn($o) => self::text($mission, $o), (array) ($q['options'] ?? []));
            shuffle($options);
            $questions[] = ['prompt' => self::text($mission, $q['prompt'] ?? ''), 'options' => $options,
                'answer' => self::text($mission, $q['answer'] ?? ''), 'why' => self::text($mission, $q['why'] ?? '')];
        }
        return [
            'code' => $code,
            'title' => self::text($mission, $c['title'] ?? $code),
            'pages' => array_values(array_map(fn($p) => self::text($mission, $p), (array) ($c['pages'] ?? []))),
            'questions' => $questions,
            'fact' => self::text($mission, $c['fact'] ?? ''),
            'back' => (int) ($c['back'] ?? 0),
            'done' => in_array($code, self::done($courseid, (int) $char->id), true),
        ];
    }

    /**
     * A student ends a curiosity with its questions answered: right all of them, the first time it gives the gems back
     * and some more, a seal of curiosity and the golden card of its topic.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param string[] $answers One for each question, in their order.
     * @param int|null $now
     * @return array right (all of them), first (the first time), gems, seals.
     */
    public static function finish(int $courseid, int $userid, string $code, array $answers, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $read = self::read($courseid, $userid, $code);
        [$mission, $char, $member] = self::of($courseid, $userid, $code);
        $right = count($answers) === count($read['questions']);
        foreach ($read['questions'] as $i => $q) {
            $right = $right && \core_text::strtolower(trim((string) ($answers[$i] ?? ''))) === \core_text::strtolower($q['answer']);
        }
        $first = false;
        if ($right) {
            $first = engine::one_at_a_time((int) $char->id, function () use (
                $DB,
                $courseid,
                $code,
                $char,
                $member,
                $mission,
                $read,
                $now
) {
                $key = ['charid' => $char->id, 'courseid' => $courseid, 'kind' => self::DONE, 'code' => $code];
                if ($DB->record_exists(self::T . 'found', $key)) {
                    return false;
                }
                $transaction = $DB->start_delegated_transaction();
                $DB->insert_record(self::T . 'found', (object) ($key + ['missionid' => $mission->id, 'timecreated' => $now]));
                if ($read['back'] > 0) {
                    engine::gems($member, $read['back'], 'curiosity', 0, 0, 0, $now);
                }
                $transaction->allow_commit();
                return true;
            });
        }
        if ($first) {
            badges::check_explorer($courseid, $userid);
        }
        return ['right' => $right, 'first' => $first, 'gems' => $first ? $read['back'] : 0,
            'seals' => count(self::done($courseid, (int) $char->id))];
    }

    /**
     * The library of a team: a book for each curiosity one of them read with its questions right. With three, the
     * lantern of its banner is the team's.
     *
     * @param int $courseid
     * @param int $groupid
     * @return array books, titles, banner (it brought the lantern of the banner).
     */
    public static function library(int $courseid, int $groupid): array {
        global $DB;
        $mission = missions::get($courseid);
        $members = teams::members($courseid, $groupid, true);
        $books = [];
        if ($mission && $members) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($m) => (int) $m->charid, $members));
            $found = $DB->get_records_select(
                self::T . 'found',
                "charid $insql AND courseid = ? AND kind = ?",
                array_merge($params, [$courseid, self::DONE]),
                'timecreated',
                'id, code'
            );
            foreach ($found as $book) {
                $books[] = self::text($mission, $mission->data['curiosities'][$book->code]['title'] ?? $book->code);
            }
        }
        return ['books' => count($books), 'titles' => $books, 'banner' => count($books) >= self::LIBRARY_BANNER];
    }

    /**
     * The curiosities a student read with their questions right, for their teacher: whether they told the class their
     * fact yet.
     *
     * @param int $courseid
     * @param int $charid
     * @return array code, title, fact, told.
     */
    public static function for_teacher(int $courseid, int $charid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || empty($mission->data['curiosities'])) {
            return [];
        }
        $told = $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            'charid = ? AND courseid = ? AND kind = ?',
            [$charid, $courseid, self::TOLD]
        );
        $out = [];
        foreach (self::done($courseid, $charid) as $code) {
            $c = $mission->data['curiosities'][$code] ?? [];
            $out[] = ['code' => $code, 'title' => self::text($mission, $c['title'] ?? $code),
                'fact' => self::text($mission, $c['fact'] ?? ''), 'told' => in_array($code, $told, true)];
        }
        return $out;
    }

    /**
     * The teacher marks that a student told the class the fact of the explorer of a curiosity: what one learnt, shared.
     * Each one of their team who plays gets breath for their powers, once for each curiosity.
     *
     * @param int $courseid
     * @param int $userid The student.
     * @param string $code
     * @param int $actorid The teacher.
     * @param int|null $now
     * @return int Those who got breath.
     */
    public static function told(int $courseid, int $userid, string $code, int $actorid, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $mission = missions::get($courseid);
        if (!$char || !$mission || !in_array($code, self::done($courseid, (int) $char->id), true)) {
            throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
        }
        $key = ['charid' => $char->id, 'courseid' => $courseid, 'kind' => self::TOLD, 'code' => $code];
        if ($DB->record_exists(self::T . 'found', $key)) {
            return 0;
        }
        $DB->insert_record(self::T . 'found', (object) ($key + ['missionid' => $mission->id, 'timecreated' => $now]));
        // The breath for the team: those who play in it, or only them without a team.
        $team = teams::of($courseid, $userid);
        $members = $team ? teams::members($courseid, (int) $team->id) : [engine::member($char, $courseid)];
        foreach ($members as $member) {
            engine::breath($member, self::FACT_BREATH, 'curiosityfact', $actorid, 0, $now);
        }
        return count($members);
    }

    /**
     * What a curiosity needs: the mission, the character playing in the course and the curiosity.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @return array [mission, char, member, curiosity]
     */
    private static function of(int $courseid, int $userid, string $code): array {
        $mission = missions::get($courseid);
        $c = $mission ? ($mission->data['curiosities'][$code] ?? null) : null;
        $char = characters::get($userid);
        $member = $char ? engine::member($char, $courseid) : null;
        if (!$c || !$member || $member->status === 'paused' || !regions::enabled($courseid)) {
            throw new \moodle_exception('nomarket', 'local_oksigeniatesape');
        }
        return [$mission, $char, $member, $c];
    }

    /**
     * A text of the mission, in its story and with its local examples.
     *
     * @param \stdClass $mission
     * @param mixed $text
     * @return string
     */
    private static function text(\stdClass $mission, $text): string {
        return missions::text($text, (string) $mission->skin, (string) $mission->variant);
    }
}
