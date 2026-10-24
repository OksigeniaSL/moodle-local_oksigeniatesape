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
 * What a teacher changes in the mission of a course: the texts of its scenes and places, and the questions of the
 * battle. The changes are kept apart, as a layer over the mission: a newer version of the mission does not take them
 * away, and going back to the original is taking the change away.
 *
 * A text is found by its path in the definition, with the ids of the options, items, bins and steps rather than their
 * places in the list: «nodes/elegir/options/semilla/feedback», «chapters/claro/sessions/0/do». A changed text is one
 * plain text for every story and every set of local examples.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class edits {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Longest text of one field. */
    public const MAX_TEXT = 4000;

    /** @var int Longest piece of the information shared out: read at a glance on a tablet, as missions are checked. */
    public const MAX_PIECE = 220;

    /** @var int Most questions of a mission. */
    public const MAX_QUESTIONS = 80;

    /** @var string In place of a scene, the changes of the mission itself (its places and questions). */
    public const MISSION = '#';

    /** @var string The switch of the sniffing of the creature in a scene: on or off, not a text. */
    public const SNIFF = 'sniff';

    /** @var string The switch of a scene in the game: «off» takes it out of the map, the class screen and the lanterns. */
    public const ENABLED = 'enabled';

    /** @var string The secrets the teacher hides in the paintings of the places, besides those of the mission. */
    public const OWNSECRETS = 'ownsecrets';

    /** @var int Most secrets of the teacher in a mission. */
    public const MAX_SECRETS = 12;

    /** @var int Longest text of a secret of the teacher. */
    public const MAX_SECRET = 300;

    /** @var string[] Fields with one text, in a scene, a node or an element of a list. */
    private const TEXTS = ['title', 'whatif', 'text', 'prompt', 'label', 'say', 'feedback', 'intro', 'offscreen', 'do', 'materials',
        'name', 'claim', 'laugh', 'weakness', 'why', 'ask', 'expect', 'mistake', 'redirect', 'spare', 'cardtitle',
        'reveal_after', 'after'];

    /** @var string[] The easy reading of a text of a node: its key => the key of the text it says again. */
    public const EASY = ['easy' => 'text', 'easyprompt' => 'prompt'];

    /** @var string[] Lists of elements with an id, in a node. */
    private const ELEMENTS = ['options', 'items', 'bins', 'steps', 'spots'];

    /**
     * The class passes a place by: its lantern is lit without playing it, and the expedition goes on in the next one.
     * Not the place of the final battle, nor one whose lantern is lit already. Undone, its lantern goes off again.
     *
     * @param int $courseid
     * @param string $code Of the chapter.
     * @param bool $skip
     * @param int|null $now
     */
    public static function skip(int $courseid, string $code, bool $skip, ?int $now = null): void {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => $code], '*', MUST_EXIST);
        if ($skip) {
            if ($chapter->litat || $code === self::final_place($mission)) {
                throw new \moodle_exception('wsnoskip', 'local_oksigeniatesape');
            }
            $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'litat' => $now, 'skippedat' => $now,
                'readyat' => 0, 'timemodified' => $now]);
        } else if ($chapter->skippedat) {
            $DB->update_record(self::T . 'chapter', (object) ['id' => $chapter->id, 'litat' => 0, 'skippedat' => 0,
                'timemodified' => $now]);
        }
    }

    /**
     * The classroom challenge of a place: the one its students see on the map, and the class does at the end of its
     * sessions. One of the classroom challenges of the course, or none.
     *
     * @param int $courseid
     * @param string $code Of the chapter.
     * @param int $challengeid 0: none.
     */
    public static function place_challenge(int $courseid, string $code, int $challengeid): void {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $chapter = $DB->get_record(self::T . 'chapter', ['missionid' => $mission->id, 'code' => $code], '*', MUST_EXIST);
        if ($challengeid && !$DB->record_exists(self::T . 'challenge', ['id' => $challengeid, 'courseid' => $courseid, 'cmid' => 0])) {
            throw new \invalid_parameter_exception('Not a classroom challenge of the course');
        }
        $DB->set_field(self::T . 'challenge', 'chapterid', 0, ['courseid' => $courseid, 'cmid' => 0, 'chapterid' => $chapter->id]);
        if ($challengeid) {
            $DB->set_field(self::T . 'challenge', 'chapterid', $chapter->id, ['id' => $challengeid]);
        }
    }

    /**
     * The place whose lantern the final battle lights: it cannot be passed by.
     *
     * @param \stdClass $mission
     * @return string
     */
    private static function final_place(\stdClass $mission): string {
        $codes = array_column($mission->data['chapters'] ?? [], 'code');
        return (string) ($mission->data['battle']['final']['lantern'] ?? end($codes));
    }

    /**
     * Whether a change is a list kept as JSON: the questions of the battle, the secrets of the teacher, or the questions
     * of a knot.
     *
     * @param string $path
     * @return bool
     */
    private static function is_list(string $path): bool {
        return $path === 'questions' || $path === self::OWNSECRETS || (bool) preg_match('#^battle/knots/[A-Za-z0-9_-]+/(questions|taken)$#', $path);
    }

    /**
     * Lanes for a knot of the battle, drafted by the AI of the site from what the Embrollo claims and why it is wrong:
     * three questions (the right answer first, the others mistakes of the Embrollo) and two boasts of the Embrollo. Only
     * a proposal: nothing is kept until the teacher takes it, one by one.
     *
     * @param int $courseid
     * @param string $code Of the knot.
     * @param int $userid
     * @return array questions (text, options, why) and laughs.
     */
    public static function knot_proposal(int $courseid, string $code, int $userid): array {
        $status = nextclass::status($courseid, $userid);
        if (!$status['ai'] || !$status['canuse']) {
            throw new \moodle_exception('authoring_noai', 'local_oksigeniatesape');
        }
        if (!$status['policy']) {
            throw new \moodle_exception('authoring_aipolicy', 'local_oksigeniatesape');
        }
        $mission = missions::get($courseid);
        $knot = $mission ? knots::get($mission, $code) : null;
        if (!$knot) {
            throw new \invalid_parameter_exception('Not a knot of the mission');
        }
        $skin = (string) $mission->skin;
        $objective = (string) ($mission->data['objectives'][self::knot($mission, $code)['objective'] ?? ''] ?? '');
        $prompt = "You write questions for the battle of an educational role-playing game for a class of students aged " .
            ($mission->data['ages'] ?? '9-11') . ", in the language of this code: " . current_language() . ". The Embrollo is " .
            "a funny monster made of tangled ideas; each knot is a mistaken idea it claims. The teacher will read every " .
            "question and keep only the good ones.\n\n" .
            "The knot: " . missions::text($knot['name'] ?? '', $skin) . "\n" .
            "What the Embrollo claims: " . missions::text($knot['claim'] ?? '', $skin) . "\n" .
            "Why it is wrong: " . missions::text($knot['why'] ?? '', $skin) . "\n" .
            "Objective: " . missions::text($objective, $skin) . "\n\n" .
            "Rules: correct and checked knowledge, without opinions, politics or agendas; short sentences for that age; " .
            "each question has one right answer and two or three wrong ones that are the mistakes the Embrollo would make; " .
            "the explanation says why the right one is right in one or two sentences; the boasts are one line each, in the " .
            "voice of the Embrollo, funny and wrong, never mocking anybody; no real names of people.\n\n" .
            "Answer with only this JSON: {\"questions\": [{\"text\": text, \"options\": [the right answer first, then 2 " .
            "or 3 wrong ones], \"why\": text}] (exactly 3), \"laughs\": [text, text]}";
        $manager = \core\di::get(\core_ai\manager::class);
        $contextid = games::context($courseid)->id;
        $again = 'Your previous answer was not valid JSON with exactly that shape. Answer again with only the JSON.';
        foreach ([$prompt, $prompt . "\n\n" . $again] as $text) {
            try {
                $response = $manager->process_action(new \core_ai\aiactions\generate_text($contextid, $userid, $text));
            } catch (\Throwable $e) {
                throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape', '', null, $e->getMessage());
            }
            if (!$response->get_success()) {
                $why = $response->get_errormessage();
                throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape', '', null, $why);
            }
            $answer = (string) ($response->get_response_data()['generatedcontent'] ?? '');
            $start = strpos($answer, '{');
            $end = strrpos($answer, '}');
            $data = $start !== false && $end !== false ? json_decode(substr($answer, $start, $end - $start + 1), true) : null;
            $proposal = is_array($data) ? self::clean_proposal($data) : null;
            if ($proposal && $proposal['questions']) {
                return $proposal;
            }
        }
        throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape');
    }

    /**
     * A proposal of the AI cleaned as anything a teacher writes: questions with a text and two to four options, and up to
     * two boasts.
     *
     * @param array $data
     * @return array questions and laughs.
     */
    public static function clean_proposal(array $data): array {
        $text = fn($value, int $max) => \core_text::substr(trim(clean_param((string) $value, PARAM_TEXT)), 0, $max);
        $questions = [];
        foreach (array_slice((array) ($data['questions'] ?? []), 0, 3) as $q) {
            if (!is_array($q)) {
                continue;
            }
            $options = array_map(fn($o) => $text($o, 200), array_slice((array) ($q['options'] ?? []), 0, 4));
            $options = array_values(array_filter($options, fn($o) => $o !== ''));
            $question = $text($q['text'] ?? '', 500);
            if ($question !== '' && count($options) >= 2) {
                $questions[] = ['text' => $question, 'options' => $options, 'why' => $text($q['why'] ?? '', 500)];
            }
        }
        $laughs = array_map(fn($l) => $text($l, 200), array_slice((array) ($data['laughs'] ?? []), 0, 2));
        $laughs = array_values(array_filter($laughs, fn($l) => $l !== ''));
        return ['questions' => $questions, 'laughs' => $laughs];
    }

    /**
     * A knot of the battle as the mission of the course has it (with the changes of the teachers): its place, objective
     * and questions too.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @return array|null
     */
    private static function knot(\stdClass $mission, string $code): ?array {
        foreach ($mission->data['battle']['knots'] ?? [] as $knot) {
            if (($knot['code'] ?? '') === $code) {
                return $knot;
            }
        }
        return null;
    }

    /**
     * A question the teacher takes for a knot: it joins the questions of the battle, in the place and objective of the
     * knot, and the lanes of the knot.
     *
     * @param int $courseid
     * @param string $code Of the knot.
     * @param array $question text, options (the right one first), why.
     * @return string The id of the question.
     */
    public static function knot_accept(int $courseid, string $code, array $question): string {
        $mission = missions::get($courseid);
        $knot = $mission ? self::knot($mission, $code) : null;
        if (!$knot) {
            throw new \invalid_parameter_exception('Not a knot of the mission');
        }
        $clean = self::clean_proposal(['questions' => [$question]])['questions'];
        if (!$clean) {
            throw new \invalid_parameter_exception('A question needs its text and two options');
        }
        $codes = array_column($mission->data['chapters'] ?? [], 'code');
        $list = self::view($courseid)['questions'];
        $list[] = $clean[0] + ['id' => '', 'chapter' => (string) ($codes[(int) ($knot['chapter'] ?? 1) - 1] ?? ''),
            'objective' => (string) ($knot['objective'] ?? '')];
        $saved = self::save_questions($courseid, $list);
        $id = (string) end($saved)['id'];
        $mine = array_values(array_unique(array_merge((array) ($knot['questions'] ?? []), [$id])));
        self::store($courseid, (string) $mission->code, self::MISSION, 'battle/knots/' . $code . '/questions', json_encode($mine));
        // A knot with lanes of its own plays them, not its questions: the one taken is one more lane of it.
        $taken = array_values(array_unique(array_merge((array) ($knot['taken'] ?? []), [$id])));
        self::store($courseid, (string) $mission->code, self::MISSION, 'battle/knots/' . $code . '/taken', json_encode($taken));
        return $id;
    }

    /**
     * The scenes the teachers of a course took out of the game.
     *
     * @param int $courseid
     * @param string $mission Code of the mission.
     * @return string[] Their codes.
     */
    public static function off(int $courseid, string $mission): array {
        $off = [];
        foreach (self::of($courseid, $mission) as $scene => $changes) {
            if (($changes[self::ENABLED] ?? '') === 'off') {
                $off[] = (string) $scene;
            }
        }
        return $off;
    }

    /**
     * The changes of the mission of a course.
     *
     * @param int $courseid
     * @param string $mission Code of the mission.
     * @return array scene => [path => value].
     */
    public static function of(int $courseid, string $mission): array {
        global $DB;
        $key = $courseid . '_' . $mission;
        $list = self::cache()->get($key);
        if ($list === false) {
            $list = [];
            $records = $DB->get_records(
                self::T . 'edit',
                ['courseid' => $courseid, 'mission' => $mission],
                'id',
                'id, scene, path, value'
            );
            foreach ($records as $record) {
                $list[(string) $record->scene][$record->path] = self::is_list($record->path) ?
                    (json_decode($record->value, true) ?: []) : $record->value;
            }
            self::cache()->set($key, $list);
        }
        return $list;
    }

    /**
     * The changes read in this request: every scene of a map reads them.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'local_oksigeniatesape', 'edits');
    }

    /**
     * A definition (of the mission or of a scene) with the changes of the teacher on it.
     *
     * @param array $definition
     * @param int $courseid
     * @param string $mission Code of the mission.
     * @param string $scene Code of the scene, or MISSION.
     * @return array
     */
    public static function apply(array $definition, int $courseid, string $mission, string $scene = self::MISSION): array {
        foreach (self::of($courseid, $mission)[$scene] ?? [] as $path => $value) {
            if ($path === self::SNIFF) {
                $definition['sniff'] = $value === 'on';
                continue;
            }
            if ($path === self::ENABLED) {
                continue;
            }
            if ($path === self::OWNSECRETS) {
                // Those of the teacher go after those of the mission: one more to find in the painting of its place.
                $definition['secrets'] = array_merge($definition['secrets'] ?? [], is_array($value) ? $value : []);
                continue;
            }
            self::set($definition, explode('/', $path), $value);
        }
        return $definition;
    }

    /**
     * The definition of a scene of the mission of a course, with the changes of the teacher on it.
     *
     * @param \stdClass $scene With its definition and code.
     * @param \stdClass $mission
     * @return array
     */
    public static function scene(\stdClass $scene, \stdClass $mission): array {
        $definition = json_decode((string) $scene->definition, true) ?: [];
        return self::apply($definition, (int) $mission->courseid, (string) $mission->code, (string) $scene->code);
    }

    /**
     * The texts a teacher can change in a definition: path => text as it is (one text, or one per story or place).
     *
     * @param array $definition Of a scene, or of the mission.
     * @param bool $mission Whether it is the mission (its places), not a scene.
     * @return array
     */
    public static function texts(array $definition, bool $mission = false): array {
        $texts = [];
        if ($mission) {
            foreach ($definition['chapters'] ?? [] as $chapter) {
                $base = 'chapters/' . $chapter['code'];
                self::field($texts, $base, $chapter, ['intro', 'offscreen']);
                foreach (array_values($chapter['sessions'] ?? []) as $i => $session) {
                    self::field($texts, $base . '/sessions/' . $i, $session, ['do', 'materials']);
                    self::sheet($texts, $base . '/sessions/' . $i, $session);
                }
                foreach (array_values($chapter['pacts'] ?? []) as $i => $pact) {
                    if (self::is_text($pact)) {
                        $texts[$base . '/pacts/' . $i] = $pact;
                    }
                }
            }
            if (!empty($definition['entry'])) {
                self::sheet($texts, 'entry', $definition['entry']);
            }
            // The knots of the battle: the minion, what the Embrollo claims and its laugh, the idea that undoes it and why.
            foreach ($definition['battle']['knots'] ?? [] as $knot) {
                if (!empty($knot['code'])) {
                    self::field($texts, 'battle/knots/' . $knot['code'], $knot, ['name', 'claim', 'laugh', 'weakness', 'why']);
                }
            }
            // What each word of the glossary of the scenes means.
            foreach ($definition['glossary'] ?? [] as $word) {
                if (!empty($word['id'])) {
                    self::field($texts, 'glossary/' . $word['id'], $word, ['text']);
                }
            }
            return $texts;
        }
        self::field($texts, '', $definition, ['title', 'whatif']);
        foreach ($definition['nodes'] ?? [] as $nodeid => $node) {
            $base = 'nodes/' . $nodeid;
            self::field($texts, $base, $node, ['text', 'prompt', 'cardtitle', 'reveal_after']);
            foreach (array_values($node['hints'] ?? []) as $i => $hint) {
                if (self::is_text($hint)) {
                    $texts[$base . '/hints/' . $i] = $hint;
                }
            }
            foreach ($node['stuck'] ?? [] as $key => $text) {
                if (self::is_text($text)) {
                    $texts[$base . '/stuck/' . $key] = $text;
                }
            }
            // With the information shared out, the piece each role sees.
            foreach ($node['views'] ?? [] as $role => $text) {
                if (self::is_text($text)) {
                    $texts[$base . '/views/' . $role] = $text;
                }
            }
            // What is said to whoever is described by a word.
            foreach ($node['nature'] ?? [] as $word => $text) {
                if (self::is_text($text)) {
                    $texts[$base . '/nature/' . $word] = $text;
                }
            }
            foreach (self::ELEMENTS as $list) {
                foreach ($node[$list] ?? [] as $element) {
                    if (!is_array($element) || !isset($element['id'])) {
                        continue;
                    }
                    $at = $base . '/' . $list . '/' . $element['id'];
                    self::field($texts, $at, $element, ['label', 'say', 'after', 'why']);
                    $feedback = $element['feedback'] ?? null;
                    if (self::is_text($feedback)) {
                        $texts[$at . '/feedback'] = $feedback;
                    } else if (is_array($feedback)) {
                        // Feedback for each bin where the item may go.
                        foreach ($feedback as $key => $text) {
                            if (self::is_text($text)) {
                                $texts[$at . '/feedback/' . $key] = $text;
                            }
                        }
                    }
                }
            }
        }
        return $texts;
    }

    /**
     * The texts of the sheet of a session: its title, what to prepare, its steps (what is done and read aloud), its key
     * questions with their answers, the mistakes to watch with how to turn them round, and what to do if time is left.
     *
     * @param array $texts
     * @param string $base
     * @param array $sheet
     */
    private static function sheet(array &$texts, string $base, array $sheet): void {
        self::field($texts, $base, $sheet, ['title', 'spare']);
        foreach (array_values((array) ($sheet['before'] ?? [])) as $i => $line) {
            if (self::is_text($line)) {
                $texts[$base . '/before/' . $i] = $line;
            }
        }
        foreach (['steps' => ['do', 'say'], 'questions' => ['ask', 'expect'], 'watch' => ['mistake', 'redirect']] as $list => $names) {
            foreach (array_values((array) ($sheet[$list] ?? [])) as $i => $element) {
                if (is_array($element)) {
                    self::field($texts, $base . '/' . $list . '/' . $i, $element, $names);
                }
            }
        }
    }

    /**
     * Changes a text of the mission of a course; an empty text goes back to the original.
     *
     * @param int $courseid
     * @param string $scene Code of the scene, or MISSION.
     * @param string $path
     * @param string $value
     * @return bool Whether there is a change now.
     */
    public static function save(int $courseid, string $scene, string $path, string $value): bool {
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $original = $scene === self::MISSION ? (json_decode((string) $mission->definition, true) ?: []) :
            self::scene_definition((int) $mission->id, $scene);
        if ($path === self::ENABLED && $scene !== self::MISSION) {
            // In the game, or off. Never the last scene of its chapter that is not a quest: the chapter would have no route.
            if (!in_array($value, ['', 'off'], true)) {
                throw new \invalid_parameter_exception('Not a switch of this scene: ' . $value);
            }
            if ($value === 'off') {
                global $DB;
                $record = $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => $scene], '*', MUST_EXIST);
                $where = 'chapterid = ? AND quest = 0 AND code <> ?';
                $others = $DB->get_fieldset_select(self::T . 'scene', 'code', $where, [$record->chapterid, $scene]);
                $off = self::off($courseid, (string) $mission->code);
                if (empty($record->quest) && !array_diff($others, $off)) {
                    throw new \moodle_exception('wslastscene', 'local_oksigeniatesape');
                }
            }
            return self::store($courseid, (string) $mission->code, $scene, $path, $value);
        }
        if ($path === self::SNIFF && $scene !== self::MISSION) {
            // The creature may sniff in this scene or not: on, off, or as it came (empty).
            if (!in_array($value, ['', 'on', 'off'], true) || !self::sniffable($original)) {
                throw new \invalid_parameter_exception('Not a switch of this scene: ' . $value);
            }
            return self::store($courseid, (string) $mission->code, $scene, $path, $value);
        }
        if ($scene !== self::MISSION && isset(self::easy_paths($original)[$path])) {
            // The easy reading of a text: empty, the scene says the text as it is.
            $value = trim(clean_param($value, PARAM_TEXT));
            if (\core_text::strlen($value) > self::MAX_TEXT) {
                throw new \invalid_parameter_exception('Too long');
            }
            return self::store($courseid, (string) $mission->code, $scene, $path, $value);
        }
        if (!array_key_exists($path, self::texts($original, $scene === self::MISSION))) {
            throw new \invalid_parameter_exception('Not a text that can be changed: ' . $path);
        }
        $value = trim(clean_param($value, PARAM_TEXT));
        if (\core_text::strlen($value) > self::MAX_TEXT) {
            throw new \invalid_parameter_exception('Too long');
        }
        // What a word of the glossary means, read at a glance too.
        if (strpos($path, 'glossary/') === 0 && \core_text::strlen($value) > self::MAX_PIECE) {
            throw new \moodle_exception('wspiecelong', 'local_oksigeniatesape', '', self::MAX_PIECE);
        }
        if ($value !== '' && preg_match('#^nodes/([^/]+)/views/([a-z]+)$#', $path, $m)) {
            if (\core_text::strlen($value) > self::MAX_PIECE) {
                throw new \moodle_exception('wspiecelong', 'local_oksigeniatesape', '', self::MAX_PIECE);
            }
            // A piece of the information shared out has to be its own: the same as the piece of another role, nobody
            // would need to tell it.
            $node = self::apply($original, $courseid, (string) $mission->code, $scene)['nodes'][$m[1]] ?? [];
            foreach ($node['views'] ?? [] as $role => $text) {
                $other = self::resolve($text, (string) $mission->skin, (string) $mission->variant, $original['locals'] ?? []);
                if ($role !== $m[2] && \core_text::strtolower(trim($other)) === \core_text::strtolower($value)) {
                    throw new \moodle_exception('wssamepiece', 'local_oksigeniatesape');
                }
            }
        }
        return self::store($courseid, (string) $mission->code, $scene, $path, $value);
    }

    /**
     * The questions of the battle of the mission of a course, all of them, as the teacher leaves them.
     *
     * @param int $courseid
     * @param array $questions Each: id (empty for a new one), chapter (code), objective, text, options (the right one
     *     first), why.
     * @return array The questions kept.
     */
    public static function save_questions(int $courseid, array $questions): array {
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $chapters = array_map(fn($c) => (string) $c['code'], $mission->data['chapters'] ?? []);
        $objectives = array_map('strval', array_keys($mission->data['objectives'] ?? []));
        $clean = [];
        $ids = [];
        foreach (array_slice(array_values($questions), 0, self::MAX_QUESTIONS) as $question) {
            $text = trim(clean_param((string) ($question['text'] ?? ''), PARAM_TEXT));
            $options = array_values(array_filter(array_map(
                fn($o) => trim(clean_param((string) $o, PARAM_TEXT)),
                array_slice((array) ($question['options'] ?? []), 0, 6)
            ), fn($o) => $o !== ''));
            if ($text === '' || count($options) < 2) {
                throw new \invalid_parameter_exception('A question needs its text and two options');
            }
            $chapter = (string) ($question['chapter'] ?? '');
            $objective = (string) ($question['objective'] ?? '');
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($question['id'] ?? ''));
            while ($id === '' || isset($ids[$id])) {
                $id = 'D' . (count($ids) + 1) . '-' . random_string(4);
            }
            $ids[$id] = true;
            $clean[] = [
                'id' => $id,
                'chapter' => in_array($chapter, $chapters, true) ? $chapter : (string) reset($chapters),
                'objective' => in_array($objective, $objectives, true) ? $objective : '',
                'text' => \core_text::substr($text, 0, 500),
                'options' => array_map(fn($o) => \core_text::substr($o, 0, 200), $options),
                'why' => \core_text::substr(trim(clean_param((string) ($question['why'] ?? ''), PARAM_TEXT)), 0, 500),
            ];
        }
        self::store($courseid, (string) $mission->code, self::MISSION, 'questions', $clean ? json_encode($clean) : '');
        return $clean;
    }

    /**
     * The secrets the teacher hides in the paintings of the places of the mission of a course, all of them, as the teacher
     * leaves them: each in a place, inside what a phone shows of the painting, with what it says. Like those of the
     * mission, they go to the album and give neither knowledge nor gems.
     *
     * @param int $courseid
     * @param array $secrets Each: id (empty for a new one), place (code), x and y (percent of the painting), text.
     * @return array The secrets kept.
     */
    public static function save_secrets(int $courseid, array $secrets): array {
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $places = array_map(fn($c) => (string) $c['code'], $mission->data['chapters'] ?? []);
        $clean = [];
        $ids = [];
        foreach (array_slice(array_values($secrets), 0, self::MAX_SECRETS) as $secret) {
            $text = trim(clean_param((string) ($secret['text'] ?? ''), PARAM_TEXT));
            $place = (string) ($secret['place'] ?? '');
            if ($text === '' || !in_array($place, $places, true)) {
                throw new \invalid_parameter_exception('A secret needs its place and what it says');
            }
            $id = (string) ($secret['id'] ?? '');
            while (!preg_match('/^own-[a-z0-9]{6}$/', $id) || isset($ids[$id])) {
                $id = 'own-' . strtolower(random_string(6));
            }
            $ids[$id] = true;
            $clean[] = [
                'id' => $id,
                'place' => $place,
                'kind' => 'thing',
                // Where a phone sees it: the middle half of the painting, and not on its very edges.
                'x' => max(25, min(75, (int) round((float) ($secret['x'] ?? 50)))),
                'y' => max(12, min(88, (int) round((float) ($secret['y'] ?? 50)))),
                'text' => \core_text::substr($text, 0, self::MAX_SECRET),
            ];
        }
        self::store($courseid, (string) $mission->code, self::MISSION, self::OWNSECRETS, $clean ? json_encode($clean) : '');
        return $clean;
    }

    /**
     * The changes of the mission of a course, to take to another course or another site: only the texts of the teacher,
     * never the mission, which the other course needs to have.
     *
     * @param int $courseid
     * @return array format, mission, version, edits (scene, path, value; the questions as a list).
     */
    public static function export(int $courseid): array {
        $mission = missions::get($courseid);
        if (!$mission) {
            throw new \moodle_exception('nomission', 'local_oksigeniatesape');
        }
        $list = [];
        foreach (self::of($courseid, (string) $mission->code) as $scene => $changes) {
            foreach ($changes as $path => $value) {
                $list[] = ['scene' => (string) $scene, 'path' => (string) $path, 'value' => $value];
            }
        }
        return ['format' => 'tesape-edits', 'formatversion' => 1, 'mission' => (string) $mission->code,
            'version' => (int) $mission->version, 'edits' => $list];
    }

    /**
     * Takes changes (exported from another course) to the mission of a course: those that fit its mission; a text
     * changed in both is replaced, the others stay.
     *
     * @param int $courseid
     * @param array $data As export() gives it.
     * @return array applied, skipped.
     */
    public static function import(int $courseid, array $data): array {
        $mission = missions::get($courseid);
        if (!$mission || ($data['format'] ?? '') !== 'tesape-edits' || ($data['mission'] ?? '') !== $mission->code) {
            throw new \moodle_exception('editsnotfit', 'local_oksigeniatesape');
        }
        $applied = 0;
        $skipped = 0;
        foreach ((array) ($data['edits'] ?? []) as $edit) {
            $path = (string) ($edit['path'] ?? '');
            $value = $edit['value'] ?? '';
            try {
                if ($path === 'questions' && is_array($value)) {
                    self::save_questions($courseid, $value);
                } else if ($path === self::OWNSECRETS && is_array($value)) {
                    self::save_secrets($courseid, $value);
                } else if (is_string($value) && $value !== '') {
                    self::save($courseid, (string) ($edit['scene'] ?? self::MISSION), $path, $value);
                } else {
                    $skipped++;
                    continue;
                }
                $applied++;
            } catch (\Throwable $e) {
                // A text the mission of this course does not have (another version, a scene that is not there).
                $skipped++;
            }
        }
        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Back to the original: one scene, the places and questions (MISSION), or the whole mission (null).
     *
     * @param int $courseid
     * @param string|null $scene
     */
    public static function restore(int $courseid, ?string $scene = null): void {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return;
        }
        $conditions = ['courseid' => $courseid, 'mission' => $mission->code];
        if ($scene !== null) {
            $conditions['scene'] = $scene;
        }
        $DB->delete_records(self::T . 'edit', $conditions);
        self::cache()->purge();
        missions::forget();
    }

    /**
     * Keeps a change as it comes (from the teacher, already checked, or from a backup).
     *
     * @param int $courseid
     * @param string $mission Code of the mission.
     * @param string $scene
     * @param string $path
     * @param string $value Empty: no change.
     * @return bool Whether there is a change now.
     */
    public static function store(int $courseid, string $mission, string $scene, string $path, string $value): bool {
        global $DB;
        $key = ['courseid' => $courseid, 'mission' => $mission, 'scene' => $scene, 'path' => $path];
        $record = $DB->get_record(self::T . 'edit', $key);
        self::cache()->purge();
        missions::forget();
        if ($value === '') {
            if ($record) {
                $DB->delete_records(self::T . 'edit', ['id' => $record->id]);
            }
            return false;
        }
        if ($record) {
            $DB->update_record(self::T . 'edit', (object) ['id' => $record->id, 'value' => $value, 'timemodified' => time()]);
        } else {
            $DB->insert_record(self::T . 'edit', (object) ($key + ['value' => $value, 'timemodified' => time()]));
        }
        return true;
    }

    /**
     * Whether the creature could sniff some question of a scene (a choice of one answer among three or more).
     *
     * @param array $definition
     * @return bool
     */
    private static function sniffable(array $definition): bool {
        foreach ($definition['nodes'] ?? [] as $node) {
            if (scene_runner::sniffs($node) !== null) {
                return true;
            }
        }
        return false;
    }

    /**
     * What the teacher sees to change the mission of a course: its places and scenes, chapter by chapter, with each
     * text as it is now and as it came, in the story and place of the course; and the questions of the battle.
     *
     * @param int $courseid
     * @return array
     */
    public static function view(int $courseid): array {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return [];
        }
        $code = (string) $mission->code;
        $skin = (string) $mission->skin;
        $variant = (string) $mission->variant;
        $changes = self::of($courseid, $code);
        $original = json_decode((string) $mission->definition, true) ?: [];
        $places = self::fields(self::texts($original, true), $changes[self::MISSION] ?? [], $skin, $variant, []);
        $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code');
        // The classroom challenges of the course, and the one of each place.
        $courseid = (int) $mission->courseid;
        $classroom = $DB->get_records(self::T . 'challenge', ['courseid' => $courseid, 'cmid' => 0], 'name', 'id, name, chapterid');
        $placechallenges = [];
        foreach ($classroom as $challenge) {
            if ($challenge->chapterid) {
                $placechallenges[(int) $challenge->chapterid] = (int) $challenge->id;
            }
        }
        $chapters = [];
        foreach ($original['chapters'] ?? [] as $i => $chapter) {
            $where = ['missionid' => $mission->id, 'code' => $chapter['code']];
            $record = $DB->get_record(self::T . 'chapter', $where, 'id, litat, skippedat');
            $chapterid = (int) ($record->id ?? 0);
            $list = [];
            foreach ($scenes as $scene) {
                if ((int) $scene->chapterid !== $chapterid) {
                    continue;
                }
                $def = json_decode((string) $scene->definition, true) ?: [];
                $mine = $changes[$scene->code] ?? [];
                $fields = self::fields(self::texts($def), $mine, $skin, $variant, $def['locals'] ?? []);
                foreach ($fields as &$field) {
                    // Narration, question, ending…: the label of each text depends on its moment.
                    $field['type'] = (string) ($def['nodes'][$field['node']]['type'] ?? '');
                }
                unset($field);
                $came = ($def['sniff'] ?? true) !== false;
                $list[] = [
                    'id' => (int) $scene->id,
                    'code' => (string) $scene->code,
                    'title' => (string) ($mine['title'] ?? self::resolve($def['title'] ?? $scene->code, $skin, $variant)),
                    'step' => (int) $scene->step,
                    'quest' => !empty($scene->quest),
                    'changed' => count($mine),
                    'fields' => $fields,
                    // Whether the creature can sniff a question of the scene, and whether it does, as it came and now.
                    'sniffable' => self::sniffable($def),
                    'sniffcame' => $came,
                    'sniff' => isset($mine[self::SNIFF]) ? $mine[self::SNIFF] === 'on' : $came,
                    'enabled' => ($mine[self::ENABLED] ?? '') !== 'off',
                    // The easy reading of its texts, for the students who have it marked.
                    'easy' => self::easy_fields($def, $mine, $skin, $variant),
                ];
            }
            usort($list, fn($a, $b) => [$a['quest'], $a['step'], $a['code']] <=> [$b['quest'], $b['step'], $b['code']]);
            $prefix = 'chapters/' . $chapter['code'] . '/';
            // The knots of the battle of this place go with its texts.
            $here = array_filter($original['battle']['knots'] ?? [], fn($k) => (int) ($k['chapter'] ?? 0) === $i + 1);
            $knots = array_map(fn($k) => 'battle/knots/' . ($k['code'] ?? '') . '/', $here);
            // The entry session, before the first place, goes with the first place.
            $mine = fn($f) => strpos($f['path'], $prefix) === 0 || array_filter($knots, fn($k) => strpos($f['path'], $k) === 0)
                || ($i === 0 && strpos($f['path'], 'entry/') === 0);
            $chapters[] = [
                'code' => (string) $chapter['code'],
                'art' => (string) ($chapter['art'] ?? $chapter['code']),
                // Where the secrets of the mission are in its painting, for the teacher hiding one of their own.
                'secrets' => array_values(array_map(fn($x) => ['x' => (int) ($x['x'] ?? 50), 'y' => (int) ($x['y'] ?? 50)], array_filter(
                    $original['secrets'] ?? [],
                    fn($x) => ($x['place'] ?? '') === $chapter['code']
                ))),
                'number' => $i + 1,
                'name' => self::resolve($chapter['name'] ?? $chapter['code'], $skin, $variant),
                'fields' => array_values(array_filter($places, $mine)),
                'scenes' => $list,
                // Whether the class passed it by, or can still: not lit yet, and not the place of the final battle.
                'skipped' => !empty($record->skippedat),
                'id' => $chapterid,
                'challengeid' => (int) ($placechallenges[$chapterid] ?? 0),
                'skippable' => $record && !$record->litat && (string) $chapter['code'] !== self::final_place($mission),
            ];
        }
        $questions = [];
        foreach ($mission->data['questions'] ?? [] as $question) {
            $questions[] = [
                'id' => (string) ($question['id'] ?? ''),
                'chapter' => (string) ($question['chapter'] ?? ''),
                'objective' => (string) ($question['objective'] ?? ''),
                'text' => self::resolve($question['text'] ?? '', $skin, $variant),
                'options' => array_map(fn($o) => self::resolve($o, $skin, $variant), array_values($question['options'] ?? [])),
                'why' => self::resolve($question['why'] ?? '', $skin, $variant),
            ];
        }
        $objectives = [];
        foreach ($original['objectives'] ?? [] as $objective => $name) {
            $objectives[] = ['code' => (string) $objective, 'name' => self::resolve($name, $skin, $variant)];
        }
        return [
            'chapters' => $chapters,
            'questions' => $questions,
            'questionschanged' => isset($changes[self::MISSION]['questions']),
            'ownsecrets' => array_values((array) ($changes[self::MISSION][self::OWNSECRETS] ?? [])),
            // The glossary of the scenes: each word as it is written first, with what it means.
            'glossary' => array_values(array_filter(array_map(function ($word) use ($places) {
                $field = array_values(array_filter($places, fn($f) => $f['path'] === 'glossary/' . ($word['id'] ?? '') . '/text'));
                return $field ? ['word' => (string) (((array) ($word['forms'] ?? []))[0] ?? $word['id']), 'field' => $field[0]] : null;
            }, $original['glossary'] ?? []))),
            'secretsmost' => self::MAX_SECRETS,
            'secretmax' => self::MAX_SECRET,
            'objectives' => $objectives,
            'changed' => array_sum(array_map('count', $changes)),
            'challenges' => array_values(array_map(fn($c) => ['id' => (int) $c->id, 'name' => format_string($c->name),
                'chapterid' => (int) $c->chapterid], $classroom)),
        ];
    }

    /**
     * The texts of a definition for the teacher: path, kind (the last name of the path), now and as it came, and how long
     * it may be.
     *
     * @param array $texts path => text, as it came.
     * @param array $changes path => text of the teacher.
     * @param string $skin
     * @param string $variant
     * @param array $locals The words of each place of a scene.
     * @return array
     */
    private static function fields(array $texts, array $changes, string $skin, string $variant, array $locals): array {
        $fields = [];
        foreach ($texts as $path => $text) {
            $original = self::resolve($text, $skin, $variant, $locals);
            $parts = explode('/', $path);
            $grouped = in_array($parts[count($parts) - 2] ?? '', ['stuck', 'feedback', 'views', 'nature'], true);
            $kind = preg_match('/^\d+$/', end($parts)) || $grouped ? $parts[count($parts) - 2] : end($parts);
            $fields[] = [
                'path' => $path,
                'kind' => $kind,
                'node' => ($parts[0] ?? '') === 'nodes' ? (string) ($parts[1] ?? '') : '',
                'element' => in_array($parts[2] ?? '', self::ELEMENTS, true) ? (string) ($parts[3] ?? '') : '',
                'text' => array_key_exists($path, $changes) ? (string) $changes[$path] : $original,
                'original' => $original,
                'changed' => array_key_exists($path, $changes),
                'max' => $kind === 'views' || $parts[0] === 'glossary' ? self::MAX_PIECE : self::MAX_TEXT,
            ];
        }
        return $fields;
    }

    /**
     * Where the easy reading of each text of a scene goes: path => the key of the text it says again.
     *
     * @param array $definition Of a scene.
     * @return array
     */
    private static function easy_paths(array $definition): array {
        $paths = [];
        foreach ($definition['nodes'] ?? [] as $nodeid => $node) {
            foreach (self::EASY as $easy => $of) {
                if (self::is_text($node[$of] ?? null)) {
                    $paths['nodes/' . $nodeid . '/' . $easy] = $of;
                }
            }
        }
        return $paths;
    }

    /**
     * The easy reading of the texts of a scene, for the workshop: each with the text it says again (as the course has
     * it now), what it says, and whether the teacher wrote it.
     *
     * @param array $definition Of a scene, as the mission has it.
     * @param array $mine The changes of the course in it.
     * @param string $skin
     * @param string $variant
     * @return array path, node, kind (easy or easyprompt), source, text, changed.
     */
    private static function easy_fields(array $definition, array $mine, string $skin, string $variant): array {
        $locals = $definition['locals'] ?? [];
        $fields = [];
        foreach (self::easy_paths($definition) as $path => $of) {
            $parts = explode('/', $path);
            $node = $definition['nodes'][$parts[1]];
            $source = $mine['nodes/' . $parts[1] . '/' . $of] ?? self::resolve($node[$of], $skin, $variant, $locals);
            $fields[] = [
                'path' => $path,
                'node' => $parts[1],
                'kind' => $parts[2],
                'source' => (string) $source,
                'text' => array_key_exists($path, $mine) ? (string) $mine[$path] :
                    self::resolve($node[$parts[2]] ?? '', $skin, $variant, $locals),
                'changed' => array_key_exists($path, $mine),
                'max' => self::MAX_TEXT,
            ];
        }
        return $fields;
    }

    /**
     * The easy reading of the texts of a scene, proposed by the AI of the site: shorter sentences, one idea in each,
     * common words, the same facts. Kept nowhere: the teacher reads it, changes what they want and saves it.
     *
     * @param int $courseid
     * @param string $code Of the scene.
     * @param int $userid
     * @return array path => text.
     */
    public static function easy_proposal(int $courseid, string $code, int $userid): array {
        global $DB;
        $status = nextclass::status($courseid, $userid);
        if (!$status['ai'] || !$status['canuse']) {
            throw new \moodle_exception('authoring_noai', 'local_oksigeniatesape');
        }
        if (!$status['policy']) {
            throw new \moodle_exception('authoring_aipolicy', 'local_oksigeniatesape');
        }
        $mission = missions::get($courseid);
        $scene = $mission ? $DB->get_record(self::T . 'scene', ['missionid' => $mission->id, 'code' => $code]) : null;
        if (!$scene) {
            throw new \invalid_parameter_exception('Not a scene of the mission');
        }
        $definition = json_decode((string) $scene->definition, true) ?: [];
        $mine = self::of($courseid, (string) $mission->code)[$code] ?? [];
        $sources = [];
        foreach (self::easy_fields($definition, $mine, (string) $mission->skin, (string) $mission->variant) as $field) {
            $sources[$field['path']] = $field['source'];
        }
        if (!$sources) {
            return [];
        }
        $prompt = "You rewrite the texts of a scene of an educational role-playing game in easy reading, for students aged " .
            ($mission->data['ages'] ?? '9-11') . " who read with difficulty (dyslexia, another language at home), in the " .
            "language of this code: " . current_language() . ". Rules: short sentences, one idea in each; common words; the " .
            "active voice; no metaphors, irony or double meanings; the same facts, names, people who speak and decisions, " .
            "without adding anything or giving away any answer; keep {{creature}} and {{name}} exactly as they are; keep " .
            "the quotes of who speaks with «»; a question stays a question; not longer than the original; the same way " .
            "of addressing the reader (in Spanish, the student is «tú», never the plural «vosotros» nor its forms such as " .
            "«os» or «fiéis», and never the verb «coger»). The teacher " .
            "will read every text.\n\nThe texts, as JSON (key => text):\n" . json_encode($sources, JSON_UNESCAPED_UNICODE) .
            "\n\nAnswer with only a JSON object with the same keys and the texts rewritten.";
        $manager = \core\di::get(\core_ai\manager::class);
        $contextid = games::context($courseid)->id;
        $again = 'Your previous answer was not valid JSON with those keys. Answer again with only the JSON.';
        foreach ([$prompt, $prompt . "\n\n" . $again] as $text) {
            try {
                $response = $manager->process_action(new \core_ai\aiactions\generate_text($contextid, $userid, $text));
            } catch (\Throwable $e) {
                throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape', '', null, $e->getMessage());
            }
            if (!$response->get_success()) {
                $why = $response->get_errormessage();
                throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape', '', null, $why);
            }
            $answer = (string) ($response->get_response_data()['generatedcontent'] ?? '');
            $start = strpos($answer, '{');
            $end = strrpos($answer, '}');
            $data = $start !== false && $end !== false ? json_decode(substr($answer, $start, $end - $start + 1), true) : null;
            $proposal = is_array($data) ? self::clean_easy($data, $sources) : [];
            if ($proposal) {
                return $proposal;
            }
        }
        throw new \moodle_exception('authoring_aifailed', 'local_oksigeniatesape');
    }

    /**
     * A proposal of easy reading cleaned as anything a teacher writes: only the texts asked for, and only those that say
     * something other than the text they come from.
     *
     * @param array $data path => text, as the AI answered.
     * @param array $sources path => the text it says again.
     * @return array path => text.
     */
    public static function clean_easy(array $data, array $sources): array {
        // The plural that is never said to the students, and the verb that is never used with them: a text that brings
        // them where the original did not have them is left out.
        $never = '/(?<!\p{L})(os|vosotros|vosotras|coger|coge|cogemos)(?!\p{L})|\p{L}+éis(?!\p{L})/iu';
        $out = [];
        foreach ($sources as $path => $source) {
            $text = \core_text::substr(trim(clean_param((string) ($data[$path] ?? ''), PARAM_TEXT)), 0, self::MAX_TEXT);
            if ($text === '' || $text === trim($source) || (preg_match($never, $text) && !preg_match($never, $source))) {
                continue;
            }
            $out[$path] = $text;
        }
        return $out;
    }

    /**
     * A text in the story and place of the course, with the words of the place put in.
     *
     * @param mixed $text
     * @param string $skin
     * @param string $variant
     * @param array $locals
     * @return string
     */
    public static function resolve($text, string $skin, string $variant, array $locals = []): string {
        if (is_array($text)) {
            $text = $text[$skin . '.' . $variant] ?? $text[$skin] ?? $text[$variant] ?? reset($text);
        }
        return preg_replace_callback('/\{\{local:([a-z0-9_-]+)\}\}/', function ($m) use ($locals, $variant) {
            $words = $locals[$m[1]] ?? '';
            if (!is_array($words)) {
                return (string) $words;
            }
            return (string) ($words[$variant] ?? (reset($words) ?: ''));
        }, (string) $text);
    }

    /**
     * The definition of a scene of a mission, as it came.
     *
     * @param int $missionid
     * @param string $code
     * @return array
     */
    private static function scene_definition(int $missionid, string $code): array {
        global $DB;
        $definition = $DB->get_field(
            self::T . 'scene',
            'definition',
            ['missionid' => $missionid, 'code' => $code],
            MUST_EXIST
        );
        return json_decode((string) $definition, true) ?: [];
    }

    /**
     * Adds the texts of some fields of an element to the list.
     *
     * @param array $texts
     * @param string $base Path of the element.
     * @param array $element
     * @param string[] $names
     */
    private static function field(array &$texts, string $base, array $element, array $names): void {
        foreach ($names as $name) {
            if (in_array($name, self::TEXTS, true) && self::is_text($element[$name] ?? null)) {
                $texts[ltrim($base . '/' . $name, '/')] = $element[$name];
            }
        }
    }

    /**
     * Whether a value is a text: one string, or one per story or place.
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_text($value): bool {
        if (is_string($value)) {
            return $value !== '';
        }
        if (!is_array($value) || !$value) {
            return false;
        }
        // A text per story or place has keys like valley, azara, es, py or valley.py; feedback for each bin has the bins
        // as keys, and is several texts, not one.
        foreach ($value as $key => $text) {
            if (!is_string($text) || !self::is_skin_key((string) $key)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Whether a key of a text is a story, a place or both.
     *
     * @param string $key
     * @return bool
     */
    private static function is_skin_key(string $key): bool {
        static $keys = null;
        if ($keys === null) {
            $keys = [];
            foreach (missions::packages() as $package) {
                $skins = array_keys($package['skins'] ?? []);
                $variants = array_keys($package['variants'] ?? []);
                $keys = array_merge($keys, $skins, $variants);
                foreach ($skins as $skin) {
                    foreach ($variants as $variant) {
                        $keys[] = $skin . '.' . $variant;
                    }
                }
            }
            $keys = array_flip($keys);
        }
        return isset($keys[$key]);
    }

    /**
     * Puts a value at a path of a definition: elements of lists with an id are found by it, others by their place.
     *
     * @param array $data
     * @param string[] $path
     * @param mixed $value
     * @return bool Whether the path was there.
     */
    private static function set(array &$data, array $path, $value): bool {
        $key = array_shift($path);
        if ($key === null) {
            return false;
        }
        $at = null;
        if (array_is_list($data)) {
            // Options, items, bins and steps by their id; places by their code.
            foreach ($data as $i => $element) {
                if (is_array($element) && (string) ($element['id'] ?? $element['code'] ?? '') === $key) {
                    $at = $i;
                    break;
                }
            }
        }
        if ($at === null && (array_key_exists($key, $data) || (!$path && ($key === 'taken' || isset(self::EASY[$key]))))) {
            // The questions taken for a knot and the easy reading of a text are what a change adds that the mission did
            // not have.
            $at = $key;
        }
        if ($at === null) {
            // The mission changed and this text is not there any more: the change waits, it is not lost.
            return false;
        }
        if (!$path) {
            $data[$at] = $value;
            return true;
        }
        if (!is_array($data[$at])) {
            return false;
        }
        return self::set($data[$at], $path, $value);
    }
}
