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

/**
 * Privacy provider.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oksigeniatesape\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * The character lives in the student's user context (it is theirs on the whole site); what happens in a course lives
 * in the course context. Teachers appear in the students' records as the ones who paused, gave or decided: when a
 * teacher's data is deleted, those records stay and lose the teacher.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] Tables of a character in a course, all with charid and courseid. */
    public const COURSE_TABLES = ['member', 'ledger', 'inventory', 'sceneattempt', 'minigame', 'validation', 'found', 'idea',
        'livein', 'livetoken', 'pact'];

    /**
     * What is stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $fields = [
            'char' => ['userid', 'role', 'nameform', 'lineage', 'nickname', 'nicknamepending', 'saber', 'level', 'gems',
                'creature', 'appearance', 'creaturestage', 'rolechangedat', 'timecreated'],
            'member' => ['charid', 'courseid', 'renown', 'energy', 'breath', 'status', 'statusreason', 'statusby', 'step',
                'timecreated'],
            'ledger' => ['charid', 'courseid', 'resource', 'amount', 'reason', 'cmid', 'actorid', 'capped', 'undoneby',
                'timecreated'],
            'powerreq' => ['powerid', 'memberid', 'targetmemberid', 'status', 'decidedby', 'timecreated'],
            'inventory' => ['charid', 'itemid', 'courseid', 'source', 'timecreated'],
            'sceneattempt' => ['sceneid', 'charid', 'courseid', 'path', 'score', 'objectives', 'ending', 'attempt',
                'timestarted', 'timefinished'],
            'minigame' => ['charid', 'courseid', 'cmid', 'game', 'success', 'tries', 'detail', 'timecreated'],
            'validation' => ['challengeid', 'charid', 'courseid', 'status', 'note', 'validatedby', 'timemodified'],
            'found' => ['charid', 'courseid', 'missionid', 'kind', 'code', 'timecreated'],
            'idea' => ['courseid', 'charid', 'anonymous', 'message', 'status', 'timecreated'],
            'livein' => ['liveid', 'courseid', 'charid', 'round', 'team', 'answer', 'timecreated'],
            'livetoken' => ['liveid', 'courseid', 'charid', 'round', 'team', 'kind', 'timecreated'],
            'pact' => ['courseid', 'charid', 'missionid', 'chapter', 'groupid', 'text', 'status', 'decidedby', 'timecreated',
                'timedecided'],
        ];
        foreach ($fields as $table => $names) {
            $described = [];
            foreach ($names as $name) {
                $described[$name] = "privacy:metadata:$table:$name";
            }
            $collection->add_database_table(self::T . $table, $described, "privacy:metadata:$table");
        }
        // The plan for the next class goes to the AI of the site with counts and the content of the mission only; the
        // AI subsystem keeps who asked for it.
        $collection->add_subsystem_link('core_ai', [], 'privacy:metadata:core_ai');
        return $collection;
    }

    /**
     * The user context if they have a character, the courses where they played, and the courses where they acted as
     * a teacher on someone's character.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'char} c ON c.userid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE c.userid = :userid',
            ['level' => CONTEXT_USER, 'userid' => $userid]
        );
        foreach (self::COURSE_TABLES as $table) {
            $contextlist->add_from_sql(
                'SELECT ctx.id
                   FROM {context} ctx
                   JOIN {' . self::T . $table . '} t ON t.courseid = ctx.instanceid AND ctx.contextlevel = :level
                   JOIN {' . self::T . 'char} c ON c.id = t.charid
                  WHERE c.userid = :userid',
                ['level' => CONTEXT_COURSE, 'userid' => $userid]
            );
        }
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'member} m ON m.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE m.statusby = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'ledger} l ON l.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE l.actorid = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'member} m ON m.courseid = ctx.instanceid AND ctx.contextlevel = :level
               JOIN {' . self::T . 'powerreq} r ON r.memberid = m.id
              WHERE r.decidedby = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'validation} v ON v.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE v.validatedby = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        $contextlist->add_from_sql(
            'SELECT ctx.id
               FROM {context} ctx
               JOIN {' . self::T . 'pact} p ON p.courseid = ctx.instanceid AND ctx.contextlevel = :level
              WHERE p.decidedby = :userid',
            ['level' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel == CONTEXT_USER) {
            $userlist->add_from_sql(
                'userid',
                'SELECT userid FROM {' . self::T . 'char} WHERE userid = :userid',
                ['userid' => $context->instanceid]
            );
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $params = ['courseid' => $context->instanceid];
        foreach (self::COURSE_TABLES as $table) {
            $userlist->add_from_sql(
                'userid',
                'SELECT c.userid
                   FROM {' . self::T . $table . '} t
                   JOIN {' . self::T . 'char} c ON c.id = t.charid
                  WHERE t.courseid = :courseid',
                $params
            );
        }
        $userlist->add_from_sql(
            'statusby',
            'SELECT statusby FROM {' . self::T . 'member} WHERE courseid = :courseid AND statusby > 0',
            $params
        );
        $userlist->add_from_sql(
            'actorid',
            'SELECT actorid FROM {' . self::T . 'ledger} WHERE courseid = :courseid AND actorid > 0',
            $params
        );
        $userlist->add_from_sql(
            'validatedby',
            'SELECT validatedby FROM {' . self::T . 'validation} WHERE courseid = :courseid AND validatedby > 0',
            $params
        );
        $userlist->add_from_sql(
            'decidedby',
            'SELECT r.decidedby
               FROM {' . self::T . 'powerreq} r
               JOIN {' . self::T . 'member} m ON m.id = r.memberid
              WHERE m.courseid = :courseid AND r.decidedby > 0',
            $params
        );
        $userlist->add_from_sql(
            'decidedby',
            'SELECT decidedby FROM {' . self::T . 'pact} WHERE courseid = :courseid AND decidedby > 0',
            $params
        );
    }

    /**
     * Exports the character in the user context and what they did in each course.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        $char = $DB->get_record(self::T . 'char', ['userid' => $userid]);
        if (!$char) {
            return;
        }
        $base = self::path();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_USER && $context->instanceid == $userid) {
                writer::with_context($context)->export_data($base, (object) [
                    'role' => $char->role,
                    'nameform' => $char->nameform,
                    'lineage' => $char->lineage,
                    'nickname' => $char->nickname,
                    'nicknamepending' => $char->nicknamepending,
                    'saber' => $char->saber,
                    'level' => $char->level,
                    'gems' => $char->gems,
                    'creature' => json_decode((string) $char->creature),
                    'appearance' => json_decode((string) $char->appearance),
                    'creaturestage' => $char->creaturestage,
                    'rolechangedat' => $char->rolechangedat ? transform::datetime($char->rolechangedat) : null,
                    'timecreated' => transform::datetime($char->timecreated),
                ]);
                self::export_ledger($context, $char->id, 0);
            } else if ($context->contextlevel == CONTEXT_COURSE) {
                self::export_course($context, $char->id);
            }
        }
    }

    /**
     * Where an export goes: the plugin folder, or one of its subfolders.
     *
     * @param string|null $sub Key of the subfolder (privacy:path:<key>).
     * @return string[]
     */
    private static function path(?string $sub = null): array {
        $path = [get_string('pluginname', 'local_oksigeniatesape')];
        if ($sub !== null) {
            $path[] = get_string('privacy:path:' . $sub, 'local_oksigeniatesape');
        }
        return $path;
    }

    /**
     * Exports a character's data in a course.
     *
     * @param \context $context
     * @param int $charid
     */
    private static function export_course(\context $context, int $charid): void {
        global $DB;
        $courseid = (int) $context->instanceid;
        $writer = writer::with_context($context);
        $member = $DB->get_record(self::T . 'member', ['charid' => $charid, 'courseid' => $courseid]);
        if ($member) {
            $writer->export_data(self::path(), (object) [
                'renown' => $member->renown,
                'energy' => $member->energy,
                'breath' => $member->breath,
                'status' => $member->status,
                'statusreason' => $member->statusreason,
                'step' => $member->step,
                'timecreated' => transform::datetime($member->timecreated),
            ]);
            $requests = $DB->get_records_sql(
                'SELECT r.id, p.code, p.name, r.status, r.timecreated, r.timedecided
                   FROM {' . self::T . 'powerreq} r
                   JOIN {' . self::T . 'power} p ON p.id = r.powerid
                  WHERE r.memberid = ?
               ORDER BY r.timecreated',
                [$member->id]
            );
            if ($requests) {
                $writer->export_data(self::path('powers'), (object) [
                    'requests' => array_values(array_map(fn($r) => [
                        'power' => \local_oksigeniatesape\local\powers::name($r),
                        'status' => $r->status,
                        'timecreated' => transform::datetime($r->timecreated),
                        'timedecided' => $r->timedecided ? transform::datetime($r->timedecided) : null,
                    ], $requests)),
                ]);
            }
        }
        self::export_ledger($context, $charid, $courseid);
        $items = $DB->get_records_sql(
            'SELECT v.id, i.code, i.name, v.source, v.timecreated
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ?
           ORDER BY v.timecreated',
            [$charid, $courseid]
        );
        if ($items) {
            $writer->export_data(self::path('items'), (object) [
                'items' => array_values(array_map(fn($v) => [
                    'code' => $v->code,
                    'name' => $v->name,
                    'source' => $v->source,
                    'timecreated' => transform::datetime($v->timecreated),
                ], $items)),
            ]);
        }
        $attempts = $DB->get_records_sql(
            'SELECT a.id, s.code, a.path, a.score, a.objectives, a.ending, a.attempt, a.timestarted, a.timefinished
               FROM {' . self::T . 'sceneattempt} a
               JOIN {' . self::T . 'scene} s ON s.id = a.sceneid
              WHERE a.charid = ? AND a.courseid = ?
           ORDER BY a.timestarted',
            [$charid, $courseid]
        );
        if ($attempts) {
            $writer->export_data(self::path('scenes'), (object) [
                'attempts' => array_values(array_map(fn($a) => [
                    'scene' => $a->code,
                    'path' => json_decode((string) $a->path),
                    'score' => $a->score,
                    'objectives' => json_decode((string) $a->objectives),
                    'ending' => $a->ending,
                    'attempt' => $a->attempt,
                    'timestarted' => transform::datetime($a->timestarted),
                    'timefinished' => $a->timefinished ? transform::datetime($a->timefinished) : null,
                ], $attempts)),
            ]);
        }
        $validations = $DB->get_records_sql(
            'SELECT v.id, c.name, v.status, v.note, v.timemodified
               FROM {' . self::T . 'validation} v
               JOIN {' . self::T . 'challenge} c ON c.id = v.challengeid
              WHERE v.charid = ? AND v.courseid = ?
           ORDER BY v.timemodified',
            [$charid, $courseid]
        );
        if ($validations) {
            $writer->export_data(self::path('classroom'), (object) [
                'challenges' => array_values(array_map(fn($v) => [
                    'challenge' => $v->name,
                    'status' => $v->status,
                    'note' => $v->note,
                    'timemodified' => transform::datetime($v->timemodified),
                ], $validations)),
            ]);
        }
        $found = $DB->get_records(self::T . 'found', ['charid' => $charid, 'courseid' => $courseid], 'timecreated, id');
        if ($found) {
            $writer->export_data(self::path('found'), (object) [
                'found' => array_values(array_map(fn($f) => [
                    'kind' => $f->kind,
                    'code' => $f->code,
                    'timecreated' => transform::datetime($f->timecreated),
                ], $found)),
            ]);
        }
        $ideas = $DB->get_records(self::T . 'idea', ['charid' => $charid, 'courseid' => $courseid], 'timecreated, id');
        if ($ideas) {
            $writer->export_data(self::path('ideas'), (object) [
                'ideas' => array_values(array_map(fn($i) => [
                    'message' => $i->message,
                    'anonymous' => transform::yesno($i->anonymous),
                    'status' => $i->status,
                    'timecreated' => transform::datetime($i->timecreated),
                ], $ideas)),
            ]);
        }
        $live = $DB->get_records(self::T . 'livein', ['charid' => $charid, 'courseid' => $courseid], 'liveid, round');
        $tokens = $DB->get_records(self::T . 'livetoken', ['charid' => $charid, 'courseid' => $courseid], 'liveid');
        if ($live || $tokens) {
            $writer->export_data(self::path('battles'), (object) [
                // Round 0 is coming in.
                'answers' => array_values(array_map(fn($a) => [
                    'session' => (int) $a->liveid,
                    'round' => (int) $a->round,
                    'what' => (int) $a->round > 0 ? 'answer' : 'joined',
                    'answer' => (int) $a->answer,
                    'timecreated' => transform::datetime($a->timecreated),
                ], $live)),
                'tokens' => array_values(array_map(fn($t) => [
                    'session' => (int) $t->liveid,
                    'round' => (int) $t->round,
                    'token' => $t->kind,
                    'timecreated' => transform::datetime($t->timecreated),
                ], $tokens)),
            ]);
        }
        $pacts = $DB->get_records(self::T . 'pact', ['charid' => $charid, 'courseid' => $courseid], 'timecreated');
        if ($pacts) {
            $writer->export_data(self::path('commitments'), (object) [
                'commitments' => array_values(array_map(fn($p) => [
                    'chapter' => $p->chapter,
                    'text' => $p->text,
                    'status' => $p->status,
                    'timecreated' => transform::datetime($p->timecreated),
                    'timedecided' => $p->timedecided ? transform::datetime($p->timedecided) : null,
                ], $pacts)),
            ]);
        }
        $games = $DB->get_records(self::T . 'minigame', ['charid' => $charid, 'courseid' => $courseid], 'timecreated');
        if ($games) {
            $writer->export_data(self::path('minigames'), (object) [
                'results' => array_values(array_map(fn($g) => [
                    'game' => $g->game,
                    'success' => transform::yesno($g->success),
                    'tries' => $g->tries,
                    'detail' => json_decode((string) $g->detail),
                    'timecreated' => transform::datetime($g->timecreated),
                ], $games)),
            ]);
        }
    }

    /**
     * Exports a character's ledger in a course (or the entries of no course, with courseid 0).
     *
     * @param \context $context
     * @param int $charid
     * @param int $courseid
     */
    private static function export_ledger(\context $context, int $charid, int $courseid): void {
        global $DB;
        $entries = $DB->get_records(self::T . 'ledger', ['charid' => $charid, 'courseid' => $courseid], 'timecreated, id');
        if (!$entries) {
            return;
        }
        writer::with_context($context)->export_data(self::path('ledger'), (object) [
            'entries' => array_values(array_map(fn($e) => [
                'resource' => $e->resource,
                'amount' => $e->amount,
                'reason' => $e->reason,
                'capped' => $e->capped,
                'undone' => transform::yesno($e->undoneby > 0),
                'timecreated' => transform::datetime($e->timecreated),
            ], $entries)),
        ]);
    }

    /**
     * Deletes every user's data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel == CONTEXT_USER) {
            $charid = $DB->get_field(self::T . 'char', 'id', ['userid' => $context->instanceid]);
            if ($charid) {
                self::delete_character((int) $charid);
            }
        } else if ($context->contextlevel == CONTEXT_COURSE) {
            self::delete_in_course((int) $context->instanceid, null);
        }
    }

    /**
     * Deletes a user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_context($context, [(int) $contextlist->get_user()->id]);
        }
    }

    /**
     * Deletes the data of several users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        self::delete_users_in_context($userlist->get_context(), array_map('intval', $userlist->get_userids()));
    }

    /**
     * Deletes the data of some users in one context: the whole character in their user context; in a course, what
     * they did there as students, and their name where they acted as teachers.
     *
     * @param \context $context
     * @param int[] $userids
     */
    private static function delete_users_in_context(\context $context, array $userids): void {
        global $DB;
        if (!$userids) {
            return;
        }
        if ($context->contextlevel == CONTEXT_USER) {
            if (in_array((int) $context->instanceid, $userids, true)) {
                $charid = $DB->get_field(self::T . 'char', 'id', ['userid' => $context->instanceid]);
                if ($charid) {
                    self::delete_character((int) $charid);
                }
            }
            return;
        }
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $courseid = (int) $context->instanceid;
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $charids = $DB->get_fieldset_select(self::T . 'char', 'id', "userid $insql", $params);
        if ($charids) {
            self::delete_in_course($courseid, array_map('intval', $charids));
        }
        $DB->execute(
            'UPDATE {' . self::T . "member} SET statusby = 0 WHERE courseid = ? AND statusby $insql",
            array_merge([$courseid], $params)
        );
        $DB->execute(
            'UPDATE {' . self::T . "ledger} SET actorid = 0 WHERE courseid = ? AND actorid $insql",
            array_merge([$courseid], $params)
        );
        $DB->execute(
            'UPDATE {' . self::T . "validation} SET validatedby = 0 WHERE courseid = ? AND validatedby $insql",
            array_merge([$courseid], $params)
        );
        $DB->execute(
            'UPDATE {' . self::T . "pact} SET decidedby = 0 WHERE courseid = ? AND decidedby $insql",
            array_merge([$courseid], $params)
        );
        $members = $DB->get_fieldset_select(self::T . 'member', 'id', 'courseid = ?', [$courseid]);
        if ($members) {
            [$inmember, $memberparams] = $DB->get_in_or_equal($members);
            $DB->execute(
                'UPDATE {' . self::T . "powerreq} SET decidedby = 0 WHERE memberid $inmember AND decidedby $insql",
                array_merge($memberparams, $params)
            );
        }
    }

    /**
     * Deletes what characters did in a course: all of them, or some.
     *
     * @param int $courseid
     * @param int[]|null $charids
     */
    private static function delete_in_course(int $courseid, ?array $charids): void {
        global $DB;
        $where = 'courseid = ?';
        $params = [$courseid];
        if ($charids !== null) {
            [$insql, $charparams] = $DB->get_in_or_equal($charids);
            $where .= " AND charid $insql";
            $params = array_merge($params, $charparams);
        }
        $members = $DB->get_fieldset_select(self::T . 'member', 'id', $where, $params);
        if ($members) {
            [$inmember, $memberparams] = $DB->get_in_or_equal($members);
            $DB->delete_records_select(self::T . 'powerreq', "memberid $inmember", $memberparams);
            // Others' requests aimed at them stay, without the target.
            $DB->set_field_select(self::T . 'powerreq', 'targetmemberid', 0, "targetmemberid $inmember", $memberparams);
        }
        foreach (self::COURSE_TABLES as $table) {
            $DB->delete_records_select(self::T . $table, $where, $params);
        }
    }

    /**
     * A deleted user account: their character goes, with everything that hangs from it, and their name goes from what
     * they did as a teacher. Moodle does not ask the privacy providers when an account is deleted by hand.
     *
     * @param int $userid
     */
    public static function delete_deleted_user(int $userid): void {
        global $DB;
        $charid = $DB->get_field(self::T . 'char', 'id', ['userid' => $userid]);
        if ($charid) {
            self::delete_character((int) $charid);
        }
        $DB->set_field(self::T . 'member', 'statusby', 0, ['statusby' => $userid]);
        $DB->set_field(self::T . 'ledger', 'actorid', 0, ['actorid' => $userid]);
        $DB->set_field(self::T . 'validation', 'validatedby', 0, ['validatedby' => $userid]);
        $DB->set_field(self::T . 'pact', 'decidedby', 0, ['decidedby' => $userid]);
        $DB->set_field(self::T . 'powerreq', 'decidedby', 0, ['decidedby' => $userid]);
    }

    /**
     * Deletes a character and everything that hangs from it, in every course: without the character nothing else
     * can be tied to the student.
     *
     * @param int $charid
     */
    private static function delete_character(int $charid): void {
        global $DB;
        $courseids = [];
        foreach (self::COURSE_TABLES as $table) {
            $found = $DB->get_fieldset_select(self::T . $table, 'courseid', 'charid = ?', [$charid]);
            $courseids = array_merge($courseids, $found);
        }
        foreach (array_unique($courseids) as $courseid) {
            self::delete_in_course((int) $courseid, [$charid]);
        }
        $DB->delete_records(self::T . 'char', ['id' => $charid]);
    }
}
