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
 * A stop on the way: in each place, once the student has played one of its scenes, they stop once, and choose: rest by
 * the fire (breath back) or listen to who lives there (a short tale of the place). Nothing at random: what they choose is
 * what they get.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stops {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string[] What can be done at a stop. */
    public const CHOICES = ['rest', 'listen'];

    /** @var int Breath that resting by the fire gives back. */
    public const BREATH = 2;

    /**
     * The stop of a place for a student: who speaks there and their tale, whether they can stop already and what they
     * chose, if they did.
     *
     * @param \stdClass $mission
     * @param array $meta The place in the mission.
     * @param array $found What the student found in the mission: kind => code => true.
     * @param bool $played They played a scene of the place.
     * @return array|null speaker, text, open, taken.
     */
    public static function view(\stdClass $mission, array $meta, array $found, bool $played): ?array {
        $stop = $meta['stop'] ?? null;
        if (!$stop) {
            return null;
        }
        $taken = '';
        foreach (self::CHOICES as $choice) {
            if (!empty($found['stop'][$meta['code'] . ':' . $choice])) {
                $taken = $choice;
            }
        }
        $speaker = missions::text($mission->data['speakers'][$stop['speaker']] ?? $stop['speaker'], (string) $mission->skin);
        return [
            'speaker' => $speaker,
            'to' => self::to($speaker),
            'text' => missions::text($stop['text'] ?? '', (string) $mission->skin, (string) $mission->variant),
            'open' => $played,
            'taken' => $taken,
        ];
    }

    /**
     * Who someone is inside a sentence that goes to them («listen to…»): in Spanish, with the preposition and the article
     * as the language joins them (a la druida mayor, al barquero, a Doña Hebra); elsewhere, the name.
     *
     * @param string $name As the mission says it, at the start of a line.
     * @return string
     */
    public static function to(string $name): string {
        if (strpos(current_language(), 'es') !== 0) {
            return $name;
        }
        if (preg_match('/^El (.*)$/u', $name, $m)) {
            return 'al ' . $m[1];
        }
        if (preg_match('/^(La|Los|Las) (.*)$/u', $name, $m)) {
            return 'a ' . \core_text::strtolower($m[1]) . ' ' . $m[2];
        }
        return 'a ' . $name;
    }

    /**
     * A student stops in a place: once, after playing one of its scenes.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $place Code of the place.
     * @param string $choice rest or listen.
     * @param int|null $now
     * @return int Breath given back.
     */
    public static function take(int $courseid, int $userid, string $place, string $choice, ?int $now = null): int {
        global $DB;
        $now = $now ?? time();
        $mission = missions::get($courseid);
        $char = characters::get($userid);
        $meta = [];
        foreach ($mission ? $mission->data['chapters'] ?? [] : [] as $chapter) {
            if ((string) $chapter['code'] === $place) {
                $meta = $chapter;
            }
        }
        if (!$char || empty($meta['stop']) || !in_array($choice, self::CHOICES, true)) {
            throw new \invalid_parameter_exception('Not a stop of the mission');
        }
        $chapterid = (int) $DB->get_field(self::T . 'chapter', 'id', ['missionid' => $mission->id, 'code' => $place]);
        $played = $DB->record_exists_sql(
            'SELECT 1 FROM {' . self::T . 'sceneattempt} a JOIN {' . self::T . 'scene} s ON s.id = a.sceneid
              WHERE a.charid = ? AND a.courseid = ? AND s.chapterid = ?',
            [$char->id, $courseid, $chapterid]
        );
        $where = "charid = ? AND missionid = ? AND kind = 'stop' AND " . $DB->sql_like('code', '?');
        if (!$played || $DB->record_exists_select(self::T . 'found', $where, [$char->id, $mission->id, $place . ':%'])) {
            throw new \moodle_exception('stopclosed', 'local_oksigeniatesape');
        }
        $DB->insert_record(self::T . 'found', (object) ['charid' => $char->id, 'courseid' => $courseid,
            'missionid' => $mission->id, 'kind' => 'stop', 'code' => $place . ':' . $choice, 'timecreated' => $now]);
        if ($choice !== 'rest') {
            return 0;
        }
        $member = engine::member($char, $courseid, $now);
        $entry = engine::breath($member, self::BREATH, 'stop', 0, 0, $now);
        return $entry ? (int) $entry->amount : 0;
    }
}
