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

use core_ai\aiactions\generate_text;

/**
 * The plan for the next class: what confuses the class, turned by the AI of the site into a short plan for the
 * teacher (what to review, a new example, a question to open the class and the classroom challenge that fits).
 *
 * Only counts and the content of the mission go to the AI, never a name. Without AI on the site, nothing changes.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class nextclass {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Seconds in which a new plan is not asked for: the one of a minute ago is still good. */
    public const WAIT = 60;

    /** @var int Days the question to open the class shows on the class screen. */
    public const OPENER_DAYS = 3;

    /** @var int Mix-ups sent to the AI at most. */
    private const MIXUPS = 12;

    /** @var int Plans kept for each course: older ones say nothing about the class of today. */
    private const KEEP = 5;

    /** @var string What is said to the AI when its answer did not have the shape asked for. */
    private const AGAIN = 'Your previous answer was not valid JSON with exactly that shape. Answer again with only the JSON.';

    /**
     * Whether the site has AI for this course, whether the teacher accepted its policy and whether they can use it.
     *
     * @param int $courseid
     * @param int $userid
     * @return array ai, policy, canuse.
     */
    public static function status(int $courseid, int $userid): array {
        $manager = \core\di::get(\core_ai\manager::class);
        $ai = $manager->is_action_available(generate_text::class);
        // Since Moodle 5.1 AI tools can be turned off for a course.
        if ($ai && method_exists($manager, 'is_action_enabled_in_context')) {
            $ai = $manager->is_action_enabled_in_context(\context_course::instance($courseid), generate_text::class);
        }
        $policy = \core_ai\manager::get_user_policy_status($userid);
        // Someone who has not accepted the policy and may not accept it (a public account of a demo site) cannot use it.
        $canuse = $ai && ($policy || has_capability('moodle/ai:acceptpolicy', \context_course::instance($courseid), $userid));
        return ['ai' => $ai, 'policy' => $policy, 'canuse' => $canuse];
    }

    /**
     * The teacher accepts the AI policy of the site.
     *
     * @param int $courseid
     * @param int $userid
     */
    public static function accept_policy(int $courseid, int $userid): void {
        // Whoever may not accept it (a public account of a demo site) does not, whatever the client sends.
        require_capability('moodle/ai:acceptpolicy', \context_course::instance($courseid), $userid);
        if (!\core_ai\manager::get_user_policy_status($userid)) {
            \core_ai\manager::user_policy_accepted($userid, \context_course::instance($courseid)->id);
        }
    }

    /**
     * The last plan of a course.
     *
     * @param int $courseid
     * @return array|null The plan, with its time and the name of its challenge.
     */
    public static function last(int $courseid): ?array {
        global $DB;
        $records = $DB->get_records(self::T . 'nextclass', ['courseid' => $courseid], 'id DESC', '*', 0, 1);
        $record = reset($records);
        if (!$record) {
            return null;
        }
        $plan = json_decode($record->data, true) ?: [];
        $challenges = self::challenges($courseid);
        $plan['challengename'] = $challenges[$plan['challenge'] ?? 0] ?? '';
        if ($plan['challengename'] === '') {
            $plan['challenge'] = 0;
        }
        $plan['time'] = (int) $record->timecreated;
        return $plan;
    }

    /**
     * The question to open the class, for the class screen, while it is recent.
     *
     * @param int $courseid
     * @param int $now
     * @return string Empty if there is none.
     */
    public static function opener(int $courseid, int $now): string {
        $plan = self::last($courseid);
        return $plan && $plan['time'] > $now - self::OPENER_DAYS * DAYSECS ? (string) $plan['opener'] : '';
    }

    /**
     * Asks the AI of the site for a plan for the next class, checks it and keeps it.
     *
     * @param int $courseid
     * @param int $userid The teacher who asks.
     * @param int|null $now
     * @return array The plan, as last() gives it.
     */
    public static function generate(int $courseid, int $userid, ?int $now = null): array {
        global $DB;
        $now = $now ?? time();
        $status = self::status($courseid, $userid);
        if (!$status['ai']) {
            throw new \moodle_exception('nextclass_noai', 'local_oksigeniatesape');
        }
        if (!$status['policy']) {
            throw new \moodle_exception('nextclass_policy', 'local_oksigeniatesape');
        }
        $last = self::last($courseid);
        if ($last && $last['time'] > $now - self::WAIT) {
            return $last;
        }
        $context = self::context($courseid);
        if (!$context) {
            throw new \moodle_exception('nextclass_nodata', 'local_oksigeniatesape');
        }
        $manager = \core\di::get(\core_ai\manager::class);
        $contextid = \context_course::instance($courseid)->id;
        $prompt = self::prompt($context, current_language());
        $plan = null;
        // A second try if the answer does not have the shape asked for: smaller models sometimes add words around it.
        foreach ([$prompt, $prompt . "\n\n" . self::AGAIN] as $text) {
            try {
                $response = $manager->process_action(new generate_text($contextid, $userid, $text));
            } catch (\Throwable $e) {
                // A provider that cannot be reached throws instead of answering: the teacher gets the plain message.
                throw new \moodle_exception('nextclass_failed', 'local_oksigeniatesape', '', null, $e->getMessage());
            }
            if (!$response->get_success()) {
                $error = $response->get_errormessage();
                throw new \moodle_exception('nextclass_failed', 'local_oksigeniatesape', '', null, $error);
            }
            $answer = (string) ($response->get_response_data()['generatedcontent'] ?? '');
            $plan = self::parse($answer, array_keys($context['challenges']));
            if ($plan) {
                break;
            }
        }
        if (!$plan) {
            throw new \moodle_exception('nextclass_failed', 'local_oksigeniatesape');
        }
        $record = ['courseid' => $courseid, 'data' => json_encode($plan), 'timecreated' => $now];
        $DB->insert_record(self::T . 'nextclass', (object) $record);
        $sql = 'SELECT id FROM {' . self::T . 'nextclass} WHERE courseid = ? ORDER BY id DESC';
        $old = $DB->get_fieldset_sql($sql, [$courseid]);
        if (count($old) > self::KEEP) {
            [$insql, $params] = $DB->get_in_or_equal(array_slice($old, self::KEEP));
            $DB->delete_records_select(self::T . 'nextclass', "id $insql", $params);
        }
        return self::last($courseid);
    }

    /**
     * What the AI is told about the class: the mission, how the class did on each objective, the mix-ups that came up
     * most and the classroom challenges of the course. Counts only, no names.
     *
     * @param int $courseid
     * @return array|null Null if nobody has played a scene yet.
     */
    public static function context(int $courseid): ?array {
        $mission = missions::get($courseid);
        $results = results::mission($courseid);
        if (!$mission || !$results) {
            return null;
        }
        $objectives = [];
        $mixups = [];
        foreach ($results['chapters'] as $chapter) {
            foreach ($chapter['scenes'] as $scene) {
                foreach ($scene['objectives'] as $o) {
                    $objectives[$o['code']] = $objectives[$o['code']] ?? ['name' => $o['name'], 'ok' => 0, 'total' => 0];
                    $objectives[$o['code']]['ok'] += $o['ok'];
                    $objectives[$o['code']]['total'] += $o['total'];
                }
                foreach ($scene['confusions'] as $c) {
                    $mixups[] = ['question' => $c['question'], 'answer' => $c['text'], 'count' => $c['count']];
                }
            }
        }
        foreach ($results['games'] as $game) {
            foreach ($game['confusions'] as $c) {
                $mixups[] = ['question' => $game['title'], 'answer' => $c['text'], 'count' => $c['count']];
            }
        }
        if (!$objectives && !$mixups) {
            return null;
        }
        usort($mixups, fn($a, $b) => $b['count'] <=> $a['count']);
        $chapters = [];
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            $chapters[] = missions::text($chapter['name'] ?? $chapter['code'], $mission->skin);
        }
        return [
            'title' => missions::text($mission->data['title'] ?? $mission->code, $mission->skin),
            'ages' => (string) ($mission->data['ages'] ?? ''),
            'place' => (string) ($mission->data['variants'][$mission->variant] ?? $mission->variant),
            'players' => $results['players'],
            'chapters' => $chapters,
            'objectives' => $objectives,
            'mixups' => array_slice($mixups, 0, self::MIXUPS),
            'challenges' => self::challenges($courseid),
        ];
    }

    /**
     * The instructions for the AI.
     *
     * @param array $context As context() gives it.
     * @param string $lang Language of the plan.
     * @return string
     */
    public static function prompt(array $context, string $lang): string {
        // "Spanish; Castilian" in the list of Moodle: the first name is enough.
        $names = get_string_manager()->get_list_of_languages('en');
        $language = trim(explode(';', $names[substr($lang, 0, 2)] ?? $lang)[0]);
        $lines = [
            'You help a primary school teacher prepare the next class. The class plays Tesapé, a role-playing game in ' .
                'which each mission teaches a subject; below is how the class did in the mission (counts only, no names) ' .
                'and what confused it most.',
            '',
            "Mission: {$context['title']} (ages {$context['ages']}, {$context['players']} students). Examples of the " .
                "students' everyday life in: {$context['place']}.",
            'Chapters of the mission: ' . implode('; ', $context['chapters']) . '.',
            '',
            'Objectives (right at the first try / tries):',
        ];
        foreach ($context['objectives'] as $code => $o) {
            $pct = $o['total'] ? round(100 * $o['ok'] / $o['total']) : 0;
            $lines[] = "- {$code} {$o['name']}: {$o['ok']}/{$o['total']} ({$pct} %)";
        }
        $lines[] = '';
        $lines[] = 'Most common mix-ups (question or game → wrong answer chosen, times):';
        foreach ($context['mixups'] as $m) {
            $lines[] = "- «{$m['question']}» → «{$m['answer']}» ({$m['count']})";
        }
        if (!$context['mixups']) {
            $lines[] = '- (none yet)';
        }
        $lines[] = '';
        $lines[] = 'Classroom challenges of the course (id: name):';
        foreach ($context['challenges'] as $id => $name) {
            $lines[] = "- {$id}: {$name}";
        }
        if (!$context['challenges']) {
            $lines[] = '- (none)';
        }
        $lines[] = '';
        $lines[] = "Write a short plan for the next class, in {$language}, for the teacher:";
        $lines[] = '- what to review first: one to three points, each with how to do it in class in a few minutes;';
        $lines[] = "- one new example from the students' everyday life in {$context['place']};";
        $lines[] = '- one question to open the class, to ask aloud, that makes them think (not a quiz question that gives ' .
            'the answer away), in plain words a ten-year-old understands;';
        $lines[] = '- the classroom challenge that fits best, by its id, and why (0 and an empty reason if none fits).';
        $lines[] = '';
        $lines[] = 'Rules: scientifically correct and objective, without ideology, campaigns or catastrophism; no political ' .
            'or institutional agendas (no 2030 Agenda, no sustainable development goals); the environment as concrete ' .
            'ecosystems and what people do in them, never climate change or global warming; people appear for what they ' .
            'did, never framed by their sex (no «girls in science», no international days); standard ' .
            "{$language} with the generic masculine; speak to the teacher informally (in Spanish, «tú»); what is said " .
            'to the whole class, like the question to open it, in Spanish with «ustedes» and never «vosotros» (no ' .
            '«encontráis» or «sabríais»: «encuentran», «sabrían»); never the Spanish verb «coger» in any form; no ' .
            'names of students; short sentences.';
        $lines[] = '';
        $lines[] = 'Answer only with JSON, without Markdown, with exactly this shape:';
        $lines[] = '{"summary": "one sentence: what confuses the class most", "review": [{"point": "...", "how": "..."}], ' .
            '"example": "...", "opener": "...", "challenge": 0, "challengewhy": "..."}';
        return implode("\n", $lines);
    }

    /**
     * Reads the answer of the AI and checks its shape.
     *
     * @param string $text The answer.
     * @param int[] $challenges Ids of the classroom challenges it could choose.
     * @return array|null The plan, or null if it does not have the shape asked for.
     */
    public static function parse(string $text, array $challenges): ?array {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false) {
            return null;
        }
        $data = json_decode(substr($text, $start, $end - $start + 1), true);
        if (!is_array($data)) {
            return null;
        }
        $clean = fn($value, int $max) => \core_text::substr(trim(clean_param((string) $value, PARAM_TEXT)), 0, $max);
        $review = [];
        foreach (array_slice(is_array($data['review'] ?? null) ? $data['review'] : [], 0, 3) as $item) {
            $point = $clean($item['point'] ?? '', 300);
            if ($point !== '') {
                $review[] = ['point' => $point, 'how' => $clean($item['how'] ?? '', 600)];
            }
        }
        $plan = [
            'summary' => $clean($data['summary'] ?? '', 400),
            'review' => $review,
            'example' => $clean($data['example'] ?? '', 600),
            'opener' => $clean($data['opener'] ?? '', 400),
            'challenge' => (int) ($data['challenge'] ?? 0),
            'challengewhy' => $clean($data['challengewhy'] ?? '', 400),
        ];
        if ($plan['summary'] === '' || !$review || $plan['opener'] === '') {
            return null;
        }
        if (!in_array($plan['challenge'], $challenges, true)) {
            $plan['challenge'] = 0;
            $plan['challengewhy'] = '';
        }
        return $plan;
    }

    /**
     * The classroom challenges of a course.
     *
     * @param int $courseid
     * @return string[] Name by id.
     */
    private static function challenges(int $courseid): array {
        global $DB;
        $where = ['courseid' => $courseid, 'kind' => 'classroom'];
        $challenges = $DB->get_records_menu(self::T . 'challenge', $where, 'id', 'id, name');
        return array_map(fn($name) => format_string((string) $name), $challenges);
    }
}
