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
 * - {"n": node, "step": step id} for each step put in an order;
 * - {"n": node, "hint": index} for each hint opened, before answering that node.
 * - {"n": node, "look": id} for each option, item or step looked at with its tool, before answering that node.
 *
 * What a character brings changes what can be done: the items they carry, the roles of their team, the flags other
 * scenes of the mission left (what they did there) and their own role. The protector's shield stops the points lost to
 * the first mistake of a play; the result per objective stays as it was, so that the teacher sees it.
 *
 * The rules are those of the client (and of the format of the scenes): an answer of one option may lead back to the
 * same question, a classify asks for each item until it is in its group (unless retry is none), an order asks for
 * each step until it is the right one.
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

    /** @var array Nodes already answered once, for the evidence of method. */
    private array $methods = [];

    /** @var string[] Item that each tool needs; the lens is always at hand. */
    private const TOOLS = ['lens' => '', 'net' => 'net', 'lantern' => 'lantern'];

    /** @var bool The protector's shield is still up in this play. */
    private bool $shield;

    /** @var bool The shield has stopped a mistake in this play. */
    private bool $shielded = false;

    /** @var array Events not yet used. */
    private array $events = [];

    /** @var int Points. */
    private int $points = 0;

    /** @var array Result per objective: code => [ok, total]. */
    private array $objectives = [];

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
        $this->shield = $role === 'protector';
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
            return $requires['role'] === $this->role || ($this->wholeclass && in_array($requires['role'], $this->teamroles, true));
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
                    $before = $this->objectives;
                    $next = $this->choice($node, $n);
                    $this->method($node, $before);
                    $node = $next;
                    break;
                case 'classify':
                    $before = $this->objectives;
                    $this->classify($node, $n);
                    $this->method($node, $before);
                    $node = $n['next'];
                    break;
                case 'order':
                    $before = $this->objectives;
                    $this->order($node, $n);
                    $this->method($node, $before);
                    $node = $n['next'];
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
     * in a classify.
     *
     * @param string $node
     * @return int
     */
    private function correct_in(string $node): int {
        if (isset($this->placed[$node])) {
            return count($this->placed[$node]);
        }
        $options = [];
        foreach ($this->scene['nodes'][$node]['options'] ?? [] as $option) {
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
            $option = $options[$id];
            $this->points += (int) ($option['points'] ?? 0);
            $correct = $option['correct'] ?? null;
            // The objective of the option, or that of the question when the options do not say.
            $objective = $option['objective'] ?? $n['objective'] ?? null;
            $this->tally($objective, $correct !== false && ($correct === true || ($option['points'] ?? 0) > 0));
            foreach ($option['effects'] ?? [] as $effect) {
                if (isset($effect['extraquestion'])) {
                    $this->extra += (int) $effect['extraquestion'];
                } else {
                    $this->effect($effect);
                }
            }
        }
        $this->picks[$node] = $picked;
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
                    $this->tally($item['objective'] ?? $n['objective'] ?? null, $first);
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
                    $saved = $this->guard();
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
                    $saved = $this->guard();
                }
                $first = false;
            }
        }
        $this->points += $first || $saved ? 6 : 3;
        $this->tally($n['objective'] ?? null, $first);
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
        while (is_array($event) && (isset($event['hint']) || isset($event['look']))) {
            if (isset($event['look'])) {
                $this->look($node, $event);
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
     * A mistake: the protector's shield stops the points it would cost, once a play.
     *
     * @return bool The shield stopped it.
     */
    private function guard(): bool {
        if (!$this->shield) {
            return false;
        }
        $this->shield = false;
        $this->shielded = true;
        return true;
    }

    /**
     * Counts an answer for an objective.
     *
     * @param string|null $objective
     * @param bool $ok
     */
    private function tally(?string $objective, bool $ok): void {
        if (!$objective) {
            return;
        }
        $this->objectives[$objective] = $this->objectives[$objective] ?? ['ok' => 0, 'total' => 0];
        $this->objectives[$objective]['total']++;
        if ($ok) {
            $this->objectives[$objective]['ok']++;
        }
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
