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
 * Plays a decision scene again on the server with what the student did in the client, so that the path, the points,
 * the result per objective and the ending are decided here and not by the browser.
 *
 * What the student did is a list of events, in order:
 * - {"n": node, "pick": [option ids]} for each answer to a choice;
 * - {"n": node, "item": item id, "bin": bin id} for each try to put an item of a classify in a group;
 * - {"n": node, "step": step id} for each step put in an order, or each place a path goes on to;
 * - {"n": node, "give": [item ids]} for each round of a tend (things given to a being, and «days go by»);
 * - {"n": node, "hint": index} for each hint opened, before answering that node.
 * - {"n": node, "look": id} for each option, item or step looked at with its tool, before answering that node.
 * - {"n": node, "sniff": 1} when the creature of the student sniffs a choice before it is answered (see sniffs()).
 *
 * What a character brings changes what can be done: the items they carry, the roles of their team, the flags other
 * scenes of the mission left (what they did there) and their own role. The protector's shield stops the points lost to
 * the first mistake of a play; the result per objective stays as it was, so that the teacher sees it.
 *
 * The rules are those of the client (and of the format of the scenes): an answer of one option may lead back to the
 * same question, a classify asks for each item until it is in its group (unless retry is none), an order or a path
 * asks for each step until it is the right one, and a tend goes on after a good state, the last round or when the
 * student goes on.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scene_runner {
    /** @var int Most nodes visited in one play, against endless loops. */
    private const MAX_VISITS = 500;

    /** @var array The scene. */
    private array $scene;

    /** @var string[] Codes of the items the student carries. */
    private array $items;

    /** @var string[] Roles in the student's team. */
    private array $teamroles;

    /** @var string[] Flags of the mission the character carries, and those set in this play. */
    private array $flags;

    /** @var string The student's own role. */
    private string $role;

    /** @var bool Played by the whole class at the class screen. */
    private bool $wholeclass = false;

    /** @var array Hints opened: node => [index => true]. */
    private array $hints = [];

    /** @var bool Played with the aids of the first steps. */
    private bool $aided = false;

    /** @var array Node => [option, item or step looked at => true]. */
    private array $looks = [];

    /** @var array Node => the option the creature struck out sniffing: once a play. */
    private array $sniffed = [];

    /** @var array Nodes already answered once, for the evidence of method. */
    private array $methods = [];

    /** @var string[] Item that each tool needs; the lens is always at hand. */
    private const TOOLS = ['lens' => '', 'net' => 'net', 'lantern' => 'lantern'];

    /** @var int Mistakes the shield still stops in this play: one for the protector (two with «Long shield»). */
    private int $shields;

    /** @var int[] With «Team guard»: who decides in a play as a team, each with their shield for their first mistake. */
    private array $guards = [];

    /** @var string[] The roles teammates lend to the play («Shared trail»). */
    private array $lent = [];

    /** @var bool The shield has stopped a mistake in this play. */
    private bool $shielded = false;

    /** @var array Events not yet used. */
    private array $events = [];

    /** @var int Points. */
    private int $points = 0;

    /**
     * @var array Result per objective: code => [ok, total], and how each answer came (see HOW): what the evidence tells
     * beyond right or wrong.
     */
    private array $objectives = [];

    /**
     * @var string[] How an answer came: right at the first try and alone, right with help (the lens, a hint or the
     * creature), right after seeing the consequence of a mistake, and the same mistake again.
     */
    public const HOW = ['first', 'helped', 'after', 'twice'];

    /** @var array Choices already answered once in this play: node => true. */
    private array $answered = [];

    /** @var array Wrong options already chosen in each choice: node => option ids. */
    private array $mistaken = [];

    /** @var array Last answer to each choice: node => option ids. */
    private array $picks = [];

    /** @var array Items put right at the first try in each classify: node => item ids. */
    private array $placed = [];

    /** @var array Effects of the options chosen and the items placed. */
    private array $inline = [];

    /** @var int Extra answers won. */
    private int $extra = 0;

    /** @var array Nodes where an extra answer was used. */
    private array $extraused = [];

    /**
     * Prepares a play.
     *
     * @param array $scene The scene, as in its JSON.
     * @param string[] $items Codes of the items the student carries in the course.
     * @param string[] $teamroles Roles of the characters in the student's team.
     * @param string[] $flags Flags of the mission the character carries (what they did in other scenes).
     * @param string $role The student's own role.
     * @param bool $wholeclass Played by the whole class at the class screen: it has the role of any of its players.
     */
    public function __construct(
        array $scene,
        array $items = [],
        array $teamroles = [],
        array $flags = [],
        string $role = '',
        bool $wholeclass = false
    ) {
        $this->scene = $scene;
        $this->items = $items;
        $this->teamroles = $teamroles;
        $this->flags = $flags;
        $this->role = $role;
        $this->wholeclass = $wholeclass;
        $this->shields = $role === 'protector' ? 1 : 0;
    }

    /**
     * The shield of this play, by the talents: two mistakes with «Long shield».
     *
     * @param int $shields
     * @return self
     */
    public function shields(int $shields): self {
        $this->shields = max($this->shields, $shields);
        return $this;
    }

    /**
     * «Team guard»: a protector of the team in the play guards the first mistake of each one who decides.
     *
     * @param int[] $userids Who plays.
     * @return self
     */
    public function team_guard(array $userids): self {
        $this->guards = array_fill_keys(array_map('intval', $userids), 1);
        return $this;
    }

    /**
     * Roles that teammates in the play lend to it: their options are there, and they decide them.
     *
     * @param string[] $roles
     * @return self
     */
    public function lend(array $roles): self {
        $this->lent = array_values(array_unique($roles));
        return $this;
    }

    /**
     * A play with the aids of the first steps: a core scene played by who is on the first step, without the hard items.
     *
     * @param bool $aided
     * @return self
     */
    public function aided(bool $aided = true): self {
        $this->aided = $aided;
        return $this;
    }

    /**
     * Whether a requirement is met.
     *
     * @param array|null $requires {item: code}, {teamrole: role}, {role: own role}, {flag: code} or {any: [...]}.
     * @return bool
     */
    public function has(?array $requires): bool {
        if (!$requires) {
            return true;
        }
        if (isset($requires['item'])) {
            return in_array($requires['item'], $this->items, true);
        }
        if (isset($requires['flag'])) {
            return in_array($requires['flag'], $this->flags, true);
        }
        if (isset($requires['role'])) {
            // The whole class, at the class screen, has the role of any of its players.
            return $requires['role'] === $this->role || in_array($requires['role'], $this->lent, true)
                || ($this->wholeclass && in_array($requires['role'], $this->teamroles, true));
        }
        if (isset($requires['teamrole'])) {
            return in_array($requires['teamrole'], $this->teamroles, true);
        }
        if (isset($requires['any']) && is_array($requires['any'])) {
            foreach ($requires['any'] as $one) {
                if (is_array($one) && $this->has($one)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * The nodes where a play can start: the start of the scene and those where a retry ending sends back.
     *
     * @return string[]
     */
    public function starts(): array {
        $starts = [$this->scene['start']];
        foreach ($this->scene['nodes'] as $node) {
            if (($node['type'] ?? '') === 'ending' && !empty($node['retry'])) {
                $starts[] = $node['retry'];
            }
        }
        return array_values(array_unique($starts));
    }

    /**
     * Plays the scene with the events of the student.
     *
     * @param array $events
     * @param string|null $from Node to start at (the start, or where a retry ending sent back).
     * @return array ending, outcome, effects (of the ending), inline (effects won on the way), points, objectives, path.
     */
    public function run(array $events, ?string $from = null): array {
        $nodes = $this->scene['nodes'];
        $node = $from ?? $this->scene['start'];
        if (!in_array($node, $this->starts(), true)) {
            $this->fail('cannot start at ' . $node);
        }
        $this->events = array_values($events);
        $path = [];
        for ($visits = 0; $visits < self::MAX_VISITS; $visits++) {
            if (!isset($nodes[$node])) {
                $this->fail('unknown node ' . $node);
            }
            $n = $nodes[$node];
            $path[] = $node;
            switch ($n['type']) {
                case 'story':
                    $node = $n['next'];
                    break;
                case 'branch':
                    $node = $this->branch($n);
                    break;
                case 'choice':
                case 'use':
                case 'justify':
                    // Using something from the backpack and saying why are choices, told otherwise.
                    $before = $this->objectives;
                    $next = $this->choice($node, $this->as_choice($n));
                    $this->method($node, $before);
                    $node = $next;
                    break;
                case 'find':
                    $before = $this->objectives;
                    $this->find($node, $n);
                    $this->method($node, $before);
                    $node = $n['next'];
                    break;
                case 'classify':
                    $before = $this->objectives;
                    $this->classify($node, $n);
                    $this->method($node, $before);
                    $node = $n['next'];
                    break;
                case 'order':
                case 'path':
                    // A path is an order told as someone who goes along it on a picture.
                    $before = $this->objectives;
                    $this->order($node, $n);
                    $this->method($node, $before);
                    $node = $n['next'];
                    break;
                case 'tend':
                    $before = $this->objectives;
                    $next = $this->tend($node, $n);
                    $this->method($node, $before);
                    $node = $next;
                    break;
                case 'ending':
                    if ($this->events) {
                        $this->fail('events left after the ending');
                    }
                    return [
                        'ending' => $node,
                        'outcome' => $n['outcome'] ?? 'success',
                        'effects' => $n['effects'] ?? [],
                        'inline' => $this->inline,
                        'retry' => $n['retry'] ?? null,
                        'points' => $this->points,
                        'objectives' => $this->objectives,
                        'path' => $path,
                        // Looking with a tool costs as a hint does.
                        'hints' => array_sum(array_map('count', $this->hints)) + array_sum(array_map('count', $this->looks)),
                        'shielded' => $this->shielded,
                        // The creature sniffing is free: it is not a hint.
                        'sniffed' => count($this->sniffed),
                    ];
                default:
                    $this->fail('unknown type ' . $n['type']);
            }
        }
        $this->fail('too long');
    }

    /**
     * A branch: the first rule that holds, or else.
     *
     * @param array $n
     * @return string Next node.
     */
    private function branch(array $n): string {
        foreach ($n['rules'] ?? [] as $rule) {
            $if = $rule['if'] ?? [];
            if (isset($if['flag'])) {
                if (in_array($if['flag'], $this->flags, true)) {
                    return $rule['next'];
                }
                continue;
            }
            if (isset($if['correct_at_least'], $if['in'])) {
                // One node, or several (the right answers of all of them are added up).
                $count = 0;
                foreach ((array) $if['in'] as $in) {
                    $count += $this->correct_in((string) $in);
                }
                if ($count >= (int) $if['correct_at_least']) {
                    return $rule['next'];
                }
            }
        }
        return $n['else'];
    }

    /**
     * How many right answers there were in a node: options marked correct in a choice, items placed at the first try
     * in a classify, things marked correct given in the last round of a tend.
     *
     * @param string $node
     * @return int
     */
    private function correct_in(string $node): int {
        if (isset($this->placed[$node])) {
            return count($this->placed[$node]);
        }
        $options = [];
        $n = $this->scene['nodes'][$node] ?? [];
        $list = ['tend' => 'items', 'find' => 'spots'][$n['type'] ?? ''] ?? 'options';
        foreach ($n[$list] ?? [] as $option) {
            $options[$option['id']] = $option;
        }
        $count = 0;
        foreach ($this->picks[$node] ?? [] as $id) {
            if (!empty($options[$id]['correct'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * A choice: one answer (one event).
     *
     * @param string $node
     * @param array $n
     * @return string Next node (the same one if the answer leads back to the question).
     */
    private function choice(string $node, array $n): string {
        $event = $this->take($node);
        if (!isset($event['pick']) || !is_array($event['pick'])) {
            $this->fail('a choice needs pick');
        }
        $options = [];
        foreach ($n['options'] as $option) {
            $options[$option['id']] = $option;
        }
        $picked = array_values(array_unique(array_map('strval', $event['pick'])));
        $max = (int) ($n['select']['max'] ?? 1);
        $min = (int) ($n['select']['min'] ?? $max);
        if (count($picked) < $min || count($picked) > $max || count($picked) != count($event['pick'])) {
            $this->fail('wrong number of options in ' . $node);
        }
        foreach ($picked as $id) {
            if (!isset($options[$id]) || !$this->has($options[$id]['requires'] ?? null)) {
                $this->fail('option not available: ' . $id);
            }
            if (($this->sniffed[$node] ?? null) === $id) {
                $this->fail('the creature struck out ' . $id);
            }
            $option = $options[$id];
            $this->points += (int) ($option['points'] ?? 0);
            $correct = $option['correct'] ?? null;
            // The objective of the option, or that of the question when the options do not say. A dilemma has no right
            // answer: it is a decision of the story, not evidence of any objective.
            $objective = $option['objective'] ?? $n['objective'] ?? null;
            if (empty($n['dilemma'])) {
                $ok = $correct !== false && ($correct === true || ($option['points'] ?? 0) > 0);
                $how = $ok ? (empty($this->answered[$node]) ? ($this->helped($node) ? 'helped' : 'first') : 'after') :
                    (in_array($id, $this->mistaken[$node] ?? [], true) ? 'twice' : '');
                $this->tally($objective, $ok, $how);
                if (!$ok) {
                    $this->mistaken[$node][] = $id;
                }
                if ($objective && isset($this->sniffed[$node])) {
                    // Answered after the creature sniffed: the evidence says so, so that it is not read as a first try.
                    $this->objectives[$objective]['sniffed'] = ($this->objectives[$objective]['sniffed'] ?? 0) + 1;
                }
            }
            foreach ($option['effects'] ?? [] as $effect) {
                if (isset($effect['extraquestion'])) {
                    $this->extra += (int) $effect['extraquestion'];
                } else {
                    $this->effect($effect);
                }
            }
        }
        $this->picks[$node] = $picked;
        $this->answered[$node] = true;
        if ($max > 1) {
            return $n['next'];
        }
        $target = $options[$picked[0]]['next'] ?? $n['next'];
        if ($target === $node) {
            return $node;
        }
        if ($this->extra > 0 && !empty($n['extra']) && empty($this->extraused[$node])) {
            $this->extra--;
            $this->extraused[$node] = true;
            return $node;
        }
        return $target;
    }

    /**
     * A use or a justify, as the choice it is. Using: each option is a thing of the backpack (`item`), there only if it
     * is carried; an option without a thing is always there. Justifying: the reasons for what was decided in `about`
     * (those with `for` only if one of those options was picked), two to mark by default, and evidence of the objective
     * of method of the mission if the node does not name another.
     *
     * @param array $n
     * @param string[]|null $picked For a justify, what was picked in `about` (null: as this play went).
     * @return array The node, as a choice.
     */
    public function as_choice(array $n, ?array $picked = null): array {
        if (($n['type'] ?? '') === 'use') {
            $n['options'] = array_map(fn($o) => isset($o['item']) && !isset($o['requires']) ?
                $o + ['requires' => ['item' => (string) $o['item']]] : $o, $n['options'] ?? []);
        } else if (($n['type'] ?? '') === 'justify') {
            $picked = $picked ?? $this->picks[$n['about'] ?? ''] ?? [];
            $n['options'] = array_values(array_filter($n['options'] ?? [], fn($o) => empty($o['for']) ||
                array_intersect((array) $o['for'], $picked)));
            $n['select'] = $n['select'] ?? ['min' => 2, 'max' => 2];
            $n['objective'] = $n['objective'] ?? $this->scene['method'] ?? null;
        }
        return $n;
    }

    /**
     * A find: the things to find on a picture, touched one by one; touching something that is not one of them says
     * why, once each. It counts as an answer: at the first try if nothing wrong was touched.
     *
     * @param string $node
     * @param array $n
     */
    private function find(string $node, array $n): void {
        $spots = [];
        foreach ($n['spots'] as $spot) {
            $spots[(string) $spot['id']] = $spot;
        }
        $wanted = array_keys(array_filter($spots, fn($s) => !empty($s['correct'])));
        $need = min(count($wanted), (int) ($n['find'] ?? count($wanted)));
        $found = [];
        $first = true;
        $saved = false;
        while (count($found) < $need) {
            $event = $this->take($node);
            $id = (string) ($event['spot'] ?? '');
            if (!isset($spots[$id]) || in_array($id, $found, true) || in_array($id, $this->mistaken[$node] ?? [], true)) {
                $this->fail('spot not available: ' . $id);
            }
            if (!empty($spots[$id]['correct'])) {
                $found[] = $id;
                foreach ($spots[$id]['effects'] ?? [] as $effect) {
                    $this->effect($effect);
                }
                continue;
            }
            $this->mistaken[$node][] = $id;
            if ($first) {
                $saved = $this->guard($event);
            }
            $first = false;
        }
        $this->picks[$node] = $found;
        $this->points += $first || $saved ? 6 : 3;
        $this->tally($n['objective'] ?? null, $first, $first ? ($this->helped($node) ? 'helped' : 'first') : 'after');
    }

    /**
     * A classify: each item available, until it is in its group.
     *
     * @param string $node
     * @param array $n
     */
    private function classify(string $node, array $n): void {
        $bins = array_map(fn($b) => $b['id'], $n['bins']);
        $once = ($n['retry'] ?? 'item') === 'none';
        $this->placed[$node] = [];
        // All the placements of the node, each item with its own in order: one after the other, or all at once and
        // then a second trip with those that were not in their place.
        $byitem = [];
        while ($this->events && ($this->events[0]['n'] ?? null) === $node) {
            $event = $this->take($node);
            if (!isset($event['item']) || !in_array($event['bin'] ?? null, $bins, true)) {
                $this->fail('expected an item in a group of ' . $node);
            }
            $byitem[(string) $event['item']][] = $event;
        }
        $known = [];
        foreach ($n['items'] as $item) {
            $known[(string) $item['id']] = true;
            if (!$this->has($item['requires'] ?? null) || ($this->aided && !empty($item['hard']))) {
                // Locked, or left out by the aids: they could not try it, so it does not count against the objective.
                continue;
            }
            $first = true;
            $saved = false;
            $wrong = [];
            $mine = $byitem[(string) $item['id']] ?? [];
            // Tapping the same wrong group again does not count: each wrong group once, then the right one.
            for ($tries = 0; $tries <= 4 * count($bins); $tries++) {
                $event = array_shift($mine);
                if (!$event) {
                    $this->fail('expected ' . $item['id'] . ' in a group of ' . $node);
                }
                if ($event['bin'] !== $item['bin'] && !$once && in_array($event['bin'], $wrong, true)) {
                    continue;
                }
                if ($event['bin'] === $item['bin']) {
                    $this->points += $first || $saved ? 2 : 1;
                    $how = $first ? ($this->helped($node, (string) $item['id']) ? 'helped' : 'first') : 'after';
                    $this->tally($item['objective'] ?? $n['objective'] ?? null, $first, $how);
                    if ($first) {
                        $this->placed[$node][] = $item['id'];
                    }
                    foreach ($item['effects'] ?? [] as $effect) {
                        $this->effect($effect);
                    }
                    continue 2;
                }
                if ($once) {
                    $this->tally($item['objective'] ?? $n['objective'] ?? null, false);
                    continue 2;
                }
                $wrong[] = $event['bin'];
                if ($first) {
                    $saved = $this->guard($event);
                }
                $first = false;
            }
            $this->fail('too many tries for ' . $item['id']);
        }
        if (array_diff_key($byitem, $known)) {
            $this->fail('an item that is not in ' . $node);
        }
    }

    /**
     * Tending a being: the student gives it some of the things at hand and days go by; what they gave makes a state of
     * the being, the first of its states whose condition holds. They may try another round, changing something, up to
     * the rounds of the node; it goes on after a good state, after the last round, or when they go on. Each round counts
     * as an answer: right the first time, or after the consequence.
     *
     * @param string $node
     * @param array $n
     * @return string Next node.
     */
    private function tend(string $node, array $n): string {
        $items = [];
        foreach ($n['items'] ?? [] as $item) {
            $items[(string) $item['id']] = $item;
        }
        $give = max(1, (int) ($n['give'] ?? 1));
        $rounds = max(1, (int) ($n['rounds'] ?? 1));
        $seen = [];
        for ($round = 0; $round < $rounds; $round++) {
            if ($round > 0 && !$this->more($node)) {
                break;
            }
            $event = $this->take($node);
            $raw = (array) ($event['give'] ?? []);
            $given = array_values(array_unique(array_map('strval', $raw)));
            if (count($given) !== $give || count($given) !== count($raw)) {
                $this->fail('wrong number of things given in ' . $node);
            }
            foreach ($given as $id) {
                if (!isset($items[$id]) || !$this->has($items[$id]['requires'] ?? null)) {
                    $this->fail('thing not available: ' . $id);
                }
            }
            $state = self::tend_state($n, $given);
            if (!$state) {
                $this->fail('no state of ' . $node . ' for what was given');
            }
            $ok = !empty($state['correct']);
            $this->picks[$node] = $given;
            sort($given);
            $again = in_array(implode(',', $given), $seen, true);
            $seen[] = implode(',', $given);
            $how = $ok ? ($round === 0 ? ($this->helped($node) ? 'helped' : 'first') : 'after') : ($again ? 'twice' : '');
            $this->tally($state['objective'] ?? $n['objective'] ?? null, $ok, $how);
            $this->points += $round === 0 ? (int) ($state['points'] ?? ($ok ? 3 : 0)) : 0;
            foreach ($state['effects'] ?? [] as $effect) {
                $this->effect($effect);
            }
            if ($ok) {
                return $state['next'] ?? $n['next'];
            }
        }
        return $n['next'];
    }

    /**
     * The state of a being tended with some things: the first whose condition holds (all of them given, any of them
     * given, none of them given); one without a condition holds always.
     *
     * @param array $n The tend node.
     * @param string[] $given
     * @return array|null
     */
    public static function tend_state(array $n, array $given): ?array {
        foreach ($n['states'] ?? [] as $state) {
            $when = $state['when'] ?? [];
            $any = array_map('strval', (array) ($when['any'] ?? []));
            if (
                array_diff(array_map('strval', (array) ($when['all'] ?? [])), $given)
                || ($any && !array_intersect($any, $given))
                || array_intersect(array_map('strval', (array) ($when['none'] ?? [])), $given)
            ) {
                continue;
            }
            return $state;
        }
        return null;
    }

    /**
     * Whether the next event is for this node (hints, looks and sniffs of it included).
     *
     * @param string $node
     * @return bool
     */
    private function more(string $node): bool {
        return isset($this->events[0]) && is_array($this->events[0]) && ($this->events[0]['n'] ?? null) === $node;
    }

    /**
     * An order: each step until it is the one that comes.
     *
     * @param string $node
     * @param array $n
     */
    private function order(string $node, array $n): void {
        $steps = array_map(fn($s) => $s['id'], $n['steps']);
        $built = 0;
        $first = true;
        $saved = false;
        $tries = 0;
        while ($built < count($steps)) {
            if (++$tries > count($steps) * count($steps)) {
                $this->fail('too many tries in ' . $node);
            }
            $event = $this->take($node);
            $step = $event['step'] ?? null;
            if (!in_array($step, array_slice($steps, $built), true)) {
                $this->fail('step not available: ' . $step);
            }
            if ($step === $steps[$built]) {
                $built++;
            } else {
                if ($first) {
                    $saved = $this->guard($event);
                }
                $first = false;
            }
        }
        $this->points += $first || $saved ? 6 : 3;
        $this->tally($n['objective'] ?? null, $first, $first ? ($this->helped($node) ? 'helped' : 'first') : 'after');
    }

    /**
     * The next event, which has to be for this node.
     *
     * @param string $node
     * @return array
     */
    private function take(string $node): array {
        $event = array_shift($this->events);
        // The hints opened and what was looked at before answering: each one of the node, once.
        while (is_array($event) && (isset($event['hint']) || isset($event['look']) || isset($event['sniff']))) {
            if (isset($event['look'])) {
                $this->look($node, $event);
                $event = array_shift($this->events);
                continue;
            }
            if (isset($event['sniff'])) {
                $this->sniff($node, $event);
                $event = array_shift($this->events);
                continue;
            }
            $hints = $this->scene['nodes'][$node]['hints'] ?? [];
            $index = (int) $event['hint'];
            if (($event['n'] ?? null) !== $node || !isset($hints[$index]) || isset($this->hints[$node][$index])) {
                $this->fail('hint not available in ' . $node);
            }
            $this->hints[$node][$index] = true;
            $event = array_shift($this->events);
        }
        if (!is_array($event) || ($event['n'] ?? null) !== $node) {
            $this->fail('expected an event for ' . $node);
        }
        return $event;
    }

    /**
     * Looking at an option, an item or a step of the node with its tool (the lens, or the net or the lantern if they are
     * carried): once each.
     *
     * @param string $node
     * @param array $event
     */
    private function look(string $node, array $event): void {
        $n = $this->scene['nodes'][$node];
        $id = (string) $event['look'];
        $found = null;
        foreach (array_merge($n['options'] ?? [], $n['items'] ?? [], $n['steps'] ?? []) as $thing) {
            if ((string) ($thing['id'] ?? '') === $id && !empty($thing['look'])) {
                $found = $thing;
            }
        }
        $tool = is_array($found['look'] ?? null) ? (string) ($found['look']['tool'] ?? 'lens') : 'lens';
        $needs = self::TOOLS[$tool] ?? null;
        $carried = $needs === '' || ($needs !== null && $this->has(['item' => $needs]));
        if (($event['n'] ?? null) !== $node || !$found || !$carried || isset($this->looks[$node][$id])) {
            $this->fail('nothing to look at in ' . $node);
        }
        $this->looks[$node][$id] = true;
    }

    /**
     * The creature of the student sniffs a choice before it is answered: it strikes out one option that is clearly not
     * it. Once a play, only with the creature hatched, and only where the scene lets it.
     *
     * @param string $node
     * @param array $event
     */
    private function sniff(string $node, array $event): void {
        $struck = ($this->scene['sniff'] ?? true) ? self::sniffs($this->scene['nodes'][$node]) : null;
        if (($event['n'] ?? null) !== $node || $struck === null || $this->sniffed || !$this->has(['item' => 'creature'])) {
            $this->fail('nothing to sniff in ' . $node);
        }
        $this->sniffed[$node] = $struck;
    }

    /**
     * The option the creature strikes out in a choice: the first one the scene marks as clearly not it (obvious), or
     * else the last that is not right. Only in a question of one answer with three options or more that has a right one;
     * never in a decision without a right answer. The client strikes out the same one.
     *
     * @param array $n The node.
     * @return string|null The option, or null if the creature cannot sniff here.
     */
    public static function sniffs(array $n): ?string {
        $options = $n['options'] ?? [];
        $one = ($n['type'] ?? '') === 'choice' && empty($n['dilemma']) && (int) ($n['select']['max'] ?? 1) === 1;
        if (!$one || count($options) < 3) {
            return null;
        }
        $wrong = array_values(array_filter($options, fn($o) => ($o['correct'] ?? null) === false && empty($o['requires'])));
        if (!$wrong) {
            return null;
        }
        $obvious = array_values(array_filter($wrong, fn($o) => !empty($o['obvious'])));
        return (string) ($obvious ? $obvious[0]['id'] : end($wrong)['id']);
    }

    /**
     * Looking before deciding, and then deciding well at the first try, is working as a scientist does: evidence for the
     * objective of method of the scene (`method`), once for each node.
     *
     * @param string $node
     * @param array $before The objectives before the node.
     */
    private function method(string $node, array $before): void {
        $objective = $this->scene['method'] ?? null;
        // Only the first time the node is answered: what comes back to it is not a first try.
        if (!$objective || isset($this->methods[$node])) {
            return;
        }
        $this->methods[$node] = false;
        if (empty($this->looks[$node])) {
            return;
        }
        $ok = 0;
        $total = 0;
        foreach ($this->objectives as $code => $o) {
            $ok += $o['ok'] - ($before[$code]['ok'] ?? 0);
            $total += $o['total'] - ($before[$code]['total'] ?? 0);
        }
        if ($total > 0 && $ok === $total) {
            $this->tally((string) $objective, true);
        }
    }

    /**
     * An effect won on the way: a flag counts at once for the rest of the play.
     *
     * @param array $effect
     */
    private function effect(array $effect): void {
        if (isset($effect['unflag'])) {
            $this->flags = array_values(array_diff($this->flags, [(string) $effect['unflag']]));
        }
        if (isset($effect['flag'])) {
            $this->flags[] = (string) $effect['flag'];
        }
        $this->inline[] = $effect;
    }

    /**
     * A mistake: the protector's shield stops the points it would cost, once a play (twice with «Long shield»); with
     * «Team guard», the first mistake of each one who decides.
     *
     * @param array $event The event of the mistake.
     * @return bool The shield stopped it.
     */
    private function guard(array $event): bool {
        $by = (int) ($event['by'] ?? 0);
        if ($by && !empty($this->guards[$by])) {
            $this->guards[$by] = 0;
        } else if ($this->shields > 0) {
            $this->shields--;
        } else {
            return false;
        }
        $this->shielded = true;
        return true;
    }

    /**
     * Counts an answer for an objective, and how it came.
     *
     * @param string|null $objective
     * @param bool $ok
     * @param string $how One of HOW, or empty.
     */
    private function tally(?string $objective, bool $ok, string $how = ''): void {
        if (!$objective) {
            return;
        }
        $this->objectives[$objective] = $this->objectives[$objective] ?? ['ok' => 0, 'total' => 0];
        $this->objectives[$objective]['total']++;
        if ($ok) {
            $this->objectives[$objective]['ok']++;
        }
        if ($how !== '') {
            $this->objectives[$objective][$how] = ($this->objectives[$objective][$how] ?? 0) + 1;
        }
    }

    /**
     * Whether the student had help in a node before answering: a hint, the creature sniffing, or a look with their
     * tool (at that item, if one is said; at anything of the node, if not).
     *
     * @param string $node
     * @param string $thing
     * @return bool
     */
    private function helped(string $node, string $thing = ''): bool {
        return !empty($this->hints[$node]) || isset($this->sniffed[$node]) ||
            ($thing !== '' ? isset($this->looks[$node][$thing]) : !empty($this->looks[$node]));
    }

    /**
     * Stops: what the client sent does not fit the scene.
     *
     * @param string $why
     */
    private function fail(string $why): never {
        throw new \moodle_exception('invalidscenepath', 'local_oksigeniatesape', '', null, $why);
    }
}
