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
 * Encounters with the beings of the valley: in each place, once a day for each student, a being of that place they do
 * not have yet peeks out (chosen by the day, not by chance alone). Their creature sniffs it and tells its group; it is
 * recognised with three clues in the first person, choosing its name among four. The encounter is never lost: a wrong
 * name is crossed out and the being flees to another bush, to be looked for again the same day, until only its own name
 * is left. With one clue and no miss, a golden card and twice the gems; with no miss, a card and its gems; after a miss,
 * a card (with «Fresh track», the first miss keeps the gems). Each being is registered once, in the field guide of the
 * student, never with saber. Some only peek out with an item or a role in the team.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class encounters {
    /** @var string Prefix of the tables of the plugin. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] The groups of the beings. */
    public const GROUPS = ['mamifero', 'ave', 'reptil', 'anfibio', 'pez', 'insecto', 'aracnido', 'molusco', 'gusano', 'planta',
        'hongo'];

    /** @var int Gems for a card; a golden one (with one clue), twice. */
    public const GEMS = 1;

    /**
     * The being that peeks out in a place for a student today: none if they met one there today already, if they have
     * every being of the place, or if the mission has no encounters.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $place Code of the place.
     * @param int|null $now
     * @return array|null The being, as in the mission.
     */
    public static function today(\stdClass $char, \stdClass $mission, string $place, ?int $now = null): ?array {
        global $DB;
        $day = self::day((int) $mission->courseid, $now);
        if (self::found($char, $mission, 'met', $place . '-' . $day)) {
            return null;
        }
        $where = "charid = ? AND missionid = ? AND kind = 'being'";
        $registered = $DB->get_fieldset_select(self::T . 'found', 'code', $where, [$char->id, $mission->id]);
        [$items, $roles] = scenes::context($char, (int) $mission->courseid);
        $candidates = [];
        foreach ($mission->data['encounters'] ?? [] as $b) {
            $needs = $b['requires'] ?? [];
            if (
                ($b['place'] ?? '') === $place && !in_array((string) ($b['id'] ?? ''), $registered, true)
                && (empty($needs['item']) || in_array($needs['item'], $items, true))
                && (empty($needs['teamrole']) || in_array($needs['teamrole'], $roles, true))
            ) {
                $candidates[] = $b;
            }
        }
        return $candidates ? $candidates[($day + (int) $char->id) % count($candidates)] : null;
    }

    /**
     * A being as the student meets it: its clues, its group (what the creature sniffs) and four names to choose from,
     * its own and three of the same group (the creature already said the group): other beings of the mission first,
     * then the decoys of the group. Not which one is right.
     *
     * @param array $being
     * @param \stdClass $mission
     * @param string[] $crossed The names they missed today: crossed out.
     * @return array place, clues, group, options (id, name), picture, crossed.
     */
    public static function view(array $being, \stdClass $mission, array $crossed = []): array {
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $group = (string) ($being['group'] ?? '');
        $same = array_values(array_filter($mission->data['encounters'] ?? [], fn($b) => ($b['id'] ?? '') !== $being['id']
            && ($b['group'] ?? '') === $group));
        // The same three others each time for this being, so that meeting it again is the same question.
        usort($same, fn($a, $b) => crc32($being['id'] . $a['id']) <=> crc32($being['id'] . $b['id']));
        $options = [['id' => (string) $being['id'], 'name' => $text($being['name'] ?? '')]];
        foreach ($same as $other) {
            $options[] = ['id' => (string) $other['id'], 'name' => $text($other['name'] ?? '')];
        }
        foreach ((array) ($mission->data['encounterdecoys'][$group] ?? []) as $i => $decoy) {
            $options[] = ['id' => 'decoy-' . $i, 'name' => $text($decoy)];
        }
        $options = array_slice($options, 0, 4);
        usort($options, fn($a, $b) => strcmp($a['name'], $b['name']));
        return ['place' => (string) $being['place'], 'clues' => array_map($text, array_values($being['clues'] ?? [])),
            'group' => $group, 'options' => $options, 'picture' => 'being.' . $being['id'], 'crossed' => $crossed];
    }

    /**
     * The names a student missed today in a place, crossed out: they look for it again.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $place
     * @param int|null $now
     * @return string[] Ids of the options.
     */
    public static function crossed(\stdClass $char, \stdClass $mission, string $place, ?int $now = null): array {
        global $DB;
        $key = $place . '-' . self::day((int) $mission->courseid, $now) . ':';
        $where = 'charid = ? AND missionid = ? AND kind = ? AND ' . $DB->sql_like('code', '?');
        $codes = $DB->get_fieldset_select(self::T . 'found', 'code', $where, [$char->id, $mission->id, 'miss',
            $DB->sql_like_escape($key) . '%']);
        // A miss keeps the place of the name among the four (a name may not fit).
        $being = $codes ? self::today($char, $mission, $place, $now) : null;
        $options = $being ? self::view($being, $mission)['options'] : [];
        $crossed = [];
        foreach ($codes as $code) {
            $id = (string) ($options[(int) substr((string) $code, strlen($key))]['id'] ?? '');
            if ($id !== '') {
                $crossed[] = $id;
            }
        }
        return $crossed;
    }

    /**
     * The student says who it is, with the clues they opened. Right, it goes to their field guide: golden with one clue
     * and no miss, with twice the gems; with no miss, its gems; after a miss, none (with «Fresh track», the first miss
     * keeps them). Wrong, the name is crossed out and it flees to another bush: they look for it again.
     *
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass $mission
     * @param string $place
     * @param string $choice Id of the being chosen.
     * @param int $clues Clues opened (1 to 3).
     * @param int|null $now
     * @return array right, golden, gems, name, group, fact, again (they look for it again), crossed (the names missed).
     */
    public static function answer(
        \stdClass $char,
        \stdClass $member,
        \stdClass $mission,
        string $place,
        string $choice,
        int $clues,
        ?int $now = null
    ): array {
        $now = $now ?? time();
        $being = self::today($char, $mission, $place, $now);
        if (!$being) {
            throw new \moodle_exception('noencounter', 'local_oksigeniatesape');
        }
        $day = $place . '-' . self::day((int) $mission->courseid, $now);
        $crossed = self::crossed($char, $mission, $place, $now);
        $at = array_search($choice, array_column(self::view($being, $mission)['options'], 'id'), true);
        if ($at === false || in_array($choice, $crossed, true)) {
            throw new \invalid_parameter_exception('Not a name to choose');
        }
        if ($choice !== (string) $being['id']) {
            self::note($char, $mission, 'miss', $day . ':' . $at, $now);
            return ['right' => false, 'golden' => false, 'gems' => 0, 'name' => '', 'group' => '', 'fact' => '',
                'again' => true, 'crossed' => array_merge($crossed, [$choice])];
        }
        self::note($char, $mission, 'met', $day, $now);
        self::note($char, $mission, 'being', (string) $being['id'], $now);
        $golden = $clues <= 1 && !$crossed;
        if ($golden) {
            self::note($char, $mission, 'beinggold', (string) $being['id'], $now);
        }
        $fresh = count($crossed) === 1 && talents::has((int) $char->id, 'freshtrack', (int) $mission->courseid);
        $gems = $golden ? 2 * self::GEMS : (!$crossed || $fresh ? self::GEMS : 0);
        $entry = $gems ? engine::gems($member, $gems, 'encounter', 0, 0, 0, $now) : null;
        return ['right' => true, 'golden' => $golden, 'gems' => $entry ? (int) $entry->amount : 0,
            'name' => missions::text($being['name'] ?? '', $mission->skin, $mission->variant),
            'group' => (string) ($being['group'] ?? ''),
            'fact' => missions::text($being['fact'] ?? '', $mission->skin, $mission->variant), 'again' => false,
            'crossed' => $crossed];
    }

    /**
     * The field guide of the beings of a student, place by place: how many there are and those they registered.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @return array Place code => total, beings (id, name, group, fact, golden).
     */
    public static function guide(\stdClass $char, \stdClass $mission): array {
        global $DB;
        $where = "charid = ? AND missionid = ? AND kind IN ('being', 'beinggold')";
        $found = $DB->get_records_select(self::T . 'found', $where, [$char->id, $mission->id], '', 'id, kind, code');
        $mine = [];
        foreach ($found as $row) {
            $mine[$row->code][$row->kind] = true;
        }
        $guide = [];
        foreach ($mission->data['encounters'] ?? [] as $being) {
            $place = (string) ($being['place'] ?? '');
            $guide[$place] = $guide[$place] ?? ['place' => $place, 'total' => 0, 'beings' => []];
            $guide[$place]['total']++;
            if (!empty($mine[$being['id']]['being'])) {
                $guide[$place]['beings'][] = ['id' => (string) $being['id'],
                    'name' => missions::text($being['name'] ?? '', $mission->skin, $mission->variant),
                    'group' => (string) ($being['group'] ?? ''),
                    'fact' => missions::text($being['fact'] ?? '', $mission->skin, $mission->variant),
                    'golden' => !empty($mine[$being['id']]['beinggold'])];
            }
        }
        return array_values($guide);
    }

    /**
     * The day, as yyyymmdd in the time zone of the course.
     *
     * @param int $courseid
     * @param int|null $now
     * @return int
     */
    private static function day(int $courseid, ?int $now = null): int {
        return (int) (new \DateTimeImmutable('@' . ($now ?? time())))->setTimezone(engine::timezone($courseid))->format('Ymd');
    }

    /**
     * Whether a student has found something in a mission.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $kind
     * @param string $code
     * @return bool
     */
    private static function found(\stdClass $char, \stdClass $mission, string $kind, string $code): bool {
        global $DB;
        return $DB->record_exists(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id, 'kind' => $kind,
            'code' => $code]);
    }

    /**
     * Notes that a student found something in a mission, once.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @param string $kind
     * @param string $code
     * @param int $now
     */
    private static function note(\stdClass $char, \stdClass $mission, string $kind, string $code, int $now): void {
        global $DB;
        if (!self::found($char, $mission, $kind, $code)) {
            $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $mission->courseid,
                'missionid' => $mission->id, 'kind' => $kind, 'code' => $code, 'timecreated' => $now]);
        }
    }
}
