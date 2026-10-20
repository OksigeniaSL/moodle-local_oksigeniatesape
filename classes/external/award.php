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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_oksigeniatesape\local\characters;
use local_oksigeniatesape\local\classroom;
use local_oksigeniatesape\local\engine;
use local_oksigeniatesape\local\regions;
use local_oksigeniatesape\local\view;

/**
 * Gives knowledge, gems or breath to some students, or takes energy for a commitment not kept.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class award extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course'),
            'userids' => new external_multiple_structure(new external_value(PARAM_INT, 'Student')),
            'resource' => new external_value(PARAM_ALPHA, 'saber, gems, breath or energy'),
            'amount' => new external_value(PARAM_INT, 'How much; negative only for energy'),
            'note' => new external_value(PARAM_TEXT, 'Why, as the teacher chose it: the student sees it', VALUE_DEFAULT, ''),
            'opid' => new external_value(
                PARAM_ALPHANUMEXT,
                'Made by the device for this touch: sent again without a connection, it counts once',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Gives knowledge, gems or breath to some students, or takes energy for a commitment not kept.
     *
     * @param int $courseid
     * @param int[] $userids
     * @param string $resource
     * @param int $amount
     * @param string $note
     * @param string $opid Made by the device for this touch.
     * @return array
     */
    public static function execute(
        int $courseid,
        array $userids,
        string $resource,
        int $amount,
        string $note = '',
        string $opid = ''
    ): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userids' => $userids,
            'resource' => $resource,
            'amount' => $amount,
            'note' => $note,
            'opid' => $opid,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/oksigeniatesape:award', $context);
        if (!in_array($params['resource'], ['saber', 'gems', 'breath', 'energy'], true)) {
            throw new \invalid_parameter_exception('Unknown resource');
        }
        if ($params['amount'] == 0 || ($params['amount'] < 0 && $params['resource'] !== 'energy')) {
            throw new \invalid_parameter_exception('Only energy can be taken away, and something has to be given');
        }
        view::require_students($params['courseid'], $params['userids']);
        $results = [];
        foreach ($params['userids'] as $userid) {
            $char = characters::get($userid);
            if (!$char) {
                $results[] = ['userid' => $userid, 'status' => 'nocharacter'];
                continue;
            }
            $member = engine::member($char, $params['courseid']);
            $reason = $params['resource'] === 'energy' && $params['amount'] < 0 ? 'commitment' : 'manual';
            $method = $params['resource'];
            // The same touch, sent again because the answer was lost on the way: given once.
            $entry = $params['opid'] !== '' ? $DB->get_record('local_oksigeniatesape_ledger', ['opid' => $params['opid'],
                'charid' => $char->id]) : null;
            if (!$entry) {
                $entry = engine::$method($member, $params['amount'], $reason, (int) $USER->id);
                $note = \core_text::substr(trim($params['note']), 0, 255);
                if ($entry && !empty($entry->id) && ($note !== '' || $params['opid'] !== '')) {
                    $DB->update_record('local_oksigeniatesape_ledger', (object) ['id' => $entry->id,
                        'note' => $note !== '' ? $note : null, 'opid' => $params['opid'] !== '' ? $params['opid'] : null]);
                }
            }
            if (!$entry) {
                $results[] = ['userid' => $userid, 'status' => $member->status === 'paused' ? 'paused' : 'capped'];
                continue;
            }
            // How the student is left, so that the panel draws it at once without reading the whole class again.
            $fresh = $DB->get_record('local_oksigeniatesape_member', ['id' => $member->id], 'energy, breath, status');
            $results[] = [
                'userid' => $userid,
                'status' => $entry->capped && !$entry->amount ? 'capped' : 'given',
                'amount' => (int) $entry->amount,
                'level' => (int) $DB->get_field('local_oksigeniatesape_char', 'level', ['id' => $char->id]),
                'energy' => (int) $fresh->energy,
                'breath' => (int) $fresh->breath,
                'state' => (string) $fresh->status,
            ];
        }
        return $results;
    }

    /**
     * What it returns.
     *
     * @return \core_external\external_description
     */
    public static function execute_returns(): \core_external\external_description {
        return structures::results();
    }
}
