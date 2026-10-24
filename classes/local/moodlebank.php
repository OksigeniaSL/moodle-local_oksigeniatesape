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
 * The question bank of Moodle as a source of the battles: the teacher picks a category of the banks of the course
 * (the course itself up to Moodle 4.5, its question banks from Moodle 5.0), and half of the questions of the battles
 * without turns come from it, mixed with those of the mission. Only questions the class screen can show as they are:
 * the last ready version of a multiple choice question with one right answer (two to five options) or of a true or
 * false one, as plain text. Their ids in the game are «mb» and the id of the question.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class moodlebank {
    /** @var string Prefix of the ids of these questions in the game. */
    public const PREFIX = 'mb';

    /** @var int Most options a question can have to be shown. */
    public const MOST = 5;

    /**
     * The contexts whose question categories a course can use: the course, and its question banks where Moodle has them.
     *
     * @param int $courseid
     * @return int[] Context ids.
     */
    public static function contexts(int $courseid): array {
        $ids = [(int) games::context($courseid)->id];
        if (\core_component::get_component_directory('mod_qbank')) {
            foreach (get_fast_modinfo(games::course($courseid))->get_instances_of('qbank') as $cm) {
                $ids[] = (int) \context_module::instance($cm->id)->id;
            }
        }
        return $ids;
    }

    /**
     * The categories a teacher can choose for the battles of a course, with how many questions of them can be shown.
     *
     * @param int $courseid
     * @return array Each: id, name, count.
     */
    public static function categories(int $courseid): array {
        global $DB;
        $contexts = self::contexts($courseid);
        [$in, $params] = $DB->get_in_or_equal($contexts);
        $cats = $DB->get_records_select(
            'question_categories',
            "contextid $in AND parent <> 0",
            $params,
            'contextid, sortorder, name',
            'id, name, contextid'
        );
        $banks = [];
        $out = [];
        foreach ($cats as $cat) {
            $context = \context::instance_by_id((int) $cat->contextid);
            if (!has_capability('moodle/question:useall', $context)) {
                continue;
            }
            if ($context->contextlevel === CONTEXT_MODULE) {
                $banks[$cat->contextid] = $banks[$cat->contextid] ?? $context->get_context_name(false, true);
            }
            $name = format_string($cat->name, true, ['context' => $context]);
            $out[] = ['id' => (int) $cat->id, 'name' => isset($banks[$cat->contextid]) ? $banks[$cat->contextid] . ' · ' . $name :
                $name, 'count' => count(self::rows((int) $cat->id))];
        }
        return $out;
    }

    /**
     * Whether a teacher can choose a category for a course.
     *
     * @param int $courseid
     * @param int $categoryid
     * @return bool
     */
    public static function allowed(int $courseid, int $categoryid): bool {
        return in_array($categoryid, array_column(self::categories($courseid), 'id'), true);
    }

    /**
     * The ids in the game of the questions of the category a course uses, if it uses one.
     *
     * @param int $courseid
     * @return string[]
     */
    public static function usable(int $courseid): array {
        global $DB;
        $region = regions::get($courseid);
        $category = $region ? regions::rule($region, 'qbank') : 0;
        // Only a category of the banks of this course (a course restored elsewhere keeps the number of another one).
        $contextid = $category ? (int) $DB->get_field('question_categories', 'contextid', ['id' => $category]) : 0;
        if (!$contextid || !in_array($contextid, self::contexts($courseid), true)) {
            return [];
        }
        return array_map(fn($id) => self::PREFIX . $id, array_keys(self::rows($category)));
    }

    /**
     * A question of the bank as the class screen shows it, with its options in an order that only depends on the seed.
     * Only from the banks of the course.
     *
     * @param int $courseid
     * @param string $id Its id in the game.
     * @param int $seed
     * @return array|null As questions::get().
     */
    public static function get(int $courseid, string $id, int $seed): ?array {
        global $DB;
        $questionid = (int) substr($id, strlen(self::PREFIX));
        $category = (int) $DB->get_field_sql(
            'SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qv.questionid = ?',
            [$questionid]
        );
        $contextid = $category ? (int) $DB->get_field('question_categories', 'contextid', ['id' => $category]) : 0;
        $row = in_array($contextid, self::contexts($courseid), true) ? (self::rows($category)[$questionid] ?? null) : null;
        if (!$row) {
            return null;
        }
        $options = [];
        foreach ($row['answers'] as $i => $answer) {
            $options[] = ['text' => $answer['text'], 'right' => $answer['right'], 'order' => crc32($seed . ':' . $i . ':' . $id)];
        }
        usort($options, fn($a, $b) => $a['order'] <=> $b['order']);
        $correct = (int) array_search(true, array_column($options, 'right'), true);
        return ['id' => $id, 'chapter' => '', 'objective' => '', 'text' => $row['text'],
            'options' => array_column($options, 'text'), 'correct' => $correct, 'why' => $row['why']];
    }

    /**
     * The questions of a category that can be shown, by question id: the last ready version of each one.
     *
     * @param int $categoryid
     * @return array questionid => text, why, answers (text, right).
     */
    private static function rows(int $categoryid): array {
        global $DB;
        $questions = $DB->get_records_sql(
            "SELECT q.id, q.qtype, q.questiontext, q.questiontextformat, q.generalfeedback, q.generalfeedbackformat
               FROM {question_bank_entries} qbe
               JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               JOIN {question} q ON q.id = qv.questionid
          LEFT JOIN {qtype_multichoice_options} mc ON mc.questionid = q.id
              WHERE qbe.questioncategoryid = ? AND qv.status = 'ready'
                AND qv.version = (SELECT MAX(v.version) FROM {question_versions} v WHERE v.questionbankentryid = qbe.id)
                AND (q.qtype = 'truefalse' OR (q.qtype = 'multichoice' AND mc.single = 1))
           ORDER BY q.id",
            [$categoryid]
        );
        if (!$questions) {
            return [];
        }
        [$in, $params] = $DB->get_in_or_equal(array_keys($questions));
        $answers = $DB->get_records_select(
            'question_answers',
            "question $in",
            $params,
            'question, id',
            'id, question, answer, answerformat, fraction, feedback, feedbackformat'
        );
        $by = [];
        foreach ($answers as $answer) {
            $by[(int) $answer->question][] = $answer;
        }
        $rows = [];
        foreach ($questions as $q) {
            $list = [];
            $why = self::plain((string) $q->generalfeedback, (int) $q->generalfeedbackformat);
            foreach ($by[(int) $q->id] ?? [] as $answer) {
                $right = (float) $answer->fraction >= 0.9999;
                $list[] = ['text' => self::plain((string) $answer->answer, (int) $answer->answerformat), 'right' => $right];
                if ($right && $why === '') {
                    $why = self::plain((string) $answer->feedback, (int) $answer->feedbackformat);
                }
            }
            $text = self::plain((string) $q->questiontext, (int) $q->questiontextformat);
            $rights = count(array_filter(array_column($list, 'right')));
            $empty = in_array('', array_column($list, 'text'), true);
            if ($text !== '' && $rights === 1 && !$empty && count($list) >= 2 && count($list) <= self::MOST) {
                $rows[(int) $q->id] = ['text' => $text, 'why' => $why, 'answers' => $list];
            }
        }
        return $rows;
    }

    /**
     * A text of the bank as plain text: the screens of the game show no images nor formulas.
     *
     * @param string $text
     * @param int $format
     * @return string
     */
    private static function plain(string $text, int $format): string {
        $html = format_text($text, $format, ['para' => false, 'context' => \context_system::instance()]);
        $html = preg_replace('/<(br|\/p|\/div|\/li)[^>]*>/i', ' ', $html);
        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $plain));
    }
}
