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

namespace local_oksigeniatesape\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniatesape\local\huddles;
use local_oksigeniatesape\local\live;
use local_oksigeniatesape\local\nestboards;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\sessions;
use local_oksigeniatesape\local\teams;
use local_oksigeniatesape\local\untangles;

/**
 * The battle on the class screen: open it, the next question, show the answer, mark the teams that answered aloud,
 * finish it; and how it is going, polled every second and a half.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class live_host extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'action' => new external_value(
                PARAM_ALPHA,
                'state, start, next, reveal, mark, argue, verb, boardplay, settle, finish, startvote, vote, pick, pause or resume;' .
                    ' untangle and go for the great untangling; nest (with groupid) and go for the nest of a team'
            ),
            'mode' => new external_value(PARAM_ALPHA, 'start: phones or aloud', VALUE_DEFAULT, 'phones'),
            'count' => new external_value(PARAM_INT, 'start: questions', VALUE_DEFAULT, 6),
            'secs' => new external_value(PARAM_INT, 'start: seconds per question (0: no limit)', VALUE_DEFAULT, 30),
            'groupid' => new external_value(PARAM_INT, 'mark: the team', VALUE_DEFAULT, 0),
            'right' => new external_value(PARAM_BOOL, 'mark: it got it right', VALUE_DEFAULT, false),
            'sceneid' => new external_value(PARAM_INT, 'startvote: the scene the class plays', VALUE_DEFAULT, 0),
            'text' => new external_value(PARAM_TEXT, 'vote: what the scene asks', VALUE_DEFAULT, ''),
            'options' => new external_value(PARAM_RAW, 'vote: the options, in JSON', VALUE_DEFAULT, '[]'),
            'round' => new external_value(PARAM_INT, 'next and reveal: round on the class screen (-1: any)', VALUE_DEFAULT, -1),
            'final' => new external_value(PARAM_BOOL, 'start: the final battle of the mission', VALUE_DEFAULT, false),
            'turns' => new external_value(PARAM_BOOL, 'start: by turns, with the knots of the mission', VALUE_DEFAULT, false),
            'rehearsal' => new external_value(
                PARAM_BOOL,
                'start: a rehearsal, three test questions without prizes',
                VALUE_DEFAULT,
                false
            ),
            'meta' => new external_value(
                PARAM_RAW,
                'vote: the node and the ids of the options; boardplay: what was played; in JSON',
                VALUE_DEFAULT,
                '{}'
            ),
            'kind' => new external_value(PARAM_ALPHA, 'verb: glance, trail, shield, cheer or carry', VALUE_DEFAULT, ''),
            'target' => new external_value(PARAM_INT, 'verb: the team it goes to', VALUE_DEFAULT, 0),
            'option' => new external_value(PARAM_INT, 'pick: the option of the decision, from 0', VALUE_DEFAULT, -1),
        ]);
    }

    /**
     * Does something with the battle of the course and says how it is going.
     *
     * @param int $courseid
     * @param string $action
     * @param string $mode
     * @param int $count
     * @param int $secs
     * @param int $groupid
     * @param bool $right
     * @param int $sceneid
     * @param string $text
     * @param string $options
     * @param int $round
     * @param bool $final
     * @param bool $turns
     * @param bool $rehearsal
     * @param string $meta
     * @param string $kind
     * @param int $target
     * @param int $option
     * @return array
     */
    public static function execute(
        int $courseid,
        string $action,
        string $mode = 'phones',
        int $count = 6,
        int $secs = 30,
        int $groupid = 0,
        bool $right = false,
        int $sceneid = 0,
        string $text = '',
        string $options = '[]',
        int $round = -1,
        bool $final = false,
        bool $turns = false,
        bool $rehearsal = false,
        string $meta = '{}',
        string $kind = '',
        int $target = 0,
        int $option = -1
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'action' => $action,
            'mode' => $mode,
            'count' => $count,
            'secs' => $secs,
            'groupid' => $groupid,
            'right' => $right,
            'sceneid' => $sceneid,
            'text' => $text,
            'options' => $options,
            'round' => $round,
            'final' => $final,
            'turns' => $turns,
            'rehearsal' => $rehearsal,
            'kind' => $kind,
            'target' => $target,
            'option' => $option,
            'meta' => $meta,
        ]);
        $context = \local_oksigeniatesape\local\games::require_enter($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:runclassscreen', $context);
        $courseid = $params['courseid'];
        $now = time();
        if ($params['action'] === 'state') {
            // The session going on, for the class screen to show what the teacher launches.
            $session = sessions::board($courseid, $now);
            $session = $session ? ['session' => json_encode($session)] : [];
            // How each team says it is doing today, for its lantern on the class screen.
            $session['teams'] = json_encode((object) teams::states($courseid, $now));
            // And which teams play a scene with their tablets joined: a firefly by each.
            $session['huddles'] = json_encode((object) huddles::open_teams($courseid, $now));
            $live = live::recent($courseid, $now, true);
            if (!$live) {
                return $session;
            }
            if (!$live->timeend) {
                live::heartbeat($live, $now);
                // A battle by turns moves on by itself when the time of a stage is over.
                $award = has_capability('local/oksigeniatesape:award', $context);
                $userid = (int) $USER->id;
                $live = live::locked($courseid, fn() => $live->kind === untangles::KIND ?
                    untangles::tick(live::recent($courseid, $now, true) ?? $live, $now) : ($live->kind === nestboards::KIND ?
                    nestboards::tick(live::recent($courseid, $now, true) ?? $live, $now) :
                    live::tick(live::recent($courseid, $now, true) ?? $live, $userid, $award, $now)));
            }
            return ['live' => self::view($live, $now)] + $session;
        }
        if (!regions::enabled($courseid)) {
            throw new \moodle_exception('gameoff', 'local_oksigeniatesape');
        }
        $userid = (int) $USER->id;
        return live::locked($courseid, fn() => self::act($params, $context, $userid, $now));
    }

    /**
     * Does an action of the class screen, with the session read again inside the lock.
     *
     * @param array $params
     * @param \context_course $context
     * @param int $userid
     * @param int $now
     * @return array
     */
    private static function act(array $params, \context_course $context, int $userid, int $now): array {
        $courseid = $params['courseid'];
        $from = $params['round'] >= 0 ? $params['round'] : null;
        if ($params['action'] === 'start') {
            $live = live::start(
                $courseid,
                $params['mode'],
                $params['count'],
                $params['secs'],
                $now,
                $params['final'],
                $params['turns'],
                $params['rehearsal']
            );
            return ['live' => live::board_view($live, $now)];
        }
        if ($params['action'] === 'startvote') {
            $live = live::start_vote($courseid, $params['sceneid'], $now);
            return ['live' => live::board_view($live, $now)];
        }
        if ($params['action'] === 'untangle') {
            require_capability('local/oksigeniatesape:award', $context);
            return ['live' => self::view(untangles::start($courseid, $now), $now)];
        }
        if ($params['action'] === 'nest') {
            require_capability('local/oksigeniatesape:award', $context);
            return ['live' => self::view(nestboards::start($courseid, $params['groupid'], $now), $now)];
        }
        $live = live::current($courseid, $now, true);
        if (!$live) {
            throw new \moodle_exception('nobattle', 'local_oksigeniatesape');
        }
        // Back on the session: the phones see it again.
        live::heartbeat($live, $now);
        if ($live->kind === untangles::KIND || $live->kind === nestboards::KIND) {
            // The great untangling and the nest of a team: started or ended from the class screen; the rest happens on
            // the phones.
            require_capability('local/oksigeniatesape:award', $context);
            $class = $live->kind === untangles::KIND ? untangles::class : nestboards::class;
            if ($params['action'] === 'go') {
                $live = $class::go($live, $now);
            } else if ($params['action'] === 'finish') {
                $live = $class::finish($live, $now);
            } else {
                throw new \invalid_parameter_exception('Not for the great untangling');
            }
            return ['live' => self::view($live, $now)];
        }
        if (in_array($params['action'], ['next', 'reveal'], true)) {
            // To go on by hand is to go on: a pause ends first.
            $live = live::resume($live, $now);
        }
        switch ($params['action']) {
            case 'pause':
                $live = live::pause($live, $now);
                break;
            case 'resume':
                $live = live::resume($live, $now);
                break;
            case 'next':
                $live = live::next($live, $now, $from);
                break;
            case 'reveal':
                $live = live::reveal($live, $now, $from);
                break;
            case 'mark':
                require_capability('local/oksigeniatesape:award', $context);
                $live = live::mark($live, $params['groupid'], $params['right'], $now);
                break;
            case 'argue':
                require_capability('local/oksigeniatesape:award', $context);
                $live = live::argue($live, $params['groupid'], $userid, $now);
                break;
            case 'verb':
                // Marked by who marks the teams: the verbs change the turn as much as the marks.
                require_capability('local/oksigeniatesape:award', $context);
                $live = live::say_verb($live, $params['groupid'], $params['kind'], $params['target'], $now);
                break;
            case 'boardplay':
                $payload = json_decode($params['meta'], true);
                $live = live::board_strike($live, $params['groupid'], is_array($payload) ? $payload : [], $now);
                break;
            case 'settle':
                $live = live::settle($live, $now);
                break;
            case 'pick':
                require_capability('local/oksigeniatesape:award', $context);
                $live = live::pick($live, $params['option'], $now);
                break;
            case 'finish':
                require_capability('local/oksigeniatesape:award', $context);
                if ($live->kind === 'battle' && !(int) $live->round) {
                    // Nobody has played yet: called off, without an outcome.
                    live::cancel($live, $now);
                    return [];
                }
                $live = live::finish($live, $userid, $now);
                break;
            case 'vote':
                $list = json_decode($params['options'], true);
                $meta = json_decode($params['meta'], true);
                $live = live::vote($live, $params['text'], is_array($list) ? $list : [], $now, is_array($meta) ? $meta : []);
                break;
            default:
                throw new \invalid_parameter_exception('Unknown action');
        }
        return ['live' => live::board_view($live, $now)];
    }

    /**
     * What the class screen shows of a session; of the great untangling, also its knot.
     *
     * @param \stdClass $live
     * @param int $now
     * @return array
     */
    private static function view(\stdClass $live, int $now): array {
        $view = live::board_view($live, $now);
        if ($live->kind === untangles::KIND) {
            $view['untangle'] = json_encode(untangles::board($live, $now));
        } else if ($live->kind === nestboards::KIND) {
            $view['nest'] = json_encode(nestboards::board($live, $now));
        }
        return $view;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return new external_single_structure([
            'live' => structures::live_board(),
            'session' => new external_value(PARAM_RAW, 'The session going on, as the class screen shows it (JSON)', VALUE_OPTIONAL),
            'teams' => new external_value(PARAM_RAW, 'How each team says it is doing today, by group (JSON)', VALUE_OPTIONAL),
            'huddles' => new external_value(PARAM_RAW, 'The team sessions open, group => session (JSON)', VALUE_OPTIONAL),
        ]);
    }
}
