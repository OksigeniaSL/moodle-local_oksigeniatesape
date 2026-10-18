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
 * The commitments of a team: in each chapter, each member chooses one from those the mission brings ("bring a leaf for
 * the inventory"). The teacher marks whether it was kept. One not kept costs its owner energy (the wall, the shield and
 * the calm day still stop it); a team that keeps them all gets breath for each member. Energy is lost for what one
 * chose, never for behaviour.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class pacts {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Energy a commitment not kept costs its owner. */
    public const LOSS = 2;

    /** @var int Breath for each member of a team that keeps all its commitments of a chapter. */
    public const BREATH = 1;

    /** @var int Commitments a team needs at least to earn the breath (fewer if the team is smaller). */
    public const MIN = 2;

    /**
     * The chapter the class is in: the first one whose lantern the class has not lit yet (the last one, once all are lit).
     *
     * @param \stdClass $mission
     * @return \stdClass|null The chapter.
     */
    public static function chapter(\stdClass $mission): ?\stdClass {
        global $DB;
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder');
        foreach ($chapters as $chapter) {
            if (!$chapter->litat) {
                return $chapter;
            }
        }
        return $chapters ? end($chapters) : null;
    }

    /**
     * The commitments the mission brings for a chapter.
     *
     * @param \stdClass $mission With data.
     * @param string $chapter Code.
     * @return string[]
     */
    public static function options(\stdClass $mission, string $chapter): array {
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if ($info['code'] === $chapter) {
                return array_values(array_map(
                    fn($pact) => missions::text($pact, $mission->skin, $mission->variant),
                    $info['pacts'] ?? []
                ));
            }
        }
        return [];
    }

    /**
     * A student chooses their commitment for the chapter the class is in, or changes it while it is not marked.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $option Index in the commitments of the chapter.
     * @param int|null $now
     * @return \stdClass The commitment.
     */
    public static function choose(int $courseid, int $userid, int $option, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $member = $char ? $DB->get_record(self::T . 'member', ['charid' => $char->id, 'courseid' => $courseid]) : null;
        $mission = missions::get($courseid);
        $team = teams::of($courseid, $userid);
        if (!$member || $member->status === 'paused' || !$mission || !$team) {
            throw new \moodle_exception('nopact', 'local_oksigeniatesape');
        }
        $chapter = self::chapter($mission);
        $options = $chapter ? self::options($mission, $chapter->code) : [];
        if (!isset($options[$option])) {
            throw new \invalid_parameter_exception('Not a commitment of the chapter');
        }
        $key = ['charid' => $char->id, 'courseid' => $courseid, 'missionid' => $mission->id, 'chapter' => $chapter->code];
        $pact = $DB->get_record(self::T . 'pact', $key);
        if ($pact && $pact->status !== 'open') {
            throw new \moodle_exception('pactdecided', 'local_oksigeniatesape');
        }
        if ($pact) {
            $pact->text = $options[$option];
            $pact->groupid = $team->id;
            $DB->update_record(self::T . 'pact', $pact);
            return $pact;
        }
        $pact = (object) ($key + ['groupid' => $team->id, 'text' => $options[$option], 'status' => 'open', 'decidedby' => 0,
            'timecreated' => $now, 'timedecided' => 0]);
        try {
            $pact->id = $DB->insert_record(self::T . 'pact', $pact);
        } catch (\dml_write_exception $e) {
            // A second tap at the same time: the first one counts.
            return $DB->get_record(self::T . 'pact', $key, '*', MUST_EXIST);
        }
        return $pact;
    }

    /**
     * The teacher marks a commitment: kept, not kept, or back to pending. Not kept costs its owner energy; going back
     * undoes it. When the team has kept all of them in the chapter, each member gets breath, once; if a mark changes
     * afterwards, the breath goes back.
     *
     * @param int $pactid
     * @param string $status kept, broken or open.
     * @param int $actorid
     * @param int|null $now
     * @return \stdClass The commitment.
     */
    public static function decide(int $pactid, string $status, int $actorid, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        if (!in_array($status, ['kept', 'broken', 'open'], true)) {
            throw new \invalid_parameter_exception('Not a mark for a commitment');
        }
        $pact = $DB->get_record(self::T . 'pact', ['id' => $pactid], '*', MUST_EXIST);
        if ($pact->status === $status) {
            return $pact;
        }
        $transaction = $DB->start_delegated_transaction();
        $member = $DB->get_record(self::T . 'member', ['charid' => $pact->charid, 'courseid' => $pact->courseid]);
        if ($pact->status === 'broken') {
            // It was kept after all: the energy comes back.
            $where = "charid = ? AND courseid = ? AND resource = 'energy' AND reason = 'commitment' AND refid = ? AND undoneby = 0";
            foreach ($DB->get_records_select(self::T . 'ledger', $where, [$pact->charid, $pact->courseid, $pact->id]) as $entry) {
                engine::undo((int) $entry->id, $actorid, $now);
            }
            // And what a shield cost the protector for it, with the shield ready again.
            $where = "courseid = ? AND resource = 'energy' AND reason = 'shield' AND refid = ? AND undoneby = 0 AND amount < 0";
            $shields = $DB->get_records_select(self::T . 'ledger', $where, [$pact->courseid, $pact->id]);
            foreach ($shields as $entry) {
                engine::undo((int) $entry->id, $actorid, $now);
            }
            $guarded = $member ? $DB->record_exists_select(self::T . 'ledger', "charid = ? AND courseid = ? AND reason = 'shield'
                AND refid = ? AND undoneby = 0", [$pact->charid, $pact->courseid, $pact->id]) : false;
            if ($guarded) {
                $used = $DB->get_records_sql(
                    "SELECT r.id
                       FROM {" . self::T . "powerreq} r
                       JOIN {" . self::T . "power} p ON p.id = r.powerid
                      WHERE p.code = 'shield' AND r.status = 'used' AND r.targetmemberid = ?
                   ORDER BY r.id DESC",
                    [$member->id],
                    0,
                    1
                );
                foreach ($used as $request) {
                    $DB->set_field(self::T . 'powerreq', 'status', 'approved', ['id' => $request->id]);
                }
                $DB->set_field_select(self::T . 'ledger', 'undoneby', $actorid, "charid = ? AND courseid = ? AND reason = 'shield'
                    AND refid = ? AND undoneby = 0", [$pact->charid, $pact->courseid, $pact->id]);
            }
        }
        $pact->status = $status;
        $pact->decidedby = $status === 'open' ? 0 : $actorid;
        $pact->timedecided = $status === 'open' ? 0 : $now;
        $DB->update_record(self::T . 'pact', $pact);
        if ($status === 'broken' && $member) {
            $member = $DB->get_record(self::T . 'member', ['id' => $member->id]);
            engine::energy($member, -self::LOSS, 'commitment', $actorid, (int) $pact->id, $now);
        }
        self::team_breath($pact, $actorid, $now);
        $transaction->allow_commit();
        return $pact;
    }

    /**
     * The breath of a team that kept all its commitments of a chapter: given once to each member, taken back if the
     * team no longer has them all kept.
     *
     * @param \stdClass $pact One commitment of the team and chapter.
     * @param int $actorid
     * @param int $now
     */
    private static function team_breath(\stdClass $pact, int $actorid, int $now): void {
        global $DB;
        $key = ['courseid' => $pact->courseid, 'missionid' => $pact->missionid, 'chapter' => $pact->chapter,
            'groupid' => $pact->groupid];
        $statuses = $DB->get_fieldset_select(
            self::T . 'pact',
            'status',
            'courseid = ? AND missionid = ? AND chapter = ? AND groupid = ?',
            array_values($key)
        );
        $members = teams::members((int) $pact->courseid, (int) $pact->groupid);
        $needed = min(self::MIN, max(1, count($members)));
        $all = count($statuses) >= $needed && !array_diff($statuses, ['kept']);
        // The commitments of a place kept by the whole team fill its reserve, once; not kept any more, it goes back.
        $key = 'pacts-' . $pact->chapter;
        if ($all) {
            reserve::credit((int) $pact->courseid, (int) $pact->groupid, $key, reserve::PACTS, $now);
        } else {
            reserve::uncredit((int) $pact->courseid, (int) $pact->groupid, $key, $now);
        }
        $chapterid = (int) $DB->get_field(self::T . 'chapter', 'id', ['missionid' => $pact->missionid, 'code' => $pact->chapter]);
        foreach ($members as $member) {
            $given = ['charid' => $member->charid, 'missionid' => $pact->missionid, 'kind' => 'pacts', 'code' => $pact->chapter];
            $has = $DB->record_exists(self::T . 'found', $given);
            if ($all && !$has) {
                $DB->insert_record(self::T . 'found', (object) ($given + ['courseid' => $pact->courseid, 'timecreated' => $now]));
                engine::breath($member, self::BREATH, 'pacts', $actorid, $chapterid, $now);
            } else if (!$all && $has) {
                $DB->delete_records(self::T . 'found', $given);
                $where = "charid = ? AND courseid = ? AND resource = 'breath' AND reason = 'pacts' AND refid = ? AND undoneby = 0";
                $entries = $DB->get_records_select(self::T . 'ledger', $where, [$member->charid, $pact->courseid, $chapterid]);
                foreach ($entries as $entry) {
                    engine::undo((int) $entry->id, $actorid, $now);
                }
            }
        }
    }

    /**
     * The commitments of a team in the chapter the class is in, for the phone of one of its members.
     *
     * @param int $courseid
     * @param \stdClass $group
     * @param int $userid The member who looks.
     * @param array $names User id => the name classmates see.
     * @return array|null chapter (name), options, mine (index, -1 if none), status (of mine), list, done, breath, loss.
     */
    public static function for_team(int $courseid, \stdClass $group, int $userid, array $names): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $chapter = $mission ? self::chapter($mission) : null;
        $options = $chapter ? self::options($mission, $chapter->code) : [];
        if (!$options) {
            return null;
        }
        $name = $chapter->code;
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if ($info['code'] === $chapter->code) {
                $name = missions::text($info['name'] ?? $chapter->code, $mission->skin);
            }
        }
        $pacts = $DB->get_records_sql(
            'SELECT p.*, c.userid
               FROM {' . self::T . 'pact} p
               JOIN {' . self::T . 'char} c ON c.id = p.charid
              WHERE p.courseid = ? AND p.missionid = ? AND p.chapter = ? AND p.groupid = ?
           ORDER BY p.timecreated, p.id',
            [$courseid, $mission->id, $chapter->code, $group->id]
        );
        $view = ['chapter' => $name, 'options' => $options, 'mine' => -1, 'status' => '', 'list' => [],
            'breath' => self::BREATH, 'loss' => self::LOSS];
        foreach ($pacts as $pact) {
            if (!isset($names[$pact->userid])) {
                continue;
            }
            if ((int) $pact->userid === $userid) {
                $index = array_search($pact->text, $options, true);
                $view['mine'] = $index === false ? -1 : (int) $index;
                $view['status'] = $pact->status;
            }
            // A commitment the teacher marked as not kept is for its student only: the team sees it without a mark.
            $me = (int) $pact->userid === $userid;
            $view['list'][] = ['name' => $names[$pact->userid], 'text' => $pact->text,
                'status' => $me || $pact->status !== 'broken' ? $pact->status : '', 'me' => $me];
        }
        $charid = (int) $DB->get_field(self::T . 'char', 'id', ['userid' => $userid]);
        $given = ['charid' => $charid, 'missionid' => $mission->id, 'kind' => 'pacts', 'code' => $chapter->code];
        $view['done'] = $DB->record_exists(self::T . 'found', $given);
        return $view;
    }

    /**
     * The commitments to mark, for the teacher: those of the chapter the class is in and those still pending from
     * earlier chapters.
     *
     * @param int $courseid
     * @return array|null chapter (name), options (how many), breath, loss and list (id, userid, teamid, chapter, text,
     *     status).
     */
    public static function for_teacher(int $courseid): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $chapter = $mission ? self::chapter($mission) : null;
        if (!$chapter) {
            return null;
        }
        $names = [];
        foreach ($mission->data['chapters'] ?? [] as $info) {
            $names[$info['code']] = missions::text($info['name'] ?? $info['code'], $mission->skin);
        }
        $pacts = $DB->get_records_sql(
            "SELECT p.*, c.userid
               FROM {" . self::T . "pact} p
               JOIN {" . self::T . "char} c ON c.id = p.charid
              WHERE p.courseid = ? AND p.missionid = ? AND (p.chapter = ? OR p.status = 'open')
           ORDER BY p.groupid, p.timecreated, p.id",
            [$courseid, $mission->id, $chapter->code]
        );
        return [
            'chapter' => $names[$chapter->code] ?? $chapter->code,
            'options' => count(self::options($mission, $chapter->code)),
            'breath' => self::BREATH,
            'loss' => self::LOSS,
            'list' => array_values(array_map(fn($p) => ['id' => (int) $p->id, 'userid' => (int) $p->userid,
                'teamid' => (int) $p->groupid, 'chapter' => $p->chapter === $chapter->code ? '' : ($names[$p->chapter] ?? ''),
                'text' => $p->text, 'status' => $p->status], $pacts)),
        ];
    }

    /**
     * Commitments still to mark in a course.
     *
     * @param int $courseid
     * @return int
     */
    public static function pending(int $courseid): int {
        global $DB;
        return $DB->count_records(self::T . 'pact', ['courseid' => $courseid, 'status' => 'open']);
    }
}
