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
 * The missions a teacher makes from scratch in the workshop: the draft, what is still missing in it, and the mission it
 * becomes, in the format of the packages, so that it is played as any other.
 *
 * The scenes of the teacher are guided: a narration, from one to three moments (choose one, choose several, classify
 * or order) and three endings. The game makes the nodes and the rules under them: a good ending with most of the right
 * answers, one halfway with half of them, and otherwise another try.
 *
 * The draft, as the workshop sends it:
 * - title, subtitle, ages, prologue, ending: texts of the mission;
 * - objectives: code, name, description and levels (four texts, may be empty);
 * - places: code, name, intro, offscreen (the classroom challenge), pacts (texts) and sessions (do, materials);
 * - scenes: code, place, title, speaker, text (the narration), moments, success, partial, retry (texts of the endings);
 *   a moment: type (one, many, classify, order), prompt, objective, hints, options (label, correct, feedback), bins
 *   (label), items (label, bin: number of its bin from 1, ok, feedback), steps (texts in their order), stuck;
 * - questions: place, objective, text, options (the right one first), why.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class authoring {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string Start of the code of the missions of teachers: own and the number of the draft. */
    public const PREFIX = 'own';

    /** @var string The world of the missions of teachers, for now: its art and its speakers. */
    public const WORLD = 'valley';

    /** @var int Fewest and most places: up to six, the map of the valley, until the map in layers. */
    public const MIN_PLACES = 3;

    /** @var int Most places. */
    public const MAX_PLACES = 6;

    /** @var int Most objectives. */
    public const MAX_OBJECTIVES = 8;

    /** @var int Most moments of a scene. */
    public const MAX_MOMENTS = 3;

    /** @var int Fewest questions: a battle has at least four. */
    public const MIN_QUESTIONS = 4;

    /** @var string[] Kinds of moment. */
    public const MOMENTS = ['one', 'many', 'classify', 'order'];

    /** @var int[][] Where the places go on the map of the valley, from the first to the sixth. */
    private const SPOTS = [[160, 560], [300, 470], [120, 360], [280, 260], [140, 170], [300, 80]];

    /**
     * Whether a mission is one of a teacher.
     *
     * @param string $code
     * @return bool
     */
    public static function is_own(string $code): bool {
        return (bool) preg_match('/^' . self::PREFIX . '[0-9]+$/', $code);
    }

    /**
     * A new draft in a course.
     *
     * @param int $courseid
     * @param string $title
     * @return \stdClass The record, with its code.
     */
    public static function create(int $courseid, string $title): \stdClass {
        global $DB;
        $now = time();
        $draft = ['title' => self::clean($title, 120), 'subtitle' => '', 'ages' => '', 'prologue' => '', 'ending' => '',
            'objectives' => [], 'places' => [], 'scenes' => [], 'questions' => []];
        $id = $DB->insert_record(self::T . 'own', (object) ['courseid' => $courseid, 'code' => '', 'version' => 0,
            'draft' => json_encode($draft), 'timecreated' => $now, 'timemodified' => $now]);
        $DB->set_field(self::T . 'own', 'code', self::PREFIX . $id, ['id' => $id]);
        return $DB->get_record(self::T . 'own', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The drafts of a course.
     *
     * @param int $courseid
     * @return \stdClass[] With their draft decoded.
     */
    public static function drafts(int $courseid): array {
        global $DB;
        $list = array_values($DB->get_records(self::T . 'own', ['courseid' => $courseid], 'id'));
        foreach ($list as $record) {
            $record->data = json_decode((string) $record->draft, true) ?: [];
        }
        return $list;
    }

    /**
     * Keeps a draft as the teacher leaves it, cleaned and with codes for what has none.
     *
     * @param int $courseid
     * @param string $code
     * @param array $draft
     * @return array The draft kept.
     */
    public static function save(int $courseid, string $code, array $draft): array {
        global $DB;
        $record = $DB->get_record(self::T . 'own', ['courseid' => $courseid, 'code' => $code], '*', MUST_EXIST);
        $clean = self::tidy($draft);
        $DB->update_record(self::T . 'own', (object) ['id' => $record->id, 'draft' => json_encode($clean),
            'timemodified' => time()]);
        return $clean;
    }

    /**
     * What is still missing for the draft to be played, as the teacher would say it.
     *
     * @param array $draft
     * @return array Each: key (a string of the plugin) and a (what it is about).
     */
    public static function check(array $draft): array {
        $draft = self::tidy($draft);
        $problems = [];
        $add = function (string $key, $a = null) use (&$problems) {
            $problems[] = ['key' => 'authoring_check_' . $key, 'a' => $a];
        };
        if (trim($draft['title'] ?? '') === '') {
            $add('title');
        }
        if (!$draft['objectives']) {
            $add('objectives');
        }
        $places = count($draft['places']);
        if ($places < self::MIN_PLACES || $places > self::MAX_PLACES) {
            $add('places', ['min' => self::MIN_PLACES, 'max' => self::MAX_PLACES]);
        }
        foreach ($draft['places'] as $i => $place) {
            $name = $place['name'] !== '' ? $place['name'] : (string) ($i + 1);
            if ($place['name'] === '') {
                $add('placename', $i + 1);
            }
            if (!array_filter($draft['scenes'], fn($s) => $s['place'] === $place['code'])) {
                $add('placescenes', $name);
            }
        }
        foreach ($draft['scenes'] as $scene) {
            $title = $scene['title'] !== '' ? $scene['title'] : '?';
            if ($scene['title'] === '' || $scene['text'] === '') {
                $add('scenetext', $title);
            }
            if (!$scene['moments']) {
                $add('scenemoments', $title);
            }
            foreach ($scene['moments'] as $moment) {
                $problem = self::moment_problem($moment);
                if ($problem) {
                    $add($problem, $title);
                }
            }
        }
        if (count($draft['questions']) < self::MIN_QUESTIONS) {
            $add('questions', self::MIN_QUESTIONS);
        }
        return $problems;
    }

    /**
     * Makes the mission of a draft, if nothing is missing, and installs it in its course (or brings it up to date,
     * keeping what the students did).
     *
     * @param int $courseid
     * @param string $code
     * @return \stdClass The mission of the course.
     */
    public static function publish(int $courseid, string $code): \stdClass {
        global $DB;
        $record = $DB->get_record(self::T . 'own', ['courseid' => $courseid, 'code' => $code], '*', MUST_EXIST);
        $draft = self::tidy(json_decode((string) $record->draft, true) ?: []);
        if (self::check($draft)) {
            throw new \moodle_exception('authoring_notready', 'local_oksigeniatesape');
        }
        $version = (int) $record->version + 1;
        [$mission, $scenes] = self::build($draft, $code, $version);
        $DB->update_record(self::T . 'own', (object) ['id' => $record->id, 'version' => $version,
            'mission' => json_encode($mission), 'scenes' => json_encode($scenes), 'timemodified' => time()]);
        return missions::install($courseid, $code, self::WORLD, 'gen');
    }

    /**
     * The mission of a teacher, as it was last made, for its course.
     *
     * @param int $courseid
     * @param string $code
     * @return array|null The mission (with the list of its scenes), or null.
     */
    public static function package(int $courseid, string $code): ?array {
        global $DB;
        $json = $DB->get_field(self::T . 'own', 'mission', ['courseid' => $courseid, 'code' => $code]);
        return $json ? (json_decode((string) $json, true) ?: null) : null;
    }

    /**
     * The scenes of the mission of a teacher, as they were last made.
     *
     * @param int $courseid
     * @param string $code
     * @return array Code of the scene => scene.
     */
    public static function scenes(int $courseid, string $code): array {
        global $DB;
        $json = $DB->get_field(self::T . 'own', 'scenes', ['courseid' => $courseid, 'code' => $code]);
        return $json ? (json_decode((string) $json, true) ?: []) : [];
    }

    /**
     * The mission and the scenes a draft becomes, in the format of the packages.
     *
     * @param array $draft Tidy.
     * @param string $code
     * @param int $version
     * @return array [mission, scenes]
     */
    public static function build(array $draft, string $code, int $version): array {
        $world = self::WORLD;
        $t = fn(string $text) => [$world => $text];
        $pilot = missions::packages()['livingthings'] ?? [];
        $objectives = [];
        $children = [];
        foreach ($draft['objectives'] as $objective) {
            $objectives[$objective['code']] = $objective['name'];
            $children[] = ['idnumber' => 'tesape-' . $code . '-' . strtolower($objective['code']),
                'objective' => $objective['code'], 'name' => $objective['name'],
                'description' => $objective['description'] !== '' ? $objective['description'] : $objective['name'],
                'levels' => $objective['levels']];
        }
        $places = array_values($draft['places']);
        $last = end($places)['code'];
        $chapters = [];
        foreach ($places as $i => $place) {
            $chapters[] = ['code' => $place['code'], 'name' => $t($place['name']), 'intro' => $t($place['intro']),
                'offscreen' => $place['offscreen'], 'pacts' => $place['pacts'],
                'sessions' => array_map(
                    fn($s) => ['do' => $s['do'], 'materials' => $s['materials'], 'minutes' => 50],
                    $place['sessions']
                ),
                'x' => self::SPOTS[$i][0], 'y' => self::SPOTS[$i][1], 'activities' => []];
        }
        $scenes = [];
        $first = $places[0]['code'];
        foreach ($draft['scenes'] as $scene) {
            $scenes[$scene['code']] = self::scene($scene, $code, $scene['place'] === $first);
        }
        $name = get_string('authoring_badge', 'local_oksigeniatesape', $draft['title']);
        $mission = [
            'code' => $code,
            'version' => $version,
            'edition' => 1,
            'licence' => 'free',
            'publisher' => '',
            'changes' => [],
            'lang' => current_language(),
            'ages' => $draft['ages'],
            'title' => $t($draft['title']),
            'subtitle' => $t($draft['subtitle']),
            'book' => $t(get_string('authoring_book', 'local_oksigeniatesape')),
            'prologue' => $t($draft['prologue']),
            'skins' => [$world => $pilot['skins'][$world] ?? $world],
            'variants' => ['gen' => $pilot['variants']['gen'] ?? 'gen'],
            'objectives' => $objectives,
            'framework' => [
                'idnumber' => 'tesape-' . $code,
                'name' => 'Tesapé · ' . $draft['title'],
                'description' => $draft['subtitle'] !== '' ? $draft['subtitle'] : $draft['title'],
                'scale' => $pilot['framework']['scale'] ?? [],
                'competencies' => [['idnumber' => 'tesape-' . $code . '-all', 'name' => $draft['title'],
                    'description' => $draft['subtitle'] !== '' ? $draft['subtitle'] : $draft['title'],
                    'children' => $children]],
            ],
            'badge' => ['name' => $t($name), 'description' => $t(get_string(
                'authoring_badge_desc',
                'local_oksigeniatesape',
                $draft['title']
            ))],
            'speakers' => $pilot['speakers'] ?? [],
            'items' => [],
            'battle' => ['final' => ['saber' => 30, 'lantern' => $last, 'creature' => 'grow']],
            'pages' => [],
            'letters' => [],
            'ending' => $t($draft['ending'] !== '' ? $draft['ending'] : get_string('authoring_ending', 'local_oksigeniatesape')),
            'chapters' => $chapters,
            'games' => [],
            'questions' => array_map(
                fn($q, $i) => ['id' => 'Q' . ($i + 1), 'chapter' => $q['place'],
                'objective' => $q['objective'], 'text' => $q['text'], 'options' => $q['options'], 'why' => $q['why']],
                $draft['questions'],
                array_keys($draft['questions'])
            ),
            'scenes' => array_keys($scenes),
        ];
        return [$mission, $scenes];
    }

    /**
     * A guided scene, as the game plays it: narration, moments, a rule and three endings.
     *
     * @param array $scene Tidy.
     * @param string $mission Code of the mission.
     * @param bool $hatch Whether its good endings hatch the creature (the scenes of the first place).
     * @return array
     */
    private static function scene(array $scene, string $mission, bool $hatch): array {
        $nodes = [];
        $moments = array_values($scene['moments']);
        $ids = array_map(fn($i) => 'm' . ($i + 1), array_keys($moments));
        $intro = ['type' => 'story', 'text' => $scene['text'], 'next' => $ids[0] ?? 'fin'];
        if ($scene['speaker'] !== '') {
            $intro['speaker'] = $scene['speaker'];
        }
        $nodes['intro'] = $intro;
        $total = 0;
        $objectives = [];
        foreach ($moments as $i => $moment) {
            $next = $ids[$i + 1] ?? 'fin';
            [$node, $right] = self::moment($moment, $next);
            $nodes[$ids[$i]] = $node;
            $total += $right;
            if ($moment['objective'] !== '') {
                $objectives[] = $moment['objective'];
            }
        }
        $effects = fn(int $saber) => array_merge([['saber' => $saber]], $hatch ? [['creature' => 'hatch']] : []);
        $rules = [];
        if ($total > 0) {
            $rules[] = ['if' => ['correct_at_least' => (int) ceil($total * 0.75), 'in' => $ids], 'next' => 'bien'];
            if ($total >= 2) {
                $rules[] = ['if' => ['correct_at_least' => (int) ceil($total / 2), 'in' => $ids], 'next' => 'casi'];
            }
        }
        $nodes['fin'] = ['type' => 'branch', 'rules' => $rules, 'else' => $total > 0 ? 'otra' : 'bien'];
        $nodes['bien'] = ['type' => 'ending', 'outcome' => 'success', 'effects' => $effects(20),
            'text' => $scene['success'] !== '' ? $scene['success'] : get_string('authoring_success', 'local_oksigeniatesape')];
        $nodes['casi'] = ['type' => 'ending', 'outcome' => 'partial', 'effects' => $effects(10),
            'text' => $scene['partial'] !== '' ? $scene['partial'] : get_string('authoring_partial', 'local_oksigeniatesape')];
        $nodes['otra'] = ['type' => 'ending', 'outcome' => 'retry', 'retry' => $ids[0] ?? 'intro',
            'text' => $scene['retry'] !== '' ? $scene['retry'] : get_string('authoring_retry', 'local_oksigeniatesape')];
        return [
            'id' => $scene['code'],
            'mission' => $mission,
            'chapter' => $scene['place'],
            'title' => $scene['title'],
            'step' => scenes::CORE_STEP,
            'mode' => 'both',
            'objectives' => array_values(array_unique($objectives)),
            'minutes' => 2 + 2 * count($moments),
            'start' => 'intro',
            'nodes' => $nodes,
        ];
    }

    /**
     * The node of a moment, and how many right answers it has.
     *
     * @param array $moment Tidy.
     * @param string $next
     * @return array [node, right answers]
     */
    private static function moment(array $moment, string $next): array {
        $objective = $moment['objective'];
        $base = ['prompt' => $moment['prompt'], 'hints' => $moment['hints'], 'next' => $next];
        if ($objective !== '') {
            $base['objective'] = $objective;
        }
        if ($moment['type'] === 'classify') {
            $bins = [];
            foreach ($moment['bins'] as $i => $bin) {
                $bins[] = ['id' => 'b' . ($i + 1), 'label' => $bin['label']];
            }
            $items = [];
            foreach ($moment['items'] as $i => $item) {
                $bin = 'b' . $item['bin'];
                $wrong = $item['feedback'] !== '' ? $item['feedback'] : get_string('app_notthere', 'local_oksigeniatesape');
                $feedback = [];
                foreach ($bins as $other) {
                    if ($other['id'] !== $bin) {
                        $feedback[$other['id']] = $wrong;
                    }
                }
                $items[] = array_filter(['id' => 'i' . ($i + 1), 'label' => $item['label'], 'bin' => $bin,
                    'objective' => $objective, 'ok' => $item['ok'], 'feedback' => $feedback], fn($v) => $v !== '');
            }
            return [['type' => 'classify', 'retry' => 'item', 'bins' => $bins, 'items' => $items] + $base, count($items)];
        }
        if ($moment['type'] === 'order') {
            $steps = [];
            foreach ($moment['steps'] as $i => $step) {
                $steps[] = ['id' => 't' . ($i + 1), 'label' => $step];
            }
            $stuck = $moment['stuck'] !== '' ? $moment['stuck'] : get_string('authoring_stuck', 'local_oksigeniatesape');
            return [['type' => 'order', 'steps' => $steps, 'stuck' => ['default' => $stuck]] + $base, 0];
        }
        $options = [];
        $right = 0;
        foreach ($moment['options'] as $i => $option) {
            $right += $option['correct'] ? 1 : 0;
            $options[] = array_filter(
                ['id' => 'o' . ($i + 1), 'label' => $option['label'], 'correct' => $option['correct'],
                'points' => $option['correct'] ? 2 : 0, 'objective' => $objective, 'feedback' => $option['feedback']],
                fn($v) => $v !== ''
            );
        }
        $node = ['type' => 'choice', 'options' => $options] + $base;
        if ($moment['type'] === 'many') {
            $node['select'] = ['min' => max(1, $right), 'max' => max(1, $right)];
            return [$node, $right];
        }
        return [$node, 1];
    }

    /**
     * What is wrong with a moment, if anything.
     *
     * @param array $moment Tidy.
     * @return string|null Name of the problem.
     */
    private static function moment_problem(array $moment): ?string {
        if ($moment['prompt'] === '' && $moment['type'] !== 'order') {
            return 'prompt';
        }
        switch ($moment['type']) {
            case 'one':
            case 'many':
                $right = count(array_filter($moment['options'], fn($o) => $o['correct']));
                if (count($moment['options']) < 2 || $right < 1 || ($moment['type'] === 'one' && $right > 1)) {
                    return 'options';
                }
                return null;
            case 'classify':
                $bins = count($moment['bins']);
                if ($bins < 2 || count($moment['items']) < 2) {
                    return 'classify';
                }
                foreach ($moment['items'] as $item) {
                    if ($item['bin'] < 1 || $item['bin'] > $bins) {
                        return 'classify';
                    }
                }
                return null;
            default:
                return count($moment['steps']) < 2 ? 'order' : null;
        }
    }

    /**
     * A draft cleaned: every field there, texts trimmed and cut, lists within their limits, codes for what has none.
     *
     * @param array $draft
     * @return array
     */
    private static function tidy(array $draft): array {
        $text = fn($value, int $max = 2000) => self::clean((string) ($value ?? ''), $max);
        $list = fn($value, int $max) => array_slice(array_values(array_filter((array) ($value ?? []), 'is_array')), 0, $max);
        $texts = fn($value, int $max, int $len = 500) => array_values(array_filter(array_map(
            fn($v) => self::clean((string) $v, $len),
            array_slice((array) ($value ?? []), 0, $max)
        ), fn($v) => $v !== ''));
        $code = function ($value, string $prefix, array &$used) {
            $value = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($value ?? ''));
            $n = count($used) + 1;
            while ($value === '' || isset($used[$value])) {
                $value = $prefix . $n++;
            }
            $used[$value] = true;
            return $value;
        };
        $used = [];
        $objectives = [];
        foreach ($list($draft['objectives'] ?? [], self::MAX_OBJECTIVES) as $o) {
            $levels = array_map(fn($l) => self::clean((string) $l, 500), array_slice((array) ($o['levels'] ?? []), 0, 4));
            $objectives[] = ['code' => $code($o['code'] ?? '', 'O', $used), 'name' => $text($o['name'] ?? '', 200),
                'description' => $text($o['description'] ?? '', 500), 'levels' => array_pad($levels, 4, '')];
        }
        $codes = array_column($objectives, 'code');
        $objective = fn($value) => in_array((string) $value, $codes, true) ? (string) $value : '';
        $used = [];
        $places = [];
        foreach ($list($draft['places'] ?? [], self::MAX_PLACES) as $p) {
            $sessions = [];
            foreach ($list($p['sessions'] ?? [], 6) as $s) {
                $sessions[] = ['do' => $text($s['do'] ?? '', 500), 'materials' => $text($s['materials'] ?? '', 300)];
            }
            $places[] = ['code' => $code($p['code'] ?? '', 'l', $used), 'name' => $text($p['name'] ?? '', 80),
                'intro' => $text($p['intro'] ?? '', 500), 'offscreen' => $text($p['offscreen'] ?? '', 200),
                'pacts' => $texts($p['pacts'] ?? [], 6, 200), 'sessions' => $sessions];
        }
        $placecodes = array_column($places, 'code');
        $place = fn($value) => in_array((string) $value, $placecodes, true) ? (string) $value : (string) ($placecodes[0] ?? '');
        $speakers = array_keys(missions::packages()['livingthings']['speakers'] ?? []);
        $used = [];
        $scenes = [];
        foreach ($list($draft['scenes'] ?? [], self::MAX_PLACES * 6) as $s) {
            $moments = [];
            foreach ($list($s['moments'] ?? [], self::MAX_MOMENTS) as $m) {
                $type = in_array($m['type'] ?? '', self::MOMENTS, true) ? $m['type'] : 'one';
                $options = [];
                foreach ($list($m['options'] ?? [], 6) as $o) {
                    $options[] = ['label' => $text($o['label'] ?? '', 200), 'correct' => !empty($o['correct']),
                        'feedback' => $text($o['feedback'] ?? '', 500)];
                }
                $items = [];
                foreach ($list($m['items'] ?? [], 12) as $i) {
                    $items[] = ['label' => $text($i['label'] ?? '', 120), 'bin' => (int) ($i['bin'] ?? 0),
                        'ok' => $text($i['ok'] ?? '', 300), 'feedback' => $text($i['feedback'] ?? '', 300)];
                }
                $moments[] = ['type' => $type, 'prompt' => $text($m['prompt'] ?? '', 500),
                    'objective' => $objective($m['objective'] ?? ''), 'hints' => $texts($m['hints'] ?? [], 3, 300),
                    'options' => array_values(array_filter($options, fn($o) => $o['label'] !== '')),
                    'bins' => array_values(array_filter(array_map(
                        fn($b) => ['label' => $text($b['label'] ?? '', 80)],
                        $list($m['bins'] ?? [], 4)
                    ), fn($b) => $b['label'] !== '')),
                    'items' => array_values(array_filter($items, fn($i) => $i['label'] !== '')),
                    'steps' => $texts($m['steps'] ?? [], 8, 200), 'stuck' => $text($m['stuck'] ?? '', 300)];
            }
            $speaker = (string) ($s['speaker'] ?? '');
            $scenes[] = ['code' => $code($s['code'] ?? '', 's', $used), 'place' => $place($s['place'] ?? ''),
                'title' => $text($s['title'] ?? '', 120), 'speaker' => in_array($speaker, $speakers, true) ? $speaker : '',
                'text' => $text($s['text'] ?? ''), 'moments' => $moments, 'success' => $text($s['success'] ?? '', 800),
                'partial' => $text($s['partial'] ?? '', 800), 'retry' => $text($s['retry'] ?? '', 800)];
        }
        $questions = [];
        foreach ($list($draft['questions'] ?? [], edits::MAX_QUESTIONS) as $q) {
            $questions[] = ['place' => $place($q['place'] ?? ''), 'objective' => $objective($q['objective'] ?? ''),
                'text' => $text($q['text'] ?? '', 500), 'options' => $texts($q['options'] ?? [], 6, 200),
                'why' => $text($q['why'] ?? '', 500)];
        }
        $questions = array_values(array_filter($questions, fn($q) => $q['text'] !== '' && count($q['options']) >= 2));
        return ['title' => $text($draft['title'] ?? '', 120), 'subtitle' => $text($draft['subtitle'] ?? '', 200),
            'ages' => $text($draft['ages'] ?? '', 40), 'prologue' => $text($draft['prologue'] ?? ''),
            'ending' => $text($draft['ending'] ?? ''), 'objectives' => $objectives, 'places' => $places,
            'scenes' => $scenes, 'questions' => $questions];
    }

    /**
     * A text of the teacher: plain, trimmed and cut.
     *
     * @param string $value
     * @param int $max
     * @return string
     */
    private static function clean(string $value, int $max): string {
        return \core_text::substr(trim(clean_param($value, PARAM_TEXT)), 0, $max);
    }
}
