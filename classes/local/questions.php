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
 * The quick questions of a mission, for the class screen: read aloud and answered by the class, never shaped like an
 * exam. In the package the first option is the right one; they are shown shuffled.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class questions {
    /** @var string Prefix of the tables. */
    private const T = 'local_oksigeniatesape_';

    /**
     * A question of the mission of a course, in its story and country, with the options in an order that only depends
     * on the seed (the same on every screen and after reloading).
     *
     * @param int $courseid
     * @param string $id
     * @param int $seed
     * @return array|null id, chapter, objective, text, options, correct (index in options), why.
     */
    public static function get(int $courseid, string $id, int $seed): ?array {
        $mission = missions::get($courseid);
        if (!$mission) {
            return null;
        }
        foreach ($mission->data['questions'] ?? [] as $question) {
            if (($question['id'] ?? '') === $id) {
                return self::export($question, $mission, $seed);
            }
        }
        return null;
    }

    /**
     * A question from the chapters the class has reached: those with the lantern of the class lit and the next one.
     *
     * @param int $courseid
     * @param string[] $exclude Ids not to repeat.
     * @return string|null Its id, or null if the mission has none.
     */
    public static function pick(int $courseid, array $exclude = []): ?string {
        global $DB;
        $mission = missions::get($courseid);
        if (!$mission || empty($mission->data['questions'])) {
            return null;
        }
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder', 'id, code, litat');
        $lit = count(array_filter($chapters, fn($c) => (int) $c->litat > 0));
        $open = array_slice(array_map(fn($c) => $c->code, array_values($chapters)), 0, max(1, $lit + 1));
        $all = $mission->data['questions'];
        $candidates = array_filter($all, fn($q) => in_array($q['chapter'] ?? '', $open, true));
        $fresh = array_filter($candidates ?: $all, fn($q) => !in_array($q['id'], $exclude, true));
        $list = array_values($fresh ?: ($candidates ?: $all));
        return $list[random_int(0, count($list) - 1)]['id'];
    }

    /**
     * Several questions without repeating, for a battle: from the chapters the class has reached first, then from the
     * rest of the mission if those are not enough.
     *
     * @param int $courseid
     * @param int $count
     * @return string[] Their ids.
     */
    public static function pick_many(int $courseid, int $count): array {
        $mission = missions::get($courseid);
        $all = $mission ? count($mission->data['questions'] ?? []) : 0;
        $ids = [];
        while (count($ids) < min($count, $all)) {
            $id = self::pick($courseid, $ids);
            if (in_array($id, $ids, true)) {
                // The chapters reached are used up: any other question of the mission.
                $rest = array_values(array_diff(array_column($mission->data['questions'], 'id'), $ids));
                $id = $rest[random_int(0, count($rest) - 1)];
            }
            $ids[] = $id;
        }
        return $ids;
    }

    /**
     * A question as the class screen shows it.
     *
     * @param array $question As in the package.
     * @param \stdClass $mission
     * @param int $seed
     * @return array
     */
    private static function export(array $question, \stdClass $mission, int $seed): array {
        $options = [];
        foreach (array_values($question['options'] ?? []) as $i => $option) {
            $options[] = ['text' => self::text($option, $mission), 'right' => $i === 0,
                'order' => crc32($seed . ':' . $i . ':' . $question['id'])];
        }
        usort($options, fn($a, $b) => $a['order'] <=> $b['order']);
        $correct = 0;
        foreach ($options as $i => $option) {
            if ($option['right']) {
                $correct = $i;
            }
        }
        return [
            'id' => (string) $question['id'],
            'chapter' => (string) ($question['chapter'] ?? ''),
            'objective' => (string) ($question['objective'] ?? ''),
            'text' => self::text($question['text'] ?? '', $mission),
            'options' => array_map(fn($o) => $o['text'], $options),
            'correct' => $correct,
            'why' => self::text($question['why'] ?? '', $mission),
        ];
    }

    /**
     * A text of the package: the same for all, or one for each story (valley, azara) or country (es, py).
     *
     * @param mixed $text
     * @param \stdClass $mission
     * @return string
     */
    private static function text($text, \stdClass $mission): string {
        if (!is_array($text)) {
            return (string) $text;
        }
        return (string) ($text[$mission->skin] ?? $text[$mission->variant] ?? reset($text));
    }
}
