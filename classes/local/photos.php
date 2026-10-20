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
 * The photos of the classroom as stickers of the map: a team sends one photo of a place (what it found, its drawings,
 * its notebook; never people), the teacher looks at it before anybody else, and once approved it is on the map of the
 * team and on the class screen. It never leaves the Moodle: it is a file of the course, served only to who may see it.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class photos {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var string Where the photos are kept, in the files of the course. */
    public const FILEAREA = 'photo';

    /** @var int The most a photo can weigh, once made smaller on the tablet. */
    public const MAX_BYTES = 1500000;

    /** @var string[] The kinds of picture taken, with their extension. */
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * A team sends the photo of a place: it waits for the teacher. A new one takes the place of the one before, whatever
     * became of it.
     *
     * @param int $courseid
     * @param int $userid Who sends it.
     * @param string $chapter Code of the place.
     * @param string $dataurl The picture, as a data URL.
     * @param int|null $now
     * @return \stdClass The photo.
     */
    public static function upload(int $courseid, int $userid, string $chapter, string $dataurl, ?int $now = null): \stdClass {
        global $DB;
        $now = $now ?? time();
        $char = characters::get($userid);
        $team = teams::of($courseid, $userid);
        $mission = missions::get($courseid);
        $codes = array_column($mission ? $mission->data['chapters'] ?? [] : [], 'code');
        if (!$char || !$team || !in_array($chapter, $codes, true)) {
            throw new \invalid_parameter_exception('Not a place of a team of the course');
        }
        if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=]+)$#', $dataurl, $m)) {
            throw new \invalid_parameter_exception('Not a picture');
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || !@getimagesizefromstring($bytes)) {
            throw new \moodle_exception('phototoobig', 'local_oksigeniatesape');
        }
        $old = $DB->get_records(self::T . 'photo', ['courseid' => $courseid, 'groupid' => $team->id, 'chapter' => $chapter]);
        self::remove(array_keys($old), $courseid);
        $id = $DB->insert_record(self::T . 'photo', (object) ['courseid' => $courseid, 'groupid' => $team->id,
            'chapter' => $chapter, 'charid' => $char->id, 'status' => 'pending', 'reviewedby' => 0, 'timecreated' => $now,
            'timemodified' => $now]);
        get_file_storage()->create_file_from_string(self::file($courseid, $id, 'photo.' . self::MIMES[$m[1]]), $bytes);
        return $DB->get_record(self::T . 'photo', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * The teacher looks at a photo: approved, it goes on the map; not approved, it goes away, and the team knows it.
     *
     * @param int $courseid
     * @param int $photoid
     * @param bool $ok
     * @param int $actorid
     * @param int|null $now
     */
    public static function review(int $courseid, int $photoid, bool $ok, int $actorid, ?int $now = null): void {
        global $DB;
        $photo = $DB->get_record(self::T . 'photo', ['id' => $photoid, 'courseid' => $courseid], '*', MUST_EXIST);
        if (!$ok) {
            get_file_storage()->delete_area_files(
                \context_course::instance($courseid)->id,
                'local_oksigeniatesape',
                self::FILEAREA,
                $photoid
            );
        }
        $DB->update_record(self::T . 'photo', (object) ['id' => $photo->id, 'status' => $ok ? 'approved' : 'rejected',
            'reviewedby' => $actorid, 'timemodified' => $now ?? time()]);
    }

    /**
     * The photos of a team, as its tablets see them: by place, what became of each one, and the picture once approved.
     *
     * @param int $courseid
     * @param int $userid
     * @return array Code of the place => status, url (only if approved), mine.
     */
    public static function of_team(int $courseid, int $userid): array {
        global $DB;
        $team = teams::of($courseid, $userid);
        $char = characters::get($userid);
        $out = [];
        foreach ($team ? $DB->get_records(self::T . 'photo', ['courseid' => $courseid, 'groupid' => $team->id]) : [] as $p) {
            $out[$p->chapter] = ['status' => $p->status, 'url' => $p->status === 'approved' ? self::url($p) : '',
                'mine' => $char && (int) $p->charid === (int) $char->id];
        }
        return $out;
    }

    /**
     * The photos of the course for the teacher and the class screen: those waiting, with their picture, and those on the
     * map.
     *
     * @param int $courseid
     * @param bool $approved Only those on the map (the class screen).
     * @return array List of id, team, chapter (code), place, status, url.
     */
    public static function of_course(int $courseid, bool $approved = false): array {
        global $DB;
        $where = $approved ? ['courseid' => $courseid, 'status' => 'approved'] : ['courseid' => $courseid];
        $mission = missions::get($courseid);
        $places = [];
        foreach ($mission ? $mission->data['chapters'] ?? [] : [] as $chapter) {
            $places[$chapter['code']] = missions::text($chapter['name'] ?? $chapter['code'], $mission->skin, $mission->variant);
        }
        $teams = groups_get_all_groups($courseid);
        $out = [];
        foreach ($DB->get_records(self::T . 'photo', $where, 'timecreated') as $p) {
            if ($p->status === 'rejected') {
                continue;
            }
            $out[] = ['id' => (int) $p->id, 'team' => isset($teams[$p->groupid]) ? format_string($teams[$p->groupid]->name) : '',
                'chapter' => (string) $p->chapter, 'place' => $places[$p->chapter] ?? $p->chapter, 'status' => $p->status,
                'url' => self::url($p)];
        }
        return $out;
    }

    /**
     * A photo for who asks for it: the teacher (and the class screen) always; a student, only an approved one of their
     * team.
     *
     * @param \context_course $context
     * @param array $args itemid, filename.
     * @param array $options
     * @return bool False if it is not to be served.
     */
    public static function serve(\context_course $context, array $args, array $options): bool {
        global $DB, $USER;
        $photoid = (int) array_shift($args);
        $photo = $DB->get_record(self::T . 'photo', ['id' => $photoid, 'courseid' => $context->instanceid]);
        if (!$photo || !self::can_see($photo, $context, (int) $USER->id)) {
            return false;
        }
        $file = get_file_storage()->get_file(
            $context->id,
            'local_oksigeniatesape',
            self::FILEAREA,
            $photoid,
            '/',
            (string) array_pop($args)
        );
        if (!$file) {
            return false;
        }
        send_stored_file($file, 0, 0, false, $options);
        return true;
    }

    /**
     * Who may see a photo: the teacher (and the class screen) always; a student, only an approved one of their team.
     *
     * @param \stdClass $photo
     * @param \context_course $context
     * @param int $userid
     * @return bool
     */
    public static function can_see(\stdClass $photo, \context_course $context, int $userid): bool {
        if (
            has_capability('local/oksigeniatesape:award', $context, $userid) ||
            has_capability('local/oksigeniatesape:runclassscreen', $context, $userid)
        ) {
            return true;
        }
        $team = teams::of((int) $context->instanceid, $userid);
        return $photo->status === 'approved' && $team && (int) $team->id === (int) $photo->groupid;
    }

    /**
     * Takes photos away, with their pictures.
     *
     * @param int[] $ids
     * @param int $courseid
     */
    public static function remove(array $ids, int $courseid): void {
        global $DB;
        if (!$ids) {
            return;
        }
        // A course deleted has no context any more: Moodle deleted its files with it.
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        foreach ($context ? $ids : [] as $id) {
            get_file_storage()->delete_area_files($context->id, 'local_oksigeniatesape', self::FILEAREA, (int) $id);
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $ids));
        $DB->delete_records_select(self::T . 'photo', "id $insql", $params);
    }

    /**
     * Takes away the photos of a course, all of them or those some characters sent.
     *
     * @param int $courseid
     * @param int[]|null $charids
     */
    public static function remove_in_course(int $courseid, ?array $charids = null): void {
        global $DB;
        $where = 'courseid = ?';
        $params = [$courseid];
        if ($charids !== null) {
            if (!$charids) {
                return;
            }
            [$insql, $charparams] = $DB->get_in_or_equal($charids);
            $where .= " AND charid $insql";
            $params = array_merge($params, $charparams);
        }
        self::remove($DB->get_fieldset_select(self::T . 'photo', 'id', $where, $params), $courseid);
    }

    /**
     * Where the picture of a photo is kept.
     *
     * @param int $courseid
     * @param int $photoid
     * @param string $filename
     * @return array
     */
    private static function file(int $courseid, int $photoid, string $filename): array {
        return ['contextid' => \context_course::instance($courseid)->id, 'component' => 'local_oksigeniatesape',
            'filearea' => self::FILEAREA, 'itemid' => $photoid, 'filepath' => '/', 'filename' => $filename];
    }

    /**
     * The address of the picture of a photo.
     *
     * @param \stdClass $photo
     * @return string Empty if it has none.
     */
    private static function url(\stdClass $photo): string {
        $context = \context_course::instance((int) $photo->courseid);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_oksigeniatesape',
            self::FILEAREA,
            (int) $photo->id,
            'filename',
            false
        );
        $file = reset($files);
        return $file ? \moodle_url::make_pluginfile_url(
            $context->id,
            'local_oksigeniatesape',
            self::FILEAREA,
            (int) $photo->id,
            '/',
            $file->get_filename()
        )->out(false) : '';
    }
}
