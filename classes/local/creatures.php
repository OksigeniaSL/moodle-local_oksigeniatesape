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
 * What the creature becomes and what it knows. Its branch, chosen when it reaches its second stage: land (fur), air
 * (feathers) or water (scales), the coat it wears from then on and the way its next stages go. Its scents: each place
 * whose lantern the student lit is a place it knows; in the battle by turns, a knot of a place it knows, it strikes out
 * a wrong option by itself, on the phone of the student. It never helps who did not play: what it knows is what its
 * student lit.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class creatures {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var array The branches, with the coat each one wears. */
    public const BRANCHES = ['land' => 'fur', 'air' => 'feathers', 'water' => 'scales'];

    /** @var int The stage at which the creature chooses its branch. */
    public const BRANCH_STAGE = 2;

    /**
     * The traits of the creature of a character.
     *
     * @param \stdClass $char
     * @return array Empty before it hatches.
     */
    public static function traits(\stdClass $char): array {
        return json_decode((string) ($char->creature ?? ''), true) ?: [];
    }

    /**
     * The branch of the creature of a character: land, air, water, or empty while it has not chosen.
     *
     * @param \stdClass $char
     * @return string
     */
    public static function branch(\stdClass $char): string {
        return (string) (self::traits($char)['branch'] ?? '');
    }

    /**
     * Whether the creature has to choose its branch: it reached its second stage and has not chosen.
     *
     * @param \stdClass $char
     * @return bool
     */
    public static function branch_pending(\stdClass $char): bool {
        return self::traits($char) && (int) $char->creaturestage >= self::BRANCH_STAGE && self::branch($char) === '';
    }

    /**
     * The creature of a character chooses its branch, once: it wears its coat from then on.
     *
     * @param \stdClass $char
     * @param string $branch
     * @return \stdClass The character.
     */
    public static function choose_branch(\stdClass $char, string $branch): \stdClass {
        global $DB;
        if (!isset(self::BRANCHES[$branch]) || !self::branch_pending($char)) {
            throw new \moodle_exception('nobranch', 'local_oksigeniatesape');
        }
        $traits = self::traits($char);
        $traits['branch'] = $branch;
        $traits['coat'] = self::BRANCHES[$branch];
        $char->creature = json_encode($traits);
        $DB->update_record(self::T . 'char', (object) ['id' => $char->id, 'creature' => $char->creature,
            'timemodified' => time()]);
        return $char;
    }

    /**
     * The places of a mission the creature of a student knows: those whose lantern the student lit.
     *
     * @param \stdClass $char
     * @param \stdClass $mission
     * @return string[] Codes of the chapters.
     */
    public static function known(\stdClass $char, \stdClass $mission): array {
        global $DB;
        if (!self::traits($char)) {
            return [];
        }
        return $DB->get_fieldset_select(
            self::T . 'found',
            'code',
            'charid = ? AND missionid = ? AND kind = ?',
            [$char->id, $mission->id, 'lantern']
        );
    }

    /**
     * Whether the creature of a student sniffs in the minigames: from its second stage, once a play; once more with
     * «A good nose» as the card of the day of the course.
     *
     * @param \stdClass $char
     * @param int $courseid The course played in, for the card of the day.
     * @param int|null $now
     * @return int Sniffs in a play.
     */
    public static function sniffs(\stdClass $char, int $courseid = 0, ?int $now = null): int {
        if (!self::traits($char)) {
            return 0;
        }
        // With «A good nose» as the card of the day, once more.
        $more = $courseid && events::active($courseid, 'sniff', $now) ? 1 : 0;
        return ((int) $char->creaturestage >= self::BRANCH_STAGE ? 1 : 0) + $more;
    }
}
