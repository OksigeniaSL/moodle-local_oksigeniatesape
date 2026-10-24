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
 * Minigames of a mission. What is learnt is in how they are played; they are short and give what the mission needs
 * (items, the coat of the creature), once. The client sends what the student did and the server counts it again.
 *
 * Defensa del nido: animals come down towards the nest; the right net is the one of their group, or of the group that
 * contains it when that net is not in the step. A wrong net, or none, lets the animal pass and hurts the shield.
 *
 * The hunt for mistakes (type errors): the Embrollo has changed facts in the sheets of the field notebook; the student
 * touches what is wrong and is shown what is right and why. A word touched that was right is a blot of ink, and too many
 * blots lose the game. The sheets are written as in the board of Classtools: [what it says|right/also right|why].
 *
 * The two circles (type venn): each thing of a sheet goes in one circle, in the other or in the middle, where they meet.
 * Sheets are written as in Classtools: «Group: thing, thing» for each circle (with «;» if a thing has commas), a thing
 * in both groups goes in the middle, and «? thing: why». A thing in the wrong place is a slip.
 *
 * Who am I? (type whoami): a shadow gives its clues one by one, from the hardest to the easiest, and the student
 * guesses who it is among four; a wrong guess shows the next clue, and with no clue left the shadow gets away.
 * Written as in Classtools: «= answer / also accepted» and a clue per line.
 *
 * Guess who (type guesswho), the naturalist's: twelve beings of the catalog face up and one of them hidden; the student
 * asks by traits («Does it have feathers?», «Is it born from an egg?») and the beings that do not fit are turned over,
 * until they say who it is. The questions come from the traits of the catalog: only those that every card answers and
 * that split them. Never lost; with few questions, the card of the being goes golden to the field guide. A wrong name
 * turns that card over and counts as a question.
 *
 * Who is alive? (type whack): living things and things that are not come out of the holes of the clearing for a moment;
 * the student touches only what is alive. A thing touched that is not alive is a slip, with its why; a living thing let
 * go is not caught.
 *
 * The burst of the Embrollo (type burst): micro-challenges of one word, one after the other and faster each time: «Alive!»
 * (is this thing alive?), «Group!» (the group of a being) and «Think!» (a question of the battle from the places the
 * class has opened); three misses and it is over, and at the end the Embrollo throws three things at once. Made from the
 * catalog and the questions of the mission, another one each play.
 *
 * The terrarium of the clearing (type terrarium): what is alive is a process, and here time passes. The student puts in
 * a terrarium what they think is alive and lets a week go by: what is alive changes (it grows, eats, has young), what is
 * not stays the same, or changes without being alive (water dries up, a dry leaf crumbles). They take out what did not
 * change and try again, until everything inside is alive. Never lost: the weeks it took are what the teacher sees.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class minigames {
    /** @var int The most a key of the leaf factory opens. */
    public const LEAF_MOST = 4;

    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Coats a creature can wear. */
    public const COATS = ['fur', 'feathers', 'scales'];

    /** @var int Mix-ups shown to the teacher per game. */
    public const CONFUSIONS = 3;

    /** @var int Right answers in a row after which the healer gets back the first slip of a play. */
    public const STREAK = 5;

    /** @var int Gems for a minigame won again, after the first time. */
    public const AGAIN_GEMS = 1;

    /** @var int Minigames won again that give gems in a day, all of them together; then, training: the streak alone. */
    public const TRAINING = 3;

    /** @var string[][] The questions of Guess who: trait of the catalog => values asked about. */
    public const GUESS = [
        'kind' => ['animal', 'spine'],
        'cover' => ['pelo', 'plumas', 'escamas', 'piel-humeda', 'concha', 'exoesqueleto', 'piel-blanda'],
        'legs' => ['any', '2', '4', '6', '8'],
        'breath' => ['pulmones', 'branquias', 'piel', 'traqueas'],
        'born' => ['huevo', 'vivo', 'semilla', 'espora'],
        'eats' => ['herbivoro', 'carnivoro', 'omnivoro', 'fotosintesis', 'descomponedor', 'parasito'],
        'habitat' => ['tierra', 'agua-dulce', 'mar', 'subsuelo', 'casa', 'aire'],
    ];

    /** @var int Micro-challenges of a burst before the three things at once. */
    public const BURST = 9;

    /** @var int Misses that end a burst. */
    public const BURST_MISSES = 3;

    /** @var int[] Seconds for each kind of micro-challenge, at the start of a burst. */
    public const BURST_SECONDS = ['alive' => 8, 'group' => 10, 'question' => 16, 'boss' => 14];

    /** @var string[] Groups of the catalog that are animals, for «Group!». */
    private const ANIMALGROUPS = ['mamifero', 'ave', 'reptil', 'anfibio', 'pez', 'insecto', 'aracnido', 'molusco'];

    /** @var string[] Groups of the catalog that are not animals. */
    private const NOTANIMALS = ['planta', 'alga', 'hongo', 'bacteria'];

    /** @var string[] Groups of the catalog with a backbone. */
    private const VERTEBRATES = ['mamifero', 'ave', 'reptil', 'anfibio', 'pez'];

    /**
     * A minigame of the mission of a course, as a student of a step and a role plays it: texts in the skin and the
     * local examples, what there is to play, and the passive of the role (the protector holds one slip more).
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param int $step
     * @param string $role Of the student: protector, healer, sage or scout.
     * @param string[] $talents Those of the student that act in the course.
     * @param int $sniffs Times their creature sniffs in a play (creatures::sniffs()).
     * @param string[] $worn What the hero wears (equipment::worn()): slot => item.
     * @param int $charid Of the student, and $plays, the plays they have kept: in Guess who, which being hides.
     * @param int $plays
     * @return array|null
     */
    public static function config(
        \stdClass $mission,
        string $code,
        int $step,
        string $role = '',
        array $talents = [],
        int $sniffs = 0,
        array $worn = [],
        int $charid = 0,
        int $plays = 0
    ): ?array {
        $game = $mission->data['games'][$code] ?? null;
        if (($game['type'] ?? '') === 'guesswho') {
            $config = self::guesswho_config($mission, $code, $game, $step, $charid, $plays);
        } else if (($game['type'] ?? '') === 'terrarium') {
            $config = self::terrarium_config($mission, $code, $game, $step);
        } else if (($game['type'] ?? '') === 'burst') {
            $config = self::burst_config($mission, $code, $game, $charid, $plays);
        } else {
            $config = self::config_step($mission, $code, $step);
        }
        if (!$config) {
            return null;
        }
        // The two lines at the start: what the Embrollo did and how the creature takes it ({{creature}}, for the client).
        $scene = $mission->data['games'][$code]['scene'] ?? [];
        $config['scene'] = [
            'embrollo' => missions::text($scene['embrollo'] ?? '', $mission->skin, $mission->variant),
            'creature' => missions::text($scene['creature'] ?? '', $mission->skin, $mission->variant),
        ];
        $config['passive'] = in_array($role, characters::ROLES, true) ? $role : '';
        $has = fn($code) => in_array($code, $talents, true);
        // «Second wind»: the healer gets the slip back after three, not five.
        $config['streak'] = $config['passive'] === 'healer' && $has('secondwind') ? 3 : self::STREAK;
        // The looks of the sage («Keen eye»: two) and what the scout sees coming («Far sight»: two things).
        $config['looks'] = $config['passive'] === 'sage' ? ($has('keeneye') ? 2 : 1) : 0;
        $config['ahead'] = $config['passive'] === 'scout' ? ($has('farsight') ? 2 : 1) : 0;
        // The creature, from its second stage, points out a case once a play.
        $config['sniffs'] = $sniffs;
        // The magnifier in the hand: a look more, for anyone; the net in the pack, in the nest, catches one that slips.
        $config['lens'] = ($worn['hand'] ?? '') === 'magnifier' ? 1 : 0;
        $config['looks'] += $config['lens'];
        $config['packnet'] = ($worn['pack'] ?? '') === 'net' && !empty($config['shield']) ? 1 : 0;
        if ($config['packnet']) {
            $config['shield'] += 1;
            $config['goal'] = max(1, $config['goal'] - 1);
        }
        if ($config['passive'] === 'protector') {
            // «Endurance»: two slips more, not one.
            $more = $has('endurance') ? 2 : 1;
            $config['extra'] = $more;
            if (isset($config['misses'])) {
                $config['misses'] += $more;
            }
            if (isset($config['golden'])) {
                // In Guess who, the card is golden with a question more.
                $config['golden'] += $more;
            }
            if (!empty($config['shield'])) {
                // In the nest, each animal that gets in is one not caught: the goal comes down with the shield.
                $config['shield'] += $more;
                $config['goal'] = max(1, $config['goal'] - $more);
            }
        }
        return $config;
    }

    /**
     * A minigame as a student of a step plays it, before the passive of their role.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param int $step
     * @return array|null
     */
    private static function config_step(\stdClass $mission, string $code, int $step): ?array {
        $game = $mission->data['games'][$code] ?? null;
        if (!$game) {
            return null;
        }
        if (($game['type'] ?? '') === 'errors') {
            return self::errors_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'whack') {
            return self::whack_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'venn') {
            return self::venn_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'whoami') {
            return self::whoami_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'leaf') {
            return self::leaf_config($mission, $code, $game, $step);
        }
        if (($game['type'] ?? '') === 'chain') {
            return self::chain_config($mission, $code, $game, $step);
        }
        $level = self::level($game, $step);
        $nets = [];
        foreach ($level['nets'] as $net) {
            $nets[] = ['id' => $net, 'label' => (string) $game['nets'][$net]['label'],
                'hint' => (string) ($game['nets'][$net]['hint'] ?? '')];
        }
        $animals = [];
        foreach ($level['animals'] as $id) {
            $animal = $game['animals'][$id];
            $label = $animal['label'];
            $animals[] = [
                'id' => $id,
                'label' => is_array($label) ? (string) ($label[$mission->variant] ?? reset($label)) : (string) $label,
                'net' => self::net($game, (string) $animal['group'], $level['nets']),
                'why' => (string) ($animal['why'] ?? ''),
            ];
        }
        return [
            'code' => $code,
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => $nets,
            'animals' => $animals,
            'goal' => (int) $level['goal'],
            'shield' => (int) $level['shield'],
            'speed' => (int) $level['speed'],
        ];
    }

    /**
     * A hunt for mistakes as a student of a step plays it: the sheets of their step, each cut into text and mistakes.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function errors_config(\stdClass $mission, string $code, array $game, int $step): array {
        $sheets = [];
        foreach (self::sheets_for($game, $step) as $sheet) {
            $text = $sheet['text'] ?? '';
            $text = is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
            // The being it talks about, drawn and tangled: it comes loose as its mistakes are found.
            $sheets[] = ['id' => (string) $sheet['id'], 'title' => missions::text((string) ($sheet['title'] ?? ''), $mission->skin),
                'parts' => self::parts($text), 'picture' => self::being_picture($mission, $sheet)];
        }
        $goal = 0;
        foreach ($sheets as $sheet) {
            $goal += count(array_filter($sheet['parts'], fn($p) => $p['mark'] >= 0));
        }
        return [
            'code' => $code,
            'type' => 'errors',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'sheets' => $sheets,
            'goal' => $goal,
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Who is alive? as a student of a step plays it: the things of their level, each one alive or not, with its why.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function whack_config(\stdClass $mission, string $code, array $game, int $step): array {
        $level = self::level($game, $step);
        $things = [];
        foreach ($level['things'] ?? [] as $id) {
            $thing = $game['things'][$id] ?? null;
            if (!$thing) {
                continue;
            }
            $label = $thing['label'];
            $why = $thing['why'] ?? '';
            $things[] = ['id' => (string) $id,
                'label' => is_array($label) ? (string) ($label[$mission->variant] ?? $label['gen'] ?? reset($label)) : (string) $label,
                'alive' => !empty($thing['alive']),
                'why' => is_array($why) ? (string) ($why[$mission->variant] ?? $why['gen'] ?? reset($why)) : (string) $why,
                'picture' => self::picture($mission, (string) $id, !empty($thing['alive']))];
        }
        return [
            'code' => $code,
            'type' => 'whack',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'things' => $things,
            'goal' => (int) ($level['goal'] ?? 0),
            'misses' => max(1, (int) ($level['misses'] ?? 3)),
            'shield' => 0,
            'speed' => (int) ($level['speed'] ?? 1),
        ];
    }

    /**
     * The key of the drawing of a being or a thing of the mission in the art pack: being.<id> if it is alive in the
     * catalog of the mission (or in the game, if the catalog does not have it), thing.<id> if not. A being named by the
     * country of the course (the Iberian lynx, the jaguar or the wildcat) is drawn as the one it names, as in the defence
     * of the nest: being.linceiberico.
     *
     * @param \stdClass $mission
     * @param string $id
     * @param bool $alive As the game says.
     * @return string
     */
    public static function picture(\stdClass $mission, string $id, bool $alive): string {
        $card = $mission->data['things'][$id] ?? null;
        $living = (bool) ($card['alive'] ?? $alive);
        if ($living && is_array($card['label'] ?? null)) {
            $label = (string) ($card['label'][$mission->variant] ?? $card['label']['gen'] ?? reset($card['label']));
            return 'being.' . preg_replace('/[^a-z]/', '', \core_text::strtolower(\core_text::specialtoascii($label)));
        }
        return ($living ? 'being.' : 'thing.') . $id;
    }

    /**
     * The drawing of the being a sheet of a hunt for mistakes talks about (being), or nothing.
     *
     * @param \stdClass $mission
     * @param array $sheet
     * @return string
     */
    public static function being_picture(\stdClass $mission, array $sheet): string {
        $id = (string) ($sheet['being'] ?? '');
        return $id !== '' && !empty($mission->data['things'][$id]['alive']) ? self::picture($mission, $id, true) : '';
    }

    /**
     * The two circles as a student of a step plays them: the sheets of their step, each with its two groups and its
     * things.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function venn_config(\stdClass $mission, string $code, array $game, int $step): array {
        $sheets = [];
        $goal = 0;
        foreach (self::sheets_for($game, $step) as $sheet) {
            $venn = self::venn(self::sheet_text($mission, $sheet));
            $goal += count($venn['items']);
            $title = missions::text((string) ($sheet['title'] ?? ''), $mission->skin);
            $sheets[] = ['id' => (string) $sheet['id'], 'title' => $title, 'parts' => []] + $venn;
        }
        return [
            'code' => $code,
            'type' => 'venn',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'sheets' => $sheets,
            'goal' => $goal,
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Who am I? as a student of a step plays it: the riddles of their step, each with its clues and four answers to
     * choose from (its own and three of the other riddles or decoys of the game).
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function whoami_config(\stdClass $mission, string $code, array $game, int $step): array {
        $pool = [];
        foreach ($game['sheets'] ?? [] as $sheet) {
            $pool[] = self::riddle(self::sheet_text($mission, $sheet))['answer'];
        }
        foreach ($game['decoys'] ?? [] as $decoy) {
            $pool[] = is_array($decoy) ? (string) ($decoy[$mission->variant] ?? $decoy['gen'] ?? reset($decoy)) : (string) $decoy;
        }
        $pool = array_values(array_unique(array_filter($pool)));
        $riddles = [];
        foreach (self::sheets_for($game, $step) as $sheet) {
            $riddle = self::riddle(self::sheet_text($mission, $sheet));
            $others = array_values(array_diff($pool, [$riddle['answer']]));
            shuffle($others);
            $options = array_merge([$riddle['answer']], array_slice($others, 0, 3));
            shuffle($options);
            $riddles[] = ['id' => (string) $sheet['id'], 'answer' => $riddle['answer'], 'clues' => $riddle['clues'],
                'options' => $options];
        }
        return [
            'code' => $code,
            'type' => 'whoami',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'riddles' => $riddles,
            'goal' => count($riddles),
            'misses' => self::misses($game, $step),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * The leaf factory as a student of a step plays it: rounds of a recipe (light, water and air, each from 0 to 4, to
     * fill the jar with as much sugar as the round asks, wasting nothing: the leaf makes as much as the least of the
     * three lets it) and rounds of a question, with their options in an order of their own.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function leaf_config(\stdClass $mission, string $code, array $game, int $step): array {
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $rounds = [];
        foreach ($game['rounds'] ?? [] as $round) {
            $out = ['id' => (string) $round['id'], 'kind' => (string) ($round['kind'] ?? 'recipe'),
                'title' => $text($round['title'] ?? ''), 'prompt' => $text($round['prompt'] ?? ''),
                'why' => $text($round['why'] ?? '')];
            if ($out['kind'] === 'question') {
                $options = array_values(array_map($text, (array) ($round['options'] ?? [])));
                shuffle($options);
                $out += ['answer' => $text($round['answer'] ?? ''), 'options' => $options];
            } else {
                $caps = (array) ($round['caps'] ?? []);
                $out += ['target' => (int) ($round['target'] ?? 3), 'caps' => ['light' => (int) ($caps['light'] ?? self::LEAF_MOST),
                    'water' => (int) ($caps['water'] ?? self::LEAF_MOST), 'air' => (int) ($caps['air'] ?? self::LEAF_MOST)]];
            }
            $rounds[] = $out;
        }
        $misses = self::misses($game, $step);
        return [
            'code' => $code,
            'type' => 'leaf',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => $text($game['intro'] ?? ''),
            'win' => $text($game['win'] ?? ''),
            'lose' => $text($game['lose'] ?? ''),
            'nets' => [],
            'animals' => [],
            'rounds' => $rounds,
            'goal' => max(1, count($rounds) - $misses),
            'misses' => $misses,
            'most' => self::LEAF_MOST,
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Eslabón as a student of a step plays it: rounds to put the links of a chain in their order from the sun (cards of
     * the catalog, shuffled) and rounds of a question.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function chain_config(\stdClass $mission, string $code, array $game, int $step): array {
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $rounds = [];
        foreach ($game['rounds'] ?? [] as $round) {
            $out = ['id' => (string) $round['id'], 'kind' => (string) ($round['kind'] ?? 'order'),
                'title' => $text($round['title'] ?? ''), 'prompt' => $text($round['prompt'] ?? ''),
                'why' => $text($round['why'] ?? '')];
            if ($out['kind'] === 'question') {
                $options = array_values(array_map($text, (array) ($round['options'] ?? [])));
                shuffle($options);
                $out += ['answer' => $text($round['answer'] ?? ''), 'options' => $options];
            } else {
                $chain = array_values(array_map('strval', (array) ($round['chain'] ?? [])));
                $cards = [];
                foreach ($chain as $id) {
                    $thing = $mission->data['things'][$id] ?? [];
                    $cards[] = ['id' => $id, 'label' => self::variant($mission, $thing['label'] ?? $id),
                        'picture' => self::picture($mission, $id, !empty($thing['alive']))];
                }
                shuffle($cards);
                $out += ['order' => $chain, 'cards' => $cards];
            }
            $rounds[] = $out;
        }
        $misses = self::misses($game, $step);
        return [
            'code' => $code,
            'type' => 'chain',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => $text($game['intro'] ?? ''),
            'win' => $text($game['win'] ?? ''),
            'lose' => $text($game['lose'] ?? ''),
            'nets' => [],
            'animals' => [],
            'rounds' => $rounds,
            'goal' => max(1, count($rounds) - $misses),
            'misses' => $misses,
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * Counts a play of Eslabón: each round once; a chain is right with its links in their order, a question with its
     * own answer.
     *
     * @param array $game As the student plays it.
     * @param array $events Each one: r (the round) and o (the ids in the order put) or g (the answer, as shown).
     * @return array [right, wrong rounds, wrong (round => what was done), success]
     */
    private static function count_chain(array $game, array $events): array {
        $rounds = array_column($game['rounds'], null, 'id');
        $seen = [];
        $right = 0;
        $wrong = [];
        foreach ($events as $event) {
            $id = (string) ($event['r'] ?? '');
            $round = $rounds[$id] ?? null;
            if (!$round || isset($seen[$id])) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$id] = true;
            if ($round['kind'] === 'question') {
                $what = trim((string) ($event['g'] ?? ''));
                $ok = \core_text::strtolower($what) === \core_text::strtolower($round['answer']);
            } else {
                $put = array_values(array_map('strval', (array) ($event['o'] ?? [])));
                if (count($put) !== count($round['order']) || array_diff($put, $round['order'])) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $ok = $put === $round['order'];
                $what = implode(',', $put);
            }
            if ($ok) {
                $right++;
            } else {
                $wrong[$id] = $what;
            }
        }
        $missed = count($rounds) - $right;
        return [$right, $missed, $wrong, $missed <= (int) $game['misses']];
    }

    /**
     * Counts a play of the leaf factory: each round once. A recipe is right when the three keys are the same and give
     * the sugar the round asks, within what it lets (the cloud, the dry root); a question, when the answer is its own.
     *
     * @param array $game As the student plays it.
     * @param array $events Each one: r (the round) and l, w, a (a recipe) or g (the answer, as shown).
     * @return array [right, wrong rounds, wrong (round => what was done), success]
     */
    private static function count_leaf(array $game, array $events): array {
        $rounds = array_column($game['rounds'], null, 'id');
        $seen = [];
        $right = 0;
        $wrong = [];
        foreach ($events as $event) {
            $id = (string) ($event['r'] ?? '');
            $round = $rounds[$id] ?? null;
            if (!$round || isset($seen[$id])) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$id] = true;
            if ($round['kind'] === 'question') {
                $guess = trim((string) ($event['g'] ?? ''));
                $ok = \core_text::strtolower($guess) === \core_text::strtolower($round['answer']);
                $what = $guess;
            } else {
                $keys = ['light' => (int) ($event['l'] ?? -1), 'water' => (int) ($event['w'] ?? -1), 'air' => (int) ($event['a'] ?? -1)];
                foreach ($keys as $key => $value) {
                    if ($value < 0 || $value > $round['caps'][$key]) {
                        throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                    }
                }
                $ok = count(array_unique($keys)) === 1 && min($keys) === $round['target'];
                $what = implode('-', $keys);
            }
            if ($ok) {
                $right++;
            } else {
                $wrong[$id] = $what;
            }
        }
        $missed = count($rounds) - $right;
        return [$right, $missed, $wrong, $missed <= (int) $game['misses']];
    }

    /**
     * Guess who as a student of a step plays it: the beings of the game (from the catalog of the mission), the questions
     * that split them, and the one that hides this time. Each student goes through all of them, play after play, in an
     * order of their own.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @param int $charid
     * @param int $plays Plays the student has kept.
     * @return array
     */
    private static function guesswho_config(
        \stdClass $mission,
        string $code,
        array $game,
        int $step,
        int $charid,
        int $plays
    ): array {
        $catalog = $mission->data['things'] ?? [];
        $ids = array_values(array_filter(array_map('strval', $game['beings'] ?? []), fn($id) => isset($catalog[$id])));
        $cards = [];
        foreach ($ids as $id) {
            $cards[] = ['id' => $id, 'label' => self::variant($mission, $catalog[$id]['label'] ?? $id),
                'name' => self::variant($mission, $catalog[$id]['name'] ?? $catalog[$id]['label'] ?? $id),
                'picture' => self::picture($mission, $id, true)];
        }
        $questions = [];
        foreach (self::GUESS as $trait => $values) {
            foreach ($values as $value) {
                $yes = [];
                foreach ($ids as $id) {
                    $answer = self::answer($catalog[$id], $trait, (string) $value);
                    if ($answer === null) {
                        // A card the catalog does not answer: the question is not asked.
                        continue 2;
                    }
                    if ($answer) {
                        $yes[] = $id;
                    }
                }
                if ($yes && count($yes) < count($ids)) {
                    $questions[] = ['id' => $trait . '_' . $value, 'trait' => $trait,
                        'text' => get_string(
                            'guesswho_q_' . $trait . '_' . str_replace('-', '', (string) $value),
                            'local_oksigeniatesape'
                        ), 'yes' => $yes];
                }
            }
        }
        // Each student, an order of their own, and a being after another: all of them before one comes back.
        $order = $ids;
        usort($order, fn($a, $b) => crc32($code . '|' . $charid . '|' . $a) <=> crc32($code . '|' . $charid . '|' . $b));
        return [
            'code' => $code,
            'type' => 'guesswho',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'cards' => $cards,
            'questions' => $questions,
            'secret' => $order ? $order[$plays % count($order)] : '',
            'seed' => $plays,
            'golden' => self::by_step($game['golden'] ?? [], $step, 4),
            'goal' => 1,
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * A burst of the Embrollo for the class screen: the micro-challenges of the burst of the mission, drawn for a moment
     * (when the teacher put it on the screen), with no student. The class answers aloud and the screen shows the answer.
     *
     * @param \stdClass $mission With data.
     * @param int $seed
     * @return array The micro-challenges, as in a play; empty if the mission has no burst.
     */
    public static function class_burst(\stdClass $mission, int $seed): array {
        foreach ($mission->data['games'] ?? [] as $code => $game) {
            if (($game['type'] ?? '') === 'burst') {
                return self::burst_config($mission, (string) $code, $game, 0, $seed)['burst'];
            }
        }
        return [];
    }

    /**
     * A burst of the Embrollo: micro-challenges drawn for this play (by the student and the plays they kept), from the
     * catalog and from the questions of the places the class has opened, and the three things at once at the end.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $charid
     * @param int $plays Plays the student has kept: each one, another burst.
     * @return array
     */
    private static function burst_config(\stdClass $mission, string $code, array $game, int $charid, int $plays): array {
        global $DB;
        $catalog = $mission->data['things'] ?? [];
        $seed = $code . '|' . $charid . '|' . $plays;
        $shuffle = function (array $list, string $salt) use ($seed): array {
            usort($list, fn($a, $b) => crc32($seed . $salt . json_encode($a)) <=> crc32($seed . $salt . json_encode($b)));
            return $list;
        };
        $word = fn($kind) => get_string('burst_word_' . $kind, 'local_oksigeniatesape');
        $things = [];
        foreach ($catalog as $id => $card) {
            $things[] = ['id' => (string) $id, 'label' => self::variant($mission, $card['label'] ?? $id),
                'alive' => !empty($card['alive']), 'group' => (string) ($card['group'] ?? ''),
                'picture' => self::picture($mission, (string) $id, !empty($card['alive']))];
        }
        // «Alive!»: half alive and half not, as they come.
        $alive = $shuffle($things, 'a');
        // «Group!»: the animals, plants and fungi (an alga or a bacterium would answer with its own name); the other options,
        // from their own family.
        $groups = $shuffle(array_values(array_filter($things, fn($x) => in_array($x['group'], self::ANIMALGROUPS, true) ||
            in_array($x['group'], ['planta', 'hongo'], true))), 'g');
        // «Think!»: the questions of the places the class has lit, and of the place it is opening.
        $lit = $DB->get_records_menu(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder', 'code, litat');
        $upto = 1;
        foreach (array_values($lit) as $i => $litat) {
            if ($litat) {
                $upto = $i + 2;
            }
        }
        $codes = array_slice(array_map(fn($c) => (string) $c['code'], $mission->data['chapters'] ?? []), 0, $upto);
        // The place of a question, by its code or by its number.
        $place = fn($c) => is_numeric($c) ? (string) ($mission->data['chapters'][(int) $c - 1]['code'] ?? '') : (string) $c;
        $questions = $shuffle(array_values(array_filter(
            $mission->data['questions'] ?? [],
            fn($q) => in_array($place($q['chapter'] ?? 1), $codes, true) && count($q['options'] ?? []) >= 2
        )), 'q');
        $items = [];
        $kinds = ['alive', 'group', 'question'];
        $taken = [];
        for ($i = 0; $i < self::BURST; $i++) {
            $kind = $kinds[$i % 3];
            if ($kind === 'question' && !$questions) {
                $kind = 'alive';
            }
            if ($kind === 'alive' || ($kind === 'group' && !$groups)) {
                $x = null;
                while ($alive && (!$x || isset($taken[$x['id']]))) {
                    $x = array_shift($alive);
                }
                $taken[$x['id']] = true;
                $items[] = ['kind' => 'alive', 'word' => $word('alive'), 'prompt' => get_string('burst_ask_alive', 'local_oksigeniatesape'),
                    'label' => $x['label'], 'picture' => $x['picture'], 'options' => [
                        get_string('burst_alive_yes', 'local_oksigeniatesape'),
                        get_string('burst_alive_no', 'local_oksigeniatesape'),
                    ], 'right' => $x['alive'] ? 0 : 1, 'seconds' => self::BURST_SECONDS['alive'], 'things' => []];
            } else if ($kind === 'group') {
                $x = array_shift($groups);
                $family = in_array($x['group'], self::ANIMALGROUPS, true) ? self::ANIMALGROUPS : self::NOTANIMALS;
                $others = array_slice($shuffle(array_values(array_diff($family, [$x['group']])), 'o' . $i), 0, 2);
                $options = $shuffle(array_merge([$x['group']], $others), 'p' . $i);
                $items[] = ['kind' => 'group', 'word' => $word('group'), 'prompt' => get_string('burst_ask_group', 'local_oksigeniatesape'),
                    'label' => $x['label'], 'picture' => $x['picture'],
                    'options' => array_map(fn($g) => get_string('burst_group_' . $g, 'local_oksigeniatesape'), $options),
                    'right' => (int) array_search($x['group'], $options, true), 'seconds' => self::BURST_SECONDS['group'],
                    'things' => []];
            } else {
                $q = array_shift($questions);
                $options = array_map(fn($o) => missions::text($o, $mission->skin, $mission->variant), array_values($q['options']));
                $order = $shuffle(array_keys($options), 'q' . $i);
                $items[] = ['kind' => 'question', 'word' => $word('question'),
                    'prompt' => missions::text($q['text'] ?? '', $mission->skin, $mission->variant), 'label' => '', 'picture' => '',
                    'options' => array_map(fn($k) => $options[$k], $order), 'right' => (int) array_search(0, $order, true),
                    'seconds' => self::BURST_SECONDS['question'], 'things' => []];
            }
        }
        // The Embrollo at the end: three things at once, to touch those that are alive (at least one, never all three).
        $three = [];
        foreach ($alive as $x) {
            if (count($three) < 3 && !isset($taken[$x['id']])) {
                $three[] = $x;
            }
        }
        $count = count(array_filter($three, fn($x) => $x['alive']));
        if (count($three) === 3 && ($count === 0 || $count === 3)) {
            // One of the other side takes the place of the last.
            foreach ($alive as $x) {
                if ($x['alive'] !== $three[0]['alive'] && !in_array($x, $three, true)) {
                    $three[2] = $x;
                    break;
                }
            }
        }
        $items[] = ['kind' => 'boss', 'word' => $word('boss'), 'prompt' => get_string('burst_ask_boss', 'local_oksigeniatesape'),
            'label' => '', 'picture' => '', 'options' => [], 'right' => 0, 'seconds' => self::BURST_SECONDS['boss'],
            'things' => array_map(fn($x) => ['id' => $x['id'], 'label' => $x['label'], 'picture' => $x['picture'],
                'alive' => $x['alive']], $three)];
        return [
            'code' => $code,
            'type' => 'burst',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'burst' => $items,
            'seed' => $plays,
            'misses' => self::BURST_MISSES,
            'goal' => count($items),
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * The terrarium of the clearing as a student of a step plays it: the things of their step, from the catalog, with
     * what a week does to each one; how many living things the terrarium needs, and how many fit in it.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function terrarium_config(\stdClass $mission, string $code, array $game, int $step): array {
        $catalog = $mission->data['things'] ?? [];
        $level = self::level($game, $step);
        $things = [];
        foreach ($level['things'] ?? [] as $id) {
            $card = $catalog[$id] ?? null;
            if (!$card) {
                continue;
            }
            $alive = !empty($card['alive']);
            $picture = self::picture($mission, (string) $id, $alive);
            $things[] = ['id' => (string) $id, 'label' => self::variant($mission, $card['label'] ?? $id), 'alive' => $alive,
                'week' => isset($card['week']) ? self::variant($mission, $card['week']) : '',
                'why' => isset($card['note']) ? self::variant($mission, $card['note']) : '',
                'picture' => $picture, 'weekpicture' => $picture . '.week'];
        }
        $living = count(array_filter($things, fn($x) => $x['alive']));
        $most = max(2, (int) ($game['most'] ?? 8));
        return [
            'code' => $code,
            'type' => 'terrarium',
            'title' => missions::text($game['title'] ?? $code, $mission->skin),
            'intro' => missions::text($game['intro'] ?? '', $mission->skin, $mission->variant),
            'win' => missions::text($game['win'] ?? '', $mission->skin, $mission->variant),
            'lose' => missions::text($game['lose'] ?? '', $mission->skin, $mission->variant),
            'nets' => [],
            'animals' => [],
            'things' => $things,
            'goal' => max(1, min($living, $most, (int) ($level['goal'] ?? 4))),
            'most' => $most,
            'shield' => 0,
            'speed' => 0,
        ];
    }

    /**
     * What the catalog says of a being for a question of Guess who: yes, no, or null if it does not say. What only
     * animals have (a cover, legs, a way of breathing) is a no for the rest.
     *
     * @param array $card Of the catalog.
     * @param string $trait
     * @param string $value
     * @return bool|null
     */
    public static function answer(array $card, string $trait, string $value): ?bool {
        $group = (string) ($card['group'] ?? '');
        if ($trait === 'kind') {
            if ($group === '') {
                return null;
            }
            return $value === 'spine' ? in_array($group, self::VERTEBRATES, true) : !in_array($group, self::NOTANIMALS, true);
        }
        if (in_array($trait, ['cover', 'legs', 'breath'], true) && in_array($group, self::NOTANIMALS, true)) {
            return false;
        }
        if (!isset($card[$trait])) {
            return null;
        }
        if ($trait === 'legs') {
            return $value === 'any' ? (int) $card['legs'] > 0 : (int) $card['legs'] === (int) $value;
        }
        return in_array($value, array_map('strval', (array) $card[$trait]), true);
    }

    /**
     * A name of the catalog in the variant of the course ({es, py, gen}) or in the story ({valley, azara}).
     *
     * @param \stdClass $mission
     * @param mixed $text
     * @return string
     */
    private static function variant(\stdClass $mission, $text): string {
        if (is_array($text) && (isset($text[$mission->variant]) || isset($text['gen']))) {
            return (string) ($text[$mission->variant] ?? $text['gen']);
        }
        return missions::text($text, $mission->skin, $mission->variant);
    }

    /**
     * A riddle of Who am I? from its plain text: the answer (and others accepted) and the clues, hardest first.
     *
     * @param string $text
     * @return array answer, accept and clues.
     */
    public static function riddle(string $text): array {
        $answer = '';
        $accept = [];
        $clues = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($line[0] === '=') {
                $names = array_values(array_filter(array_map('trim', explode('/', substr($line, 1)))));
                $answer = (string) array_shift($names);
                $accept = $names;
                continue;
            }
            $clues[] = $line;
        }
        return ['answer' => $answer, 'accept' => $accept, 'clues' => $clues];
    }

    /**
     * The text of a sheet with the examples of the place of the mission.
     *
     * @param \stdClass $mission
     * @param array $sheet
     * @return string
     */
    private static function sheet_text(\stdClass $mission, array $sheet): string {
        $text = $sheet['text'] ?? '';
        return is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
    }

    /**
     * A sheet of the two circles from its plain text: the first two groups, and each thing once, in a (only the first
     * group), b (only the second) or ab (both), with its why.
     *
     * @param string $text
     * @return array groups (two names) and items (label, zone, why).
     */
    public static function venn(string $text): array {
        $groups = [];
        $in = [];
        $why = [];
        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if ($line[0] === '?') {
                [$thing, $because] = array_pad(explode(':', substr($line, 1), 2), 2, '');
                $why[\core_text::strtolower(trim($thing))] = trim($because);
                continue;
            }
            [$name, $list] = array_pad(explode(':', $line, 2), 2, '');
            if (count($groups) === 2) {
                continue;
            }
            $groups[] = trim($name);
            foreach (explode(str_contains($list, ';') ? ';' : ',', $list) as $thing) {
                $thing = trim($thing);
                if ($thing !== '') {
                    $in['_' . $thing][count($groups) - 1] = true;
                }
            }
        }
        $items = [];
        foreach ($in as $key => $where) {
            $label = substr($key, 1);
            $zone = count($where) > 1 ? 'ab' : (isset($where[0]) ? 'a' : 'b');
            $items[] = ['label' => $label, 'zone' => $zone, 'why' => $why[\core_text::strtolower($label)] ?? ''];
        }
        return ['groups' => $groups, 'items' => $items];
    }

    /**
     * The sheets of a hunt for mistakes for a step: those that list it, or all of them if none does.
     *
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function sheets_for(array $game, int $step): array {
        $all = array_values(array_filter($game['sheets'] ?? [], fn($s) => !empty($s['id'])));
        $mine = array_values(array_filter($all, fn($s) => in_array($step, array_map('intval', $s['steps'] ?? []), true)));
        return $mine ?: $all;
    }

    /**
     * The blots of ink a step can make in a hunt for mistakes: that of the step, or of the nearest below.
     *
     * @param array $game
     * @param int $step
     * @return int
     */
    private static function misses(array $game, int $step): int {
        return max(1, self::by_step($game['misses'] ?? [], $step, 3));
    }

    /**
     * A number of a game that changes with the step: that of the step, or of the nearest below, or the one given.
     *
     * @param mixed $map Step => number, or a number for all of them.
     * @param int $step
     * @param int $default
     * @return int
     */
    private static function by_step($map, int $step, int $default): int {
        if (!is_array($map)) {
            return is_numeric($map) ? (int) $map : $default;
        }
        for ($s = $step; $s >= 1; $s--) {
            if (isset($map[$s]) || isset($map[(string) $s])) {
                return (int) ($map[$s] ?? $map[(string) $s]);
            }
        }
        return $default;
    }

    /**
     * A sheet cut into its text and its mistakes, from [what it says|right/also right|why].
     *
     * @param string $text
     * @return array Of text, mark (its number, -1 for plain text), wrong, right (accepted) and why.
     */
    public static function parts(string $text): array {
        preg_match_all('/\[([^\]|]*)\|([^\]|]*)(?:\|([^\]]*))?\]/u', $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $parts = [];
        $at = 0;
        foreach ($found as $mark => $m) {
            if ($m[0][1] > $at) {
                $parts[] = ['text' => substr($text, $at, $m[0][1] - $at), 'mark' => -1, 'wrong' => '', 'right' => [], 'why' => ''];
            }
            $parts[] = ['text' => '', 'mark' => $mark, 'wrong' => trim($m[1][0]),
                'right' => array_values(array_filter(array_map('trim', explode('/', $m[2][0])))),
                'why' => trim((string) ($m[3][0] ?? ''))];
            $at = $m[0][1] + strlen($m[0][0]);
        }
        if ($at < strlen($text)) {
            $parts[] = ['text' => substr($text, $at), 'mark' => -1, 'wrong' => '', 'right' => [], 'why' => ''];
        }
        return $parts;
    }

    /**
     * Plays a minigame: counts what the student did, keeps it for the teacher and gives the reward the first time it
     * is won.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $code
     * @param array $events What the student did, as each kind of game counts it.
     * @param int|null $now
     * @param string $playid Made by the device: a play sent again with it counts once.
     * @return array success, caught, missed, goal, tries, first, items, coat, gems.
     */
    public static function play(
        int $courseid,
        int $userid,
        string $code,
        array $events,
        ?int $now = null,
        string $playid = ''
    ): array {
        global $DB;
        $now = $now ?? time();
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        $member = engine::member($char, $courseid, $now);
        if ($member->status === 'paused') {
            throw new \moodle_exception('pausedgame', 'local_oksigeniatesape');
        }
        if ($playid !== '') {
            $done = $DB->get_record(self::T . 'minigame', ['charid' => $char->id, 'playid' => $playid]);
            $kept = $done ? (json_decode((string) $done->detail, true)['result'] ?? null) : null;
            if ($kept) {
                // The same play, sent again because the answer was lost on the way: counted once, and what it gave is
                // shown again.
                return $kept;
            }
        }
        $mission = missions::get($courseid);
        // In Guess who, the being that hid when the play began: the device says after how many plays it began.
        $plays = $DB->count_records(self::T . 'minigame', ['charid' => $char->id, 'courseid' => $courseid, 'game' => $code]);
        $seed = isset($events[0]['n']) ? max(0, min($plays, (int) $events[0]['n'])) : $plays;
        $game = $mission ? self::config(
            $mission,
            $code,
            (int) $member->step,
            (string) $char->role,
            talents::acting($char, $courseid),
            creatures::sniffs($char, $courseid),
            equipment::worn($char, $courseid),
            (int) $char->id,
            $seed
        ) : null;
        if (!$game) {
            throw new \moodle_exception('nominigame', 'local_oksigeniatesape');
        }
        $golden = false;
        $weeks = 0;
        if (($game['type'] ?? '') === 'guesswho') {
            [$caught, $missed, $wrong, $success, $golden] = self::count_guesswho($game, $events);
        } else if (($game['type'] ?? '') === 'terrarium') {
            [$caught, $missed, $wrong, $success, $weeks] = self::count_terrarium($game, $events);
        } else if (($game['type'] ?? '') === 'burst') {
            [$caught, $missed, $wrong, $success] = self::count_burst($game, $events);
        } else if (($game['type'] ?? '') === 'errors') {
            [$caught, $missed, $wrong, $success] = self::count_errors($game, $events);
        } else if (($game['type'] ?? '') === 'whack') {
            [$caught, $missed, $wrong, $success] = self::count_whack($game, $events);
        } else if (($game['type'] ?? '') === 'venn') {
            [$caught, $missed, $wrong, $success] = self::count_venn($game, $events);
        } else if (($game['type'] ?? '') === 'whoami') {
            [$caught, $missed, $wrong, $success] = self::count_whoami($game, $events);
        } else if (($game['type'] ?? '') === 'leaf') {
            [$caught, $missed, $wrong, $success] = self::count_leaf($game, $events);
        } else if (($game['type'] ?? '') === 'chain') {
            [$caught, $missed, $wrong, $success] = self::count_chain($game, $events);
        } else {
            [$caught, $missed, $wrong, $success] = self::count_nest($game, $events);
        }
        $transaction = $DB->start_delegated_transaction();
        $tries = $DB->count_records(self::T . 'minigame', ['charid' => $char->id, 'courseid' => $courseid, 'game' => $code]) + 1;
        $detail = ['step' => (int) $member->step, 'caught' => $caught, 'missed' => $missed, 'wrong' => $wrong] +
            ($golden ? ['golden' => true] : []) + ($weeks ? ['weeks' => $weeks] : []);
        $playrecord = (object) [
            'charid' => $char->id,
            'courseid' => $courseid,
            'cmid' => 0,
            'game' => $code,
            'playid' => $playid !== '' ? $playid : null,
            'success' => $success ? 1 : 0,
            'tries' => $tries,
            'detail' => json_encode($detail),
            'timecreated' => $now,
        ];
        $playrecord->id = $DB->insert_record(self::T . 'minigame', $playrecord);
        \local_oksigeniatesape\event\minigame_completed::create(['context' => games::context($courseid),
            'objectid' => $playrecord->id, 'userid' => $userid, 'other' => ['game' => $code, 'success' => (bool) $success]])
            ->trigger();
        $result = ['success' => $success, 'caught' => $caught, 'missed' => $missed, 'goal' => $game['goal'],
            'tries' => $tries, 'first' => false, 'items' => [], 'coat' => false, 'gems' => 0, 'grew' => false,
            'golden' => false, 'card' => '', 'newcard' => false, 'training' => false];
        $key = ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'game', 'code' => $code];
        $won = $success && $DB->record_exists(self::T . 'found', $key);
        if ($won) {
            // Won again: a gem, a few times a day; then, training, with the streak alone.
            $day = self::day($courseid, $now);
            if (self::again_today($char, $mission, $courseid, $now) < self::TRAINING) {
                $entry = engine::gems($member, self::AGAIN_GEMS, 'minigame', 0, 0, 0, $now);
                if ($entry) {
                    $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
                        'missionid' => $mission->id, 'kind' => 'again', 'code' => $day . ':' . $playrecord->id,
                        'timecreated' => $now]);
                    $result['gems'] = (int) $entry->amount;
                }
            } else {
                $result['training'] = true;
            }
        }
        if ($success && !$won) {
            $DB->insert_record(self::T . 'found', (object) ($key + ['courseid' => $courseid, 'timecreated' => $now]));
            $result['first'] = true;
            $reward = $mission->data['games'][$code]['reward'] ?? [];
            $itemid = empty($reward['item']) ? 0 : missions::item_id((string) $reward['item']);
            if ($itemid && engine::item($member, $itemid, 'minigame', 0, 0, $now)) {
                $result['items'][] = (string) $reward['item'];
            }
            $result['coat'] = !empty($reward['coat']);
            if (!empty($reward['gems']) && engine::gems($member, (int) $reward['gems'], 'minigame', 0, 0, 0, $now)) {
                $result['gems'] = (int) $reward['gems'];
            }
            // The first minigame won makes the creature grow in the middle of the mission (unless the lanterns did).
            $result['grew'] = scenes::grow_mid($char, $member, $mission, $now);
        }
        if (($game['type'] ?? '') === 'guesswho' && $success) {
            // The being found goes to the field guide: golden with few questions. Each play, one being.
            $card = encounters::register($char, $mission, (string) $game['secret'], $golden, $now);
            $result['card'] = (string) (array_column($game['cards'], 'name', 'id')[$game['secret']] ?? '');
            $result['golden'] = $golden;
            $result['newcard'] = $card;
        }
        if ($playid !== '') {
            $DB->set_field(self::T . 'minigame', 'detail', json_encode($detail + ['result' => $result]), ['id' => $playrecord->id]);
        }
        $transaction->allow_commit();
        return $result;
    }

    /**
     * The minigames a student won again today that gave gems, all of them together.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param int $courseid
     * @param int $now
     * @return int
     */
    public static function again_today(\stdClass $char, \stdClass $mission, int $courseid, int $now): int {
        global $DB;
        $where = "charid = ? AND missionid = ? AND kind = 'again' AND " . $DB->sql_like('code', '?');
        return $DB->count_records_select(self::T . 'found', $where, [$char->id, $mission->id,
            $DB->sql_like_escape(self::day($courseid, $now) . ':') . '%']);
    }

    /**
     * Whether a minigame is training for a student today: won already, and the gems of the day for winning again given.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $code
     * @param int|null $now
     * @return bool
     */
    public static function training(\stdClass $char, \stdClass $mission, string $code, ?int $now = null): bool {
        global $DB;
        $now = $now ?? time();
        return $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => 'game',
            'code' => $code]) && self::again_today($char, $mission, (int) $mission->courseid, $now) >= self::TRAINING;
    }

    /**
     * The day, as yyyymmdd in the time zone of the course.
     *
     * @param int $courseid
     * @param int $now
     * @return string
     */
    private static function day(int $courseid, int $now): string {
        return (new \DateTimeImmutable('@' . $now))->setTimezone(engine::timezone($courseid))->format('Ymd');
    }

    /**
     * Counts a play of Defensa del nido: each animal once, with the net thrown at it (none if it reached the nest).
     *
     * @param array $game As the student plays it.
     * @param array $events Each one: a (the animal) and net.
     * @return array [caught, missed, wrong (animal => net thrown), success]
     */
    private static function count_nest(array $game, array $events): array {
        $right = array_column($game['animals'], 'net', 'id');
        $nets = array_column($game['nets'], 'id');
        $seen = [];
        $caught = 0;
        $missed = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $animal = (string) ($event['a'] ?? '');
            $net = (string) ($event['net'] ?? '');
            if (!isset($right[$animal]) || isset($seen[$animal]) || ($net !== '' && !in_array($net, $nets, true))) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$animal] = true;
            $answers[] = $net === $right[$animal];
            if ($net === $right[$animal]) {
                $caught++;
            } else {
                $missed++;
                if ($net !== '') {
                    $wrong[$animal] = $net;
                }
            }
        }
        // The healer takes out of the nest the animal that got in: one more caught, one less missed.
        $forgiven = self::forgiven($game, $answers);
        return [$caught, $missed, $wrong, $caught + $forgiven >= $game['goal'] && $missed - $forgiven < $game['shield']];
    }

    /**
     * Counts a play of Who is alive?: each thing touched once (i, its id). A living thing touched is caught; a thing
     * touched that is not alive is a slip; a living thing not touched got away.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [caught, slips, wrong (id => touched or away), success]
     */
    private static function count_whack(array $game, array $events): array {
        $alive = array_column($game['things'], 'alive', 'id');
        $touched = [];
        $answers = [];
        foreach ($events as $event) {
            $id = (string) ($event['i'] ?? '');
            if (!isset($alive[$id]) || isset($touched[$id])) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $touched[$id] = true;
            $answers[] = (bool) $alive[$id];
        }
        $caught = 0;
        $slips = 0;
        $wrong = [];
        foreach ($alive as $id => $living) {
            if ($living && isset($touched[$id])) {
                $caught++;
            } else if ($living) {
                $wrong[$id] = 'away';
            } else if (isset($touched[$id])) {
                $slips++;
                $wrong[$id] = 'touched';
            }
        }
        return [$caught, $slips, $wrong, $caught >= $game['goal'] && $slips - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of the two circles: each thing of each sheet once (s, the sheet; i, the thing; z, where it went).
     * Won when everything is in its place with fewer slips than allowed.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [right, slips, wrong (sheet:thing => where it went), success]
     */
    private static function count_venn(array $game, array $events): array {
        $zones = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['items'] as $i => $item) {
                $zones[$sheet['id'] . ':' . $i] = $item['zone'];
            }
        }
        $seen = [];
        $right = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $key = ($event['s'] ?? '') . ':' . ($event['i'] ?? '');
            $zone = (string) ($event['z'] ?? '');
            if (!isset($zones[$key]) || isset($seen[$key]) || !in_array($zone, ['a', 'b', 'ab'], true)) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $seen[$key] = true;
            $answers[] = $zone === $zones[$key];
            if ($zone === $zones[$key]) {
                $right++;
            } else {
                $wrong[$key] = $zone;
            }
        }
        $done = count($seen) === count($zones);
        return [$right, count($wrong), $wrong, $done && count($wrong) - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of Who am I?: for each riddle (r), another clue asked for (c) or a guess (g). The first clue shows
     * from the start; a wrong guess shows the next one, and a wrong guess with no clue left lets the shadow get away.
     * Won when every shadow is found with fewer wrong guesses than allowed.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [found, wrong guesses, wrong (riddle => first wrong guess), success]
     */
    private static function count_whoami(array $game, array $events): array {
        $riddles = array_column($game['riddles'], null, 'id');
        $state = [];
        $found = 0;
        $guesses = 0;
        $wrong = [];
        $answers = [];
        foreach ($events as $event) {
            $id = (string) ($event['r'] ?? '');
            $riddle = $riddles[$id] ?? null;
            $st = $state[$id] ?? ['shown' => 1, 'over' => false];
            if (!$riddle || $st['over']) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $clues = count($riddle['clues']);
            if (!empty($event['c'])) {
                if ($st['shown'] >= $clues) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $st['shown']++;
            } else {
                $guess = \core_text::strtolower(trim((string) ($event['g'] ?? '')));
                $answers[] = $guess === \core_text::strtolower($riddle['answer']);
                if ($guess === \core_text::strtolower($riddle['answer'])) {
                    $found++;
                    $st['over'] = true;
                } else {
                    $guesses++;
                    $wrong[$id] = $wrong[$id] ?? (string) ($event['g'] ?? '');
                    if ($st['shown'] >= $clues) {
                        $st['over'] = true;
                    } else {
                        $st['shown']++;
                    }
                }
            }
            $state[$id] = $st;
        }
        return [$found, $guesses, $wrong, $found === count($riddles) && $guesses - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * Counts a play of Guess who: the device says after how many plays it began (n, first), and then each question
     * asked (q), each card the sage or the creature turned over (l) and each name said (g). A question turns over the
     * cards that do not answer as the hidden one; a wrong name turns over its card and counts as a question. Found when
     * the hidden one is named; golden with as many questions as the game says at most. The healer's first slip (a
     * question that turned nothing over, or a wrong name) does not count.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [found, questions, wrong (the first question => first), success, golden]
     */
    private static function count_guesswho(array $game, array $events): array {
        $yes = array_column($game['questions'], 'yes', 'id');
        $secret = (string) $game['secret'];
        $up = array_column($game['cards'], 'id');
        $asked = [];
        $count = 0;
        $slips = 0;
        $looks = 0;
        $found = false;
        $first = '';
        foreach ($events as $i => $event) {
            if ($i === 0 && isset($event['n'])) {
                continue;
            }
            if ($found) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['q'])) {
                $q = (string) $event['q'];
                if (!isset($yes[$q]) || isset($asked[$q])) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $asked[$q] = true;
                $first = $first !== '' ? $first : $q;
                $answer = in_array($secret, $yes[$q], true);
                $left = array_values(array_filter($up, fn($id) => in_array($id, $yes[$q], true) === $answer));
                $slips += count($left) === count($up) ? 1 : 0;
                $up = $left;
                $count++;
                continue;
            }
            $id = (string) ($event['l'] ?? $event['g'] ?? '');
            if (!in_array($id, $up, true)) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['l'])) {
                // A look turns over two cards that are not the hidden one.
                if ($id === $secret || ++$looks > 2 * ((int) ($game['looks'] ?? 0) + (int) ($game['sniffs'] ?? 0))) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $up = array_values(array_diff($up, [$id]));
            } else if ($id === $secret) {
                $found = true;
            } else {
                $up = array_values(array_diff($up, [$id]));
                $count++;
                $slips++;
            }
        }
        $counted = $count - (($game['passive'] ?? '') === 'healer' && $slips ? 1 : 0);
        return [$found ? 1 : 0, $counted, $first !== '' ? [$first => 'first'] : [], $found,
            $found && $counted <= (int) $game['golden']];
    }

    /**
     * Counts a play of a burst: each micro-challenge in its order (i, from 0; a, the option touched, or -1 when the time ran
     * out; for the three things at once, picked, those touched). Three misses end it, and nothing comes after; won when
     * all of them were played with fewer misses.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [right, misses, wrong (kind:index => what), success]
     */
    private static function count_burst(array $game, array $events): array {
        $items = $game['burst'];
        $right = 0;
        $misses = 0;
        $wrong = [];
        $next = 0;
        foreach ($events as $k => $event) {
            if ($k === 0 && isset($event['n'])) {
                continue;
            }
            $i = (int) ($event['i'] ?? -1);
            if ($i !== $next || !isset($items[$i]) || $misses >= (int) $game['misses']) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            $next++;
            $item = $items[$i];
            if ($item['kind'] === 'boss') {
                $picked = array_values(array_unique(array_map('strval', (array) ($event['picked'] ?? []))));
                $should = array_column(array_filter($item['things'], fn($x) => $x['alive']), 'id');
                $ok = !array_diff($picked, $should) && !array_diff($should, $picked);
            } else {
                $ok = (int) ($event['a'] ?? -1) === (int) $item['right'];
            }
            if ($ok) {
                $right++;
            } else {
                $misses++;
                $wrong[$item['kind'] . ':' . $i] = $item['kind'] === 'boss' ? 'boss' : (string) ($item['label'] ?: $item['prompt']);
            }
        }
        return [$right, $misses, $wrong, $next === count($items) && $misses < (int) $game['misses']];
    }

    /**
     * Counts a play of the terrarium: each thing put in or taken out (i, and in: 1 or 0, with as many inside as fit), each
     * week let go by with something inside (w), and each look that shows a thing is not alive (l). Won at the first week
     * with everything inside alive and as many as the goal; nothing comes after it.
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [living inside, things not alive that spent a week inside, wrong (thing => in), success, weeks]
     */
    private static function count_terrarium(array $game, array $events): array {
        $alive = array_column($game['things'], 'alive', 'id');
        $limit = (int) ($game['looks'] ?? 0) + (int) ($game['sniffs'] ?? 0);
        $inside = [];
        $weeks = 0;
        $looks = 0;
        $caught = 0;
        $success = false;
        $wrong = [];
        foreach ($events as $event) {
            if ($success) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['w'])) {
                if (!$inside) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $weeks++;
                $living = 0;
                foreach (array_keys($inside) as $id) {
                    if ($alive[$id]) {
                        $living++;
                    } else {
                        $wrong[$id] = 'in';
                    }
                }
                if ($living === count($inside) && $living >= (int) $game['goal']) {
                    $success = true;
                    $caught = $living;
                }
                continue;
            }
            $id = (string) ($event['i'] ?? $event['l'] ?? '');
            if (!isset($alive[$id])) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['l'])) {
                // A look of the sage or the nose of the creature: a thing that is not alive, said without a week.
                if ($alive[$id] || ++$looks > $limit) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
            } else if (!empty($event['in'])) {
                if (isset($inside[$id]) || count($inside) >= (int) $game['most']) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $inside[$id] = true;
            } else {
                if (!isset($inside[$id])) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                unset($inside[$id]);
            }
        }
        return [$caught, count($wrong), $wrong, $success, $weeks];
    }

    /**
     * Counts a play of a hunt for mistakes: each mistake found once (s, the sheet, and m, its number) and each blot of
     * ink (s and x, a word touched that was right).
     *
     * @param array $game As the student plays it.
     * @param array $events
     * @return array [found, blots, missed (sheet:mark => 0, the mistakes not found), success]
     */
    private static function count_errors(array $game, array $events): array {
        $marks = [];
        foreach ($game['sheets'] as $sheet) {
            foreach ($sheet['parts'] as $part) {
                if ($part['mark'] >= 0) {
                    $marks[$sheet['id'] . ':' . $part['mark']] = true;
                }
            }
        }
        $found = [];
        $blots = 0;
        $answers = [];
        $sheets = array_column($game['sheets'], 'id');
        foreach ($events as $event) {
            $sheet = (string) ($event['s'] ?? '');
            if (!in_array($sheet, $sheets, true)) {
                throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
            }
            if (isset($event['m'])) {
                $key = $sheet . ':' . (int) $event['m'];
                if (!isset($marks[$key]) || isset($found[$key])) {
                    throw new \moodle_exception('invalidgameplay', 'local_oksigeniatesape');
                }
                $found[$key] = true;
                $answers[] = true;
            } else {
                $blots++;
                $answers[] = false;
            }
        }
        $missed = array_map(fn() => 0, array_diff_key($marks, $found));
        return [count($found), $blots, $missed, count($found) >= $game['goal'] && $blots - self::forgiven($game, $answers) < $game['misses']];
    }

    /**
     * The slip the healer gets back: after a streak of right answers, the first slip of the play, once. The client
     * counts it the same way, answer by answer.
     *
     * @param array $game As the student plays it, with its passive.
     * @param bool[] $answers Right or not, in the order they were given.
     * @return int 1 or 0.
     */
    private static function forgiven(array $game, array $answers): int {
        if (($game['passive'] ?? '') !== 'healer') {
            return 0;
        }
        $slipped = false;
        $streak = 0;
        foreach ($answers as $right) {
            if (!$right) {
                $slipped = true;
                $streak = 0;
            } else if (++$streak >= $game['streak'] && $slipped) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * Whether a student can dress their creature: they won a minigame that gives the coat.
     *
     * @param \stdClass $char
     * @param int $courseid
     * @return bool
     */
    public static function has_coat(\stdClass $char, int $courseid): bool {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission) {
            return false;
        }
        foreach ($mission->data['games'] ?? [] as $code => $game) {
            if (
                !empty($game['reward']['coat']) && $DB->record_exists(self::T . 'found', ['charid' => $char->id,
                    'missionid' => $mission->id, 'kind' => 'game', 'code' => $code])
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Dresses the creature of a student with the coat they choose (fur, feathers or scales; empty to take it off).
     *
     * @param int $courseid
     * @param int $userid
     * @param string $coat
     * @return \stdClass The character.
     */
    public static function set_coat(int $courseid, int $userid, string $coat): \stdClass {
        global $DB;
        $char = characters::get($userid);
        if (!$char) {
            throw new \moodle_exception('nocharacter', 'local_oksigeniatesape');
        }
        // With its branch, the creature wears the coat of the branch.
        $allowed = creatures::branch($char) === '' && ($coat === '' || (in_array($coat, self::COATS, true) &&
            self::has_coat($char, $courseid)));
        if (empty($char->creature) || !$allowed) {
            throw new \moodle_exception('nocoat', 'local_oksigeniatesape');
        }
        $traits = json_decode((string) $char->creature, true) ?: [];
        $traits['coat'] = $coat;
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => json_encode($traits),
            'timemodified' => time()]);
        return characters::get($userid);
    }

    /**
     * How the class did in the minigames of the mission, for the teacher: who played, who won, tries and the most
     * common mix-ups (an animal and the net thrown at it; in a hunt for mistakes, the mistakes that slipped by most).
     *
     * @param \stdClass $mission With data.
     * @return array
     */
    public static function results(\stdClass $mission): array {
        global $DB;
        $list = [];
        foreach ($mission->data['games'] ?? [] as $code => $game) {
            $rows = $DB->get_records(self::T . 'minigame', ['courseid' => $mission->courseid, 'game' => $code], 'id');
            $players = [];
            $won = [];
            $counts = [];
            foreach ($rows as $row) {
                $players[$row->charid] = true;
                if ($row->success) {
                    $won[$row->charid] = true;
                }
                foreach ((json_decode((string) $row->detail, true)['wrong'] ?? []) as $animal => $net) {
                    $key = ($game['type'] ?? '') === 'errors' ? (string) $animal : $animal . '|' . $net;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
            }
            arsort($counts);
            $confusions = [];
            foreach (array_slice($counts, 0, self::CONFUSIONS, true) as $key => $count) {
                if (($game['type'] ?? '') === 'errors') {
                    $confusions[] = ['text' => self::mistake($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'venn') {
                    $confusions[] = ['text' => self::misplaced($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'whoami') {
                    $confusions[] = ['text' => self::mistaken($mission, $game, (string) $key), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'chain') {
                    // The round of Eslabón the class gets wrong most, and the order it puts or the answer it gives.
                    [$id, $what] = array_pad(explode('|', (string) $key, 2), 2, '');
                    $round = array_column($game['rounds'] ?? [], null, 'id')[$id] ?? [];
                    $names = array_map(
                        fn($b) => self::variant($mission, $mission->data['things'][$b]['label'] ?? $b),
                        array_filter(explode(',', $what))
                    );
                    $confusions[] = ['text' => get_string('minigame_leaf_wrong', 'local_oksigeniatesape', (object) [
                        'round' => missions::text($round['title'] ?? $id, $mission->skin, $mission->variant),
                        'what' => ($round['kind'] ?? 'order') === 'question' ? $what : implode(' → ', $names)]),
                        'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'leaf') {
                    // The round of the leaf the class gets wrong most, and what it does there.
                    [$id, $what] = array_pad(explode('|', (string) $key, 2), 2, '');
                    $round = array_column($game['rounds'] ?? [], null, 'id')[$id] ?? [];
                    $confusions[] = ['text' => get_string('minigame_leaf_wrong', 'local_oksigeniatesape', (object) [
                        'round' => missions::text($round['title'] ?? $id, $mission->skin, $mission->variant),
                        'what' => preg_match('/^(\d)-(\d)-(\d)$/', $what, $m) ? get_string(
                            'minigame_leaf_recipe',
                            'local_oksigeniatesape',
                            (object) ['light' => $m[1], 'water' => $m[2], 'air' => $m[3]]
                        ) : $what]),
                        'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'guesswho') {
                    // What the class asks first.
                    [$trait, $value] = array_pad(explode('_', explode('|', $key)[0], 2), 2, '');
                    $string = 'guesswho_q_' . $trait . '_' . str_replace('-', '', $value);
                    $question = get_string_manager()->string_exists($string, 'local_oksigeniatesape') ?
                        get_string($string, 'local_oksigeniatesape') : $key;
                    $confusions[] = ['text' => get_string('minigame_guesswho_first', 'local_oksigeniatesape', $question),
                        'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'burst') {
                    // What the class misses most in the bursts: the thing, the being or the question.
                    $what = explode('|', $key, 2)[1] ?? '';
                    $confusions[] = ['text' => $what === 'boss' ? get_string('minigame_burst_boss', 'local_oksigeniatesape') :
                        get_string('minigame_burst_missed', 'local_oksigeniatesape', $what), 'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'terrarium') {
                    // What spent a week in the terrarium without being alive.
                    $id = explode('|', $key)[0];
                    $label = self::variant($mission, $mission->data['things'][$id]['label'] ?? $id);
                    $confusions[] = ['text' => get_string('minigame_terrarium_in', 'local_oksigeniatesape', $label),
                        'count' => $count];
                    continue;
                }
                if (($game['type'] ?? '') === 'whack') {
                    [$id, $how] = explode('|', $key);
                    $label = $game['things'][$id]['label'] ?? $id;
                    $label = is_array($label) ? ($label[$mission->variant] ?? $label['gen'] ?? reset($label)) : $label;
                    $string = $how === 'away' ? 'minigame_letgo' : 'minigame_thoughtalive';
                    $confusions[] = ['text' => get_string($string, 'local_oksigeniatesape', $label), 'count' => $count];
                    continue;
                }
                [$animal, $net] = explode('|', $key);
                $label = $game['animals'][$animal]['label'] ?? $animal;
                $label = is_array($label) ? ($label[$mission->variant] ?? reset($label)) : $label;
                $confusions[] = ['text' => $label . ' → ' . ($game['nets'][$net]['label'] ?? $net), 'count' => $count];
            }
            $list[] = [
                'code' => $code,
                'title' => missions::text($game['title'] ?? $code, $mission->skin),
                'chapter' => (string) self::chapter_of($mission, $code),
                'players' => count($players),
                'won' => count($won),
                'tries' => count($rows),
                'confusions' => $confusions,
            ];
        }
        return $list;
    }

    /**
     * A mistake of a hunt, for the teacher: what the sheet said and what is right.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key sheet:mark
     * @return string
     */
    private static function mistake(\stdClass $mission, array $game, string $key): string {
        [$id, $mark] = array_pad(explode(':', $key, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') !== $id) {
                continue;
            }
            $text = $sheet['text'] ?? '';
            $text = is_array($text) ? (string) ($text[$mission->variant] ?? $text['gen'] ?? reset($text)) : (string) $text;
            foreach (self::parts($text) as $part) {
                if ($part['mark'] === (int) $mark) {
                    return '«' . $part['wrong'] . '» → ' . ($part['right'][0] ?? '');
                }
            }
        }
        return $key;
    }

    /**
     * A shadow of Who am I? taken for another, for the teacher: who it was and what they thought.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key riddle|first wrong guess
     * @return string
     */
    private static function mistaken(\stdClass $mission, array $game, string $key): string {
        [$id, $guess] = array_pad(explode('|', $key, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') === $id) {
                $a = (object) ['answer' => self::riddle(self::sheet_text($mission, $sheet))['answer'], 'guess' => $guess];
                return get_string('minigame_whoami_mix', 'local_oksigeniatesape', $a);
            }
        }
        return $key;
    }

    /**
     * A thing of the two circles put in the wrong place, for the teacher: the thing and where it went.
     *
     * @param \stdClass $mission With data.
     * @param array $game
     * @param string $key sheet:thing|zone
     * @return string
     */
    private static function misplaced(\stdClass $mission, array $game, string $key): string {
        [$where, $zone] = array_pad(explode('|', $key, 2), 2, '');
        [$id, $i] = array_pad(explode(':', $where, 2), 2, '');
        foreach ($game['sheets'] ?? [] as $sheet) {
            if ((string) ($sheet['id'] ?? '') !== $id) {
                continue;
            }
            $venn = self::venn(self::sheet_text($mission, $sheet));
            $item = $venn['items'][(int) $i] ?? null;
            if ($item) {
                $place = $zone === 'ab' ? get_string('minigame_venn_both', 'local_oksigeniatesape') :
                    ($venn['groups'][$zone === 'a' ? 0 : 1] ?? $zone);
                return '«' . $item['label'] . '» → ' . $place;
            }
        }
        return $where;
    }

    /**
     * The chapter of the mission that has a minigame.
     *
     * @param \stdClass $mission With data.
     * @param string $code
     * @return string The code of the chapter, or empty.
     */
    public static function chapter_of(\stdClass $mission, string $code): string {
        foreach ($mission->data['chapters'] ?? [] as $chapter) {
            if (in_array($code, $chapter['games'] ?? [], true)) {
                return (string) $chapter['code'];
            }
        }
        return '';
    }

    /**
     * The level of a game for a step: the one of the step, or the nearest below (or the first).
     *
     * @param array $game
     * @param int $step
     * @return array
     */
    private static function level(array $game, int $step): array {
        for ($s = $step; $s >= 1; $s--) {
            if (isset($game['levels'][(string) $s])) {
                return $game['levels'][(string) $s];
            }
        }
        return reset($game['levels']);
    }

    /**
     * The right net for a group among the nets of a level.
     *
     * @param array $game
     * @param string $group
     * @param array $nets
     * @return string
     */
    private static function net(array $game, string $group, array $nets): string {
        // By traits (the first step): what the body of the group is covered with, if that net is played.
        $cover = (string) ($game['covers'][$group] ?? '');
        if ($cover !== '' && in_array($cover, $nets, true)) {
            return $cover;
        }
        $seen = [];
        while (!in_array($group, $nets, true)) {
            if (isset($seen[$group]) || !isset($game['parents'][$group])) {
                return '';
            }
            $seen[$group] = true;
            $group = (string) $game['parents'][$group];
        }
        return $group;
    }
}
