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
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class edits {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Longest text of one field. */
    public const MAX_TEXT = 4000;

    /** @var int Most questions of a mission. */
    public const MAX_QUESTIONS = 80;

    /** @var string In place of a scene, the changes of the mission itself (its places and questions). */
    public const MISSION = '#';

    /** @var string The switch of the sniffing of the creature in a scene: on or off, not a text. */
    public const SNIFF = 'sniff';

    /** @var string[] Fields with one text, in a scene, a node or an element of a list. */
    private const TEXTS = ['title', 'whatif', 'text', 'prompt', 'label', 'say', 'feedback', 'intro', 'offscreen', 'do', 'materials',
        'name', 'claim', 'laugh', 'weakness', 'why'];

    /** @var string[] Lists of elements with an id, in a node. */
    private const ELEMENTS = ['options', 'items', 'bins', 'steps'];

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
                $list[(string) $record->scene][$record->path] = $record->path === 'questions' ?
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
                }
                foreach (array_values($chapter['pacts'] ?? []) as $i => $pact) {
                    if (self::is_text($pact)) {
                        $texts[$base . '/pacts/' . $i] = $pact;
                    }
                }
            }
            // The knots of the battle: the minion, what the Embrollo claims and its laugh, the idea that undoes it and why.
            foreach ($definition['battle']['knots'] ?? [] as $knot) {
                if (!empty($knot['code'])) {
                    self::field($texts, 'battle/knots/' . $knot['code'], $knot, ['name', 'claim', 'laugh', 'weakness', 'why']);
                }
            }
            return $texts;
        }
        self::field($texts, '', $definition, ['title', 'whatif']);
        foreach ($definition['nodes'] ?? [] as $nodeid => $node) {
            $base = 'nodes/' . $nodeid;
            self::field($texts, $base, $node, ['text', 'prompt']);
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
            foreach (self::ELEMENTS as $list) {
                foreach ($node[$list] ?? [] as $element) {
                    if (!is_array($element) || !isset($element['id'])) {
                        continue;
                    }
                    $at = $base . '/' . $list . '/' . $element['id'];
                    self::field($texts, $at, $element, ['label', 'say']);
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
        if ($path === self::SNIFF && $scene !== self::MISSION) {
            // The creature may sniff in this scene or not: on, off, or as it came (empty).
            if (!in_array($value, ['', 'on', 'off'], true) || !self::sniffable($original)) {
                throw new \invalid_parameter_exception('Not a switch of this scene: ' . $value);
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
        $chapters = [];
        foreach ($original['chapters'] ?? [] as $i => $chapter) {
            $chapterid = (int) $DB->get_field(self::T . 'chapter', 'id', ['missionid' => $mission->id,
                'code' => $chapter['code']]);
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
                ];
            }
            usort($list, fn($a, $b) => [$a['quest'], $a['step'], $a['code']] <=> [$b['quest'], $b['step'], $b['code']]);
            $prefix = 'chapters/' . $chapter['code'] . '/';
            // The knots of the battle of this place go with its texts.
            $here = array_filter($original['battle']['knots'] ?? [], fn($k) => (int) ($k['chapter'] ?? 0) === $i + 1);
            $knots = array_map(fn($k) => 'battle/knots/' . ($k['code'] ?? '') . '/', $here);
            $mine = fn($f) => strpos($f['path'], $prefix) === 0 || array_filter($knots, fn($k) => strpos($f['path'], $k) === 0);
            $chapters[] = [
                'code' => (string) $chapter['code'],
                'number' => $i + 1,
                'name' => self::resolve($chapter['name'] ?? $chapter['code'], $skin, $variant),
                'fields' => array_values(array_filter($places, $mine)),
                'scenes' => $list,
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
            'objectives' => $objectives,
            'changed' => array_sum(array_map('count', $changes)),
        ];
    }

    /**
     * The texts of a definition for the teacher: path, kind (the last name of the path), now and as it came.
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
            $kind = preg_match('/^\d+$/', end($parts)) || in_array($parts[count($parts) - 2] ?? '', ['stuck', 'feedback'], true)
                ? $parts[count($parts) - 2] : end($parts);
            $fields[] = [
                'path' => $path,
                'kind' => $kind,
                'node' => ($parts[0] ?? '') === 'nodes' ? (string) ($parts[1] ?? '') : '',
                'element' => in_array($parts[2] ?? '', self::ELEMENTS, true) ? (string) ($parts[3] ?? '') : '',
                'text' => array_key_exists($path, $changes) ? (string) $changes[$path] : $original,
                'original' => $original,
                'changed' => array_key_exists($path, $changes),
            ];
        }
        return $fields;
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
        if ($at === null && array_key_exists($key, $data)) {
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
