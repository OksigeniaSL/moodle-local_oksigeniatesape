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
 * What the game client and the teacher panel see: the world of a student, and the class of a teacher.
 *
 * Who sees what follows the design: classmates see each other's character and role, energy only inside the team,
 * and never why someone fell or was paused; the teacher sees everything of their course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view {
    /** @var string Prefix of the plugin tables. */
    private const T = 'local_oksigeniatesape_';

    /** @var int Days away after which the creature is asleep when the student comes back (it wakes when touched). */
    private const ASLEEP_DAYS = 7;

    /** @var string[] Roles that see which teammates are low on energy, because they can help them. */
    public const CARERS = ['healer', 'protector'];

    /** @var int Entries of the log a student sees. */
    public const OWN_LOG = 20;

    /**
     * The world of a student in a course.
     *
     * @param int $courseid
     * @param int $userid
     * @return array
     */
    public static function world(int $courseid, int $userid): array {
        global $DB;
        $region = regions::get($courseid);
        $enabled = $region && $region->enabled;
        $char = characters::get($userid);
        $world = [
            'enabled' => $enabled,
            'family' => $region->family ?? 'storyheroes',
            'nicknames' => (int) ($region->nicknames ?? 0),
            // What puts a student in pause, agreed with the class: they can read it on their sheet.
            'pauses' => regions::pauses($region),
            'quiet' => (bool) ($region->quiet ?? false),
            'devicemusic' => (int) ($region->devicemusic ?? 1),
            'energymax' => engine::ENERGY_MAX,
            'breathmax' => engine::BREATH_MAX,
            'challenges' => [],
            'items' => [],
            'log' => [],
            'activities' => [],
            'powers' => [],
            'powerrequests' => [],
            'standings' => [],
            'ideas' => [],
            'hascoat' => false,
        ];
        // In infant school, the creature of the class: the student plays on the class screen, without a character.
        $creature = classcreature::view($courseid);
        if ($creature) {
            $world['classcreature'] = $creature;
        }
        if (!$char) {
            // Who comes in for the first time: the prologue of the mission, before choosing a role.
            $opening = $enabled ? self::opening($courseid) : null;
            if ($opening) {
                $world['opening'] = $opening;
            }
            return $world;
        }
        $member = $enabled ? engine::member($char, $courseid) : null;
        $world['character'] = self::character($char, $member, true);
        if (!$member) {
            return $world;
        }
        $team = self::team($courseid, $userid, (int) ($region->groupingid ?? 0), $region);
        if ($team) {
            $team['week'] = teams::week($courseid, $team['id']);
            $names = array_column($team['members'], 'name', 'userid');
            $pacts = pacts::for_team($courseid, (object) ['id' => $team['id']], $userid, $names);
            if ($pacts) {
                $team['pacts'] = $pacts;
            }
            $world['team'] = $team;
        }
        if ($region->leaderboard) {
            $world['standings'] = array_map(fn($t) => ['id' => $t['id'], 'name' => $t['name'], 'saber' => $t['saber'],
                'goal' => $t['goal'], 'reached' => $t['reached']], teams::standings($courseid));
        }
        $powers = powers::for_student($char, $member);
        $world['powers'] = $powers['powers'];
        $world['powerrequests'] = $powers['requests'];
        $world['activities'] = activities::for_student($courseid, $userid, $member);
        $world['ideas'] = ideas::mine($courseid, (int) $char->id);
        // Once the creature has its branch, its coat is the one of the branch.
        $world['hascoat'] = minigames::has_coat($char, $courseid) && creatures::branch($char) === '';
        $world['names'] = [
            'heroes' => (bool) $region->nicknames,
            'own' => (int) $region->nicknames === 2,
            'hero' => $region->nicknames ? names::hero_options($char) : [],
            'creature' => $char->creature ? names::creature_options($char) : [],
            'heropending' => (string) $char->nicknamepending,
            'creaturepending' => (string) ((json_decode((string) $char->creature, true) ?: [])['pending'] ?? ''),
        ];
        $page = daily::today($char, $member);
        if ($page) {
            $world['page'] = $page;
        }
        $badge = badges::for_student($courseid, $userid);
        if ($badge) {
            $world['badge'] = $badge;
        }
        $world['market'] = market::for_student($char, $courseid, $userid);
        // The shop of the naturalist: what they can read beyond the mission, and the seals of what they read.
        $world['curiosities'] = curiosities::for_student($courseid, $char);
        // The first look, while they have not done it.
        $first = $member ? entry::for_student($courseid, $member) : null;
        if ($first) {
            $world['entry'] = $first;
        }
        $album = album::for_student($courseid, $userid);
        if ($album) {
            $world['album'] = $album;
        }
        $museum = museum::of_course($courseid);
        if ($museum) {
            $world['museum'] = $museum;
        }
        $world['bestiary'] = daily::bestiary($char, $courseid);
        $fields = 'challengeid, status, note';
        $validations = $DB->get_records(self::T . 'validation', ['charid' => $char->id, 'courseid' => $courseid], '', $fields);
        $group = teams::of($courseid, $userid);
        foreach (classroom::list($courseid) as $challenge) {
            if (!in_array((string) $member->step, explode(',', $challenge->steps), true)) {
                continue;
            }
            $validation = $validations[$challenge->id] ?? null;
            $world['challenges'][] = [
                'id' => (int) $challenge->id,
                'name' => format_string($challenge->name),
                'description' => format_text((string) $challenge->description, FORMAT_PLAIN),
                'saber' => (int) $challenge->saber,
                'gems' => (int) $challenge->gems,
                'teamwork' => (bool) $challenge->teamwork,
                'status' => $validation->status ?? '',
                'note' => $validation ? (string) $validation->note : '',
                // A team task with parts: each with its role, and whether it is done in their team.
                'parts' => $group ? classroom::team_parts($challenge, (int) $group->id, $userid) : [],
            ];
        }
        // Each with where it is worn, where it could be and what it does so.
        $kinds = missions::get($courseid)->data['items'] ?? [];
        $world['items'] = array_values(array_map(fn($i) => ['code' => $i->code, 'name' => format_string($i->name),
            'description' => (string) $i->description, 'slot' => (string) $i->slot,
            'fits' => equipment::fits($courseid, (string) $i->code),
            'worn' => (string) ($kinds[$i->code]['worn'] ?? '')], $DB->get_records_sql(
                'SELECT i.id, i.code, i.name, i.description, v.slot
               FROM {' . self::T . 'inventory} v
               JOIN {' . self::T . 'item} i ON i.id = v.itemid
              WHERE v.charid = ? AND v.courseid = ?
           ORDER BY v.timecreated',
                [$char->id, $courseid]
            )));
        $world['items'] = self::traded_items($world['items'], $courseid, $group ? (int) $group->id : 0);
        // A lantern that waited ready for the teacher too long lights by itself.
        $installed = missions::get($courseid);
        if ($installed) {
            sessions::light_ready((int) $installed->id, time(), true);
        }
        $mission = self::mission($courseid, $char, $member);
        if ($mission) {
            $world['mission'] = $mission;
            // What the Embrollo stained in the valley, to clean; the knot of a team, only for its members.
            $myteam = (int) (teams::of($courseid, (int) $char->userid)->id ?? 0);
            $stained = array_map(function ($s) use ($installed, $char, $myteam) {
                if ((int) $s->groupid && (int) $s->groupid !== $myteam) {
                    return null;
                }
                $view = stains::view($s, $installed);
                if ($view && $view['kind'] === 'page') {
                    $view['tried'] = stains::tried($char, $s, $installed);
                }
                if ($view) {
                    $view['forteam'] = !empty($view['groupid']);
                    unset($view['groupid']);
                }
                return $view;
            }, stains::open($courseid));
            $world['stains'] = array_values(array_filter($stained));
            // Caring for the creature: whether it was shown a card today, and the one it remembers from some days ago.
            if (creatures::traits($char)) {
                $world['petcare'] = ['shown' => creatures::shown_today($char, $installed, time()),
                    'remembers' => creatures::remembers($char, $installed, time())];
            }
            // The card of the day, once the teacher drew it.
            $card = events::for_students($courseid);
            if ($card) {
                $world['card'] = $card;
            }
            // The photos of the classroom of the team, by place: stickers of the map once the teacher approves them; and
            // whether this student may send one.
            $world['sendphoto'] = has_capability('local/oksigeniatesape:sendphoto', games::context($courseid));
            $world['photos'] = [];
            foreach ($myteam ? photos::of_team($courseid, (int) $char->userid) : [] as $code => $photo) {
                $world['photos'][] = ['chapter' => (string) $code] + $photo;
            }
            // The libraries of other regions of the site whose teachers share them.
            $world['libraries'] = museum::shared($courseid);
            // The gift of the place where the class is now, for the team.
            $gift = gifts::for_student($courseid, (int) $char->userid);
            if ($gift) {
                $world['gift'] = $gift;
            }
            // Be the Embrollo: the tangle of the team this week, and those of other teams to undo.
            $world['tangles'] = self::tangles($courseid, (int) $char->userid);
            if (!empty($world['team'])) {
                // Where each one of the team is: the place where they last played, for the map (without names).
                $at = self::team_places($courseid, (int) $mission['id'], array_column($world['team']['members'], 'userid'));
                foreach ($world['team']['members'] as &$mate) {
                    $mate['at'] = $at[$mate['userid']] ?? 0;
                }
                unset($mate);
            }
        }
        $world['expeditions'] = self::expeditions($courseid, (int) $char->id);
        $entries = $DB->get_records(
            self::T . 'ledger',
            ['charid' => $char->id, 'courseid' => $courseid],
            'timecreated DESC, id DESC',
            '*',
            0,
            self::OWN_LOG
        );
        $world['log'] = array_values(array_map(fn($e) => self::entry($e), $entries));
        // Back after a long time, or arriving late: a welcome and a short way to catch up. And now it is noted.
        $welcome = welcome::for_student($courseid, $char, $member, $world, time());
        if ($welcome) {
            $world['welcome'] = $welcome;
        }
        // Who follows from far: the battles of the class since they last came in.
        $missed = !empty($member->remote) ? live::missed($courseid, (int) ($member->lastseen ?? 0), (int) $char->id) : [];
        if ($missed) {
            $world['battles'] = $missed;
        }
        // And the last battle they were not in, to play its questions in deferred.
        $deferred = deferred::pending($courseid, $char, $member);
        if ($deferred) {
            $world['deferred'] = $deferred;
        }
        welcome::seen($member, time());
        return $world;
    }

    /**
     * How many pages of the Gran Libro a student of a role can get, out of those the scenes give.
     *
     * @param array $data The mission.
     * @param array $summaries Of scenes::summaries().
     * @param string $role
     * @return int
     */
    private static function pagecount(array $data, array $summaries, string $role): int {
        $reachable = [];
        foreach ($summaries as $summary) {
            foreach ($summary['pages'] ?? [] as $page => $roles) {
                if (in_array('', $roles, true) || in_array($role, $roles, true)) {
                    $reachable[$page] = true;
                }
            }
        }
        return count(array_intersect_key($data['pages'] ?? [], $reachable));
    }

    /**
     * The mission of a course as a student sees it: the map with its chapters and lanterns, the scenes of their step
     * and what they have done, and the pages of the book they have found.
     *
     * @param int $courseid
     * @param \stdClass $char
     * @param \stdClass $member
     * @param \stdClass|null $mission An expedition of the course (the current one if none).
     * @return array|null
     */
    public static function mission(int $courseid, \stdClass $char, \stdClass $member, ?\stdClass $mission = null): ?array {
        global $DB;
        $current = $mission === null;
        $mission = $mission ?? missions::get($courseid);
        if (!$mission) {
            return null;
        }
        $data = $mission->data;
        $skin = $mission->skin;
        $found = [];
        foreach ($DB->get_records(self::T . 'found', ['charid' => $char->id, 'missionid' => $mission->id]) as $f) {
            $found[$f->kind][$f->code] = true;
        }
        // The plays of each scene and the endings reached in it (to know which can still be made better), in one query.
        $attempts = [];
        $reached = [];
        $rows = $DB->get_recordset_sql(
            'SELECT sceneid, ending, COUNT(1) AS plays
               FROM {' . self::T . 'sceneattempt}
              WHERE charid = ? AND courseid = ?
           GROUP BY sceneid, ending',
            [$char->id, $courseid]
        );
        foreach ($rows as $row) {
            $attempts[$row->sceneid] = ($attempts[$row->sceneid] ?? 0) + (int) $row->plays;
            $reached[$row->sceneid][] = $row->ending;
        }
        $rows->close();
        $chapters = $DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder');
        // Without their definitions: what the map shows of them is kept apart.
        $fields = 'id, missionid, chapterid, code, step, quest';
        $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code', $fields);
        $summaries = scenes::summaries($mission);
        // The scene a battle lost opens is there only while the Embrollo is out, until the class wins the rematch.
        $escaped = !empty(regions::get($courseid)->battlestate);
        $scenes = array_filter($scenes, fn($s) => $escaped || ($summaries[(int) $s->id]['after'] ?? '') !== 'lost');
        $challenges = $DB->get_records_select_menu(
            self::T . 'challenge',
            'courseid = ? AND cmid = 0 AND chapterid > 0',
            [$courseid],
            '',
            'chapterid, id'
        );
        $info = [];
        foreach ($data['chapters'] ?? [] as $chapter) {
            $info[$chapter['code']] = $chapter;
        }
        $sceneview = function ($scene) use ($found, $attempts, $reached, $summaries) {
            $sum = $summaries[(int) $scene->id] ?? ['title' => $scene->code, 'minutes' => 0, 'mode' => 'both', 'best' => []];
            $done = !empty($found['scene'][$scene->code]);
            // Done with a partial ending only: playing it again can make it better.
            $best = array_intersect($reached[$scene->id] ?? [], $sum['best']);
            return [
                'id' => (int) $scene->id,
                'code' => $scene->code,
                'title' => $sum['title'],
                'minutes' => $sum['minutes'],
                'step' => (int) $scene->step,
                'done' => $done,
                'better' => $done && !$best,
                'attempts' => (int) ($attempts[$scene->id] ?? 0),
                'mode' => $sum['mode'],
                'after' => (string) ($sum['after'] ?? ''),
            ];
        };
        $todaycode = $current ? (string) (pacts::chapter($mission)->code ?? '') : '';
        // The Embrollo of each place: the first knot of the place, for when the student closes it.
        $codes = array_column($data['chapters'] ?? [], 'code');
        $embrollos = [];
        foreach ($data['battle']['knots'] ?? [] as $knot) {
            $code = $codes[(int) ($knot['chapter'] ?? 0) - 1] ?? '';
            if ($code !== '' && !isset($embrollos[$code])) {
                $embrollos[$code] = ['name' => missions::text($knot['name'] ?? '', $skin),
                    'claim' => missions::text($knot['claim'] ?? '', $skin), 'laugh' => missions::text($knot['laugh'] ?? '', $skin),
                    'weakness' => missions::text($knot['weakness'] ?? '', $skin)];
            }
        }
        $secretsfound = secrets::found($char, $mission);
        // The ways of the team into the places, and whether this student chooses them.
        $myteam = teams::of($courseid, (int) $char->userid);
        $ways = $myteam ? routes::chosen((int) $myteam->id) : [];
        $chooser = $myteam && routes::chooser($courseid, (int) $myteam->id, $char);
        $list = [];
        foreach ($chapters as $chapter) {
            $meta = $info[$chapter->code] ?? [];
            $here = array_values(array_filter($scenes, fn($s) => $s->chapterid == $chapter->id));
            $mine = scenes::for_step($here, (int) $member->step);
            $games = [];
            foreach ($meta['games'] ?? [] as $game) {
                if (isset($data['games'][$game])) {
                    $games[] = ['code' => $game, 'title' => missions::text($data['games'][$game]['title'] ?? $game, $skin),
                        'type' => (string) ($data['games'][$game]['type'] ?? 'nest'), 'done' => !empty($found['game'][$game])];
                }
            }
            $list[] = [
                'id' => (int) $chapter->id,
                'code' => $chapter->code,
                'games' => $games,
                'name' => missions::text($meta['name'] ?? $chapter->code, $skin),
                'intro' => missions::text($meta['intro'] ?? '', $skin, $mission->variant),
                'x' => (int) ($meta['x'] ?? 0),
                'y' => (int) ($meta['y'] ?? 0),
                'landmark' => (string) ($meta['landmark'] ?? ''),
                'art' => (string) ($meta['art'] ?? $chapter->code),
                // Where each thing of the place is on its painting, in percent.
                'spots' => array_map(fn($target, $xy) => ['target' => (string) $target, 'x' => (int) ($xy[0] ?? 50),
                    'y' => (int) ($xy[1] ?? 50)], array_keys($meta['spots'] ?? []), array_values($meta['spots'] ?? [])),
                'lit' => (bool) $chapter->litat,
                'skipped' => !empty($chapter->skippedat),
                'mine' => !empty($found['lantern'][$chapter->code]),
                'done' => !empty($found['chapter'][$chapter->code]),
                'challengeid' => (int) ($challenges[$chapter->id] ?? 0),
                'letter' => $chapter->litat ? today::letter($mission, $chapter->code) : '',
                'teaser' => $chapter->litat ? today::teaser($mission, $chapter->code) : '',
                // A being that peeks out today, in a place the class has opened (lit, or the place of today).
                'encounter' => $current && ($chapter->litat || $chapter->code === $todaycode) ?
                    (($being = encounters::today($char, $mission, (string) $chapter->code)) ? encounters::view(
                        $being,
                        $mission,
                        encounters::crossed($char, $mission, (string) $chapter->code)
                    ) : null) : null,
                // The secrets of the place still hidden for the student, in its painting.
                'secrets' => secrets::in_place($char, $mission, (string) $chapter->code, $secretsfound),
                // A minion of the Embrollo on the road to the place the class is opening, until the class lights it.
                'minion' => $current ? minions::on_road($char, $mission, (string) $chapter->code) : null,
                // The Embrollo comes out when the student lights the lantern of the place with its core scene.
                'embrollo' => isset($embrollos[$chapter->code]) ? $embrollos[$chapter->code] + ['letter' =>
                    today::letter($mission, $chapter->code)] : null,
                // The place, to walk it: its ground, its traces and its hideouts, if the mission has them.
                'stage' => isset($data['stages'][$chapter->code]) ? json_encode($data['stages'][$chapter->code]) : '',
                // What happened here: a line for each mark the student left in this place.
                'memory' => array_values(array_map(
                    fn($m) => missions::text($m['text'] ?? '', $skin, $mission->variant),
                    array_filter($meta['memory'] ?? [], fn($m) => !empty($found['flag'][$m['flag'] ?? '']))
                )),
                'scenes' => array_map($sceneview, $mine),
                // The quests of the place, for whoever wants more, whatever their step.
                'quests' => array_map($sceneview, scenes::quests($here)),
                // The way of the team into the place: to choose while the class is in it, and how the team arrived.
                'route' => $myteam ? routes::view(
                    $mission,
                    $meta,
                    (string) ($ways[$chapter->code] ?? ''),
                    $chapter->code === $todaycode,
                    $chooser
                ) : null,
                // A stop on the way, once they played a scene of the place: rest by the fire or listen to who lives there.
                'stop' => stops::view(
                    $mission,
                    $meta + ['code' => $chapter->code],
                    $found,
                    (bool) array_filter($here, fn($s) => !empty($attempts[$s->id]))
                ),
            ];
            // The second way lists the games and the quests of the place the other way round.
            $last = &$list[count($list) - 1];
            if (!empty($last['route']['chosen']) && $last['route']['chosen'] === (string) ($meta['routes'][1]['code'] ?? '')) {
                $last['games'] = array_reverse($last['games']);
                $last['quests'] = array_reverse($last['quests']);
            }
            unset($last);
            foreach (['embrollo', 'encounter', 'minion', 'stop', 'route'] as $optional) {
                if ($list[count($list) - 1][$optional] === null) {
                    unset($list[count($list) - 1][$optional]);
                }
            }
        }
        $pages = [];
        foreach ($data['pages'] ?? [] as $code => $page) {
            if (!empty($found['page'][$code])) {
                $pages[] = ['code' => $code, 'title' => (string) $page['title'], 'text' => (string) $page['text']];
            }
        }
        return [
            'id' => (int) $mission->id,
            'code' => $mission->code,
            'title' => missions::text($data['title'] ?? $mission->code, $skin),
            'subtitle' => missions::text($data['subtitle'] ?? '', $skin),
            'prologue' => missions::text($data['prologue'] ?? '', $skin, $mission->variant),
            'book' => missions::text($data['book'] ?? '', $skin),
            'skin' => $skin,
            'variant' => $mission->variant,
            // The pages this student can get: some are given only by an option of a role that is not theirs.
            'pagecount' => self::pagecount($data, $summaries, (string) $char->role),
            // The painted map: that of the mission, or of the world a mission of a teacher lives in; or a map in layers.
            'map' => (string) ($data['map'] ?? $mission->code),
            'mapworld' => (string) ($data['maplayers']['world'] ?? ''),
            'mapheight' => (int) ($data['maplayers']['height'] ?? 0),
            'seasons' => self::seasons($mission),
            'chapters' => $list,
            'pages' => $pages,
            'secrets' => secrets::album($mission, $secretsfound),
            // The secrets of the map itself, between the places (an owl dozing in a pine): those still hidden.
            'mapsecrets' => secrets::in_place($char, $mission, 'map', $secretsfound),
            // The glossary of the scenes: the key words, with their forms, a short definition and a drawing if any.
            'glossary' => array_values(array_map(fn($g) => ['id' => (string) ($g['id'] ?? ''),
                'forms' => array_values(array_map('strval', (array) ($g['forms'] ?? []))),
                'text' => missions::text($g['text'] ?? '', $skin, $mission->variant),
                'picture' => (string) ($g['picture'] ?? '')], $data['glossary'] ?? [])),
            // The burst of the Embrollo, if the mission has one: it belongs to no place, it opens from the map.
            'burst' => (string) (array_keys(array_filter($data['games'] ?? [], fn($g) => ($g['type'] ?? '') === 'burst'))[0] ?? ''),
            // The names the Embrollo puts to the places, a prank a day on the map of each student.
            'pranks' => array_map(fn($code, $name) => ['chapter' => (string) $code, 'name' => missions::text(
                $name,
                $skin,
                $mission->variant
            )], array_keys($data['pranks'] ?? []), array_values($data['pranks'] ?? [])),
            // The words the Embrollo writes over others in the Book, another prank: the word and its own.
            'prankwords' => self::prank_words($data, $skin, (string) $mission->variant),
            // The friends of the expedition: what each one tells the student, by the pages given back from what is theirs.
            'friends' => friends::view($mission, $found),
            // The field guide of the beings met: place by place, how many there are and which the student has.
            'guide' => $current ? array_map(fn($g) => $g + ['name' => missions::text(
                array_column($data['chapters'] ?? [], 'name', 'code')[$g['place']] ?? $g['place'],
                $skin
            )], encounters::guide($char, $mission)) : [],
            // Where the class is today, the place of «Today in the valley» (none on a past expedition).
            'today' => $current ? (string) (pacts::chapter($mission)->code ?? '') : '',
            // The footprints of the Embrollo towards the next place: one each school day since the class lit its last
            // lantern, up to five.
            'prints' => $current ? stains::prints($mission) : 0,
        ];
    }

    /**
     * The words the Embrollo writes over others in the Book: each word and its own, in the voice of the story.
     *
     * @param array $data Of the mission.
     * @param string $skin
     * @param string $variant
     * @return array Of from and to.
     */
    private static function prank_words(array $data, string $skin, string $variant): array {
        $words = [];
        foreach ((array) ($data['prankwords'] ?? []) as $w) {
            $from = trim((string) ($w['from'] ?? ''));
            $to = trim(missions::text($w['to'] ?? '', $skin, $variant));
            if ($from !== '' && $to !== '') {
                $words[] = ['from' => $from, 'to' => $to];
            }
        }
        return $words;
    }

    /**
     * The expeditions a course made before the current one, with what the class lit in each (and the student, if given).
     *
     * @param int $courseid
     * @param int $charid The student (0: the class only).
     * @return array id, code, title, subtitle, lit, total, mine, pages.
     */
    public static function expeditions(int $courseid, int $charid = 0): array {
        global $DB;
        $list = [];
        foreach (missions::past($courseid) as $mission) {
            $mine = $charid ? $DB->get_records_menu(
                self::T . 'found',
                ['charid' => $charid, 'missionid' => $mission->id],
                '',
                'id, kind'
            ) : [];
            $counts = array_count_values($mine);
            $list[] = [
                'id' => (int) $mission->id,
                'code' => $mission->code,
                'title' => missions::text($mission->data['title'] ?? $mission->code, $mission->skin),
                'subtitle' => missions::text($mission->data['subtitle'] ?? '', $mission->skin),
                'lit' => $DB->count_records_select(self::T . 'chapter', 'missionid = ? AND litat > 0', [$mission->id]),
                'total' => $DB->count_records(self::T . 'chapter', ['missionid' => $mission->id]),
                'mine' => (int) ($counts['lantern'] ?? 0),
                'pages' => (int) ($counts['page'] ?? 0),
            ];
        }
        return $list;
    }

    /**
     * The class screen of a course: the map of the mission with the lanterns of the class, and the event of the day.
     *
     * @param int $courseid
     * @param int|null $now
     * @return array mission (if any), event (if drawn), opener (if recent), cards.
     */
    public static function board(int $courseid, ?int $now = null): array {
        global $DB;
        $board = ['cards' => events::deck($courseid)];
        // In infant school, the creature of the class.
        $creature = classcreature::view($courseid);
        if ($creature) {
            $board['classcreature'] = $creature;
        }
        $mission = missions::get($courseid);
        if ($mission) {
            $info = [];
            foreach ($mission->data['chapters'] ?? [] as $chapter) {
                $info[$chapter['code']] = $chapter;
            }
            $chapters = [];
            $summaries = scenes::summaries($mission);
            $fields = 'id, missionid, chapterid, code, step, quest';
            $scenes = $DB->get_records(self::T . 'scene', ['missionid' => $mission->id], 'code', $fields);
            foreach ($DB->get_records(self::T . 'chapter', ['missionid' => $mission->id], 'sortorder') as $chapter) {
                $meta = $info[$chapter->code] ?? [];
                // The scenes the class can play together: those of the core step.
                $mine = scenes::for_step(array_values(array_filter($scenes, fn($sc) => $sc->chapterid == $chapter->id)), 2);
                $chapters[] = [
                    'id' => (int) $chapter->id,
                    'code' => $chapter->code,
                    'name' => missions::text($meta['name'] ?? $chapter->code, $mission->skin),
                    'x' => (int) ($meta['x'] ?? 0),
                    'y' => (int) ($meta['y'] ?? 0),
                    'landmark' => (string) ($meta['landmark'] ?? ''),
                    'lit' => (bool) $chapter->litat,
                    'scenes' => array_map(fn($sc) => ['id' => (int) $sc->id,
                        'title' => (string) ($summaries[$sc->id]['title'] ?? $sc->code)], $mine),
                ];
            }
            // The decisions of at least half of the class, as stickers by their places.
            $marks = scenes::class_flags($courseid, (int) $mission->id);
            foreach ($chapters as &$chapter) {
                $chapter['stickers'] = [];
                foreach ($info[$chapter['code']]['memory'] ?? [] as $memory) {
                    if (!empty($memory['sticker']) && in_array($memory['flag'] ?? '', $marks, true)) {
                        $chapter['stickers'][] = ['flag' => (string) $memory['flag'],
                            'text' => missions::text($memory['sticker'], $mission->skin, $mission->variant)];
                    }
                }
            }
            unset($chapter);
            [$items, $roles] = scenes::class_context($courseid);
            $board['mission'] = [
                'items' => $items,
                'roles' => $roles,
                'code' => $mission->code,
                'title' => missions::text($mission->data['title'] ?? $mission->code, $mission->skin),
                'skin' => $mission->skin,
                'map' => (string) ($mission->data['map'] ?? $mission->code),
                'mapworld' => (string) ($mission->data['maplayers']['world'] ?? ''),
                'mapheight' => (int) ($mission->data['maplayers']['height'] ?? 0),
                'seasons' => self::seasons($mission),
                'cinematics' => self::cinematics($mission),
                'chapters' => $chapters,
                // The final battle, if the mission saves something for the end, and the text of its end.
                'final' => !empty($mission->data['battle']['final']),
                // The knots of the Embrollo, for their cards on paper: what each one claims and what undoes it.
                'knots' => array_values(array_filter(array_map(function ($k) use ($mission) {
                    $knot = knots::get($mission, (string) $k['code']);
                    return $knot ? ['name' => $knot['name'], 'claim' => $knot['claim'], 'weakness' => $knot['weakness'],
                        'why' => $knot['why']] : null;
                }, knots::usable($mission)))),
                // How much the class weakened the Embrollo for it.
                'expedition' => expedition::strength($courseid) + ['known' => array_column(expedition::known($courseid), 'name')],
                'ending' => missions::text($mission->data['ending'] ?? '', $mission->skin, $mission->variant),
                // What the Great Book remembers of the class at the end: what at least half of it did, without names.
                'remembered' => self::remembered($courseid, $mission),
                // What the Embrollo says in a battle, by moment: start, hit, recover, phase2, won, lost.
                'lines' => array_map(fn($kind) => ['kind' => $kind, 'lines' => array_values(array_map(
                    fn($line) => missions::text($line, $mission->skin, $mission->variant),
                    (array) ($mission->data['battle']['lines'][$kind] ?? [])
                ))], ['start', 'hit', 'recover', 'phase2', 'won', 'lost']),
            ];
        }
        $event = events::export(events::today($courseid, $now));
        if ($event) {
            $board['event'] = $event;
        }
        $today = today::get($courseid, true);
        if ($today) {
            $board['today'] = $today;
        }
        $board['expeditions'] = self::expeditions($courseid);
        // The board as a game board: the fog of the Embrollo over the places the class forgot, and the token of each team.
        $board['fog'] = fog::places($courseid);
        // Who brought their strength from far, playing the last battle in deferred.
        $board['fromfar'] = deferred::from_far($courseid, $now);
        $board['tokens'] = fog::tokens($courseid);
        $opener = nextclass::opener($courseid, $now ?? time());
        if ($opener !== '') {
            $board['opener'] = $opener;
        }
        return $board;
    }

    /**
     * The class of a course, for its teachers. For the class screen, only what is shown to the class: no pictures, no
     * notes, no challenges, and the characters as classmates see them.
     *
     * @param int $courseid
     * @param bool $board
     * @return array
     */
    public static function teacher(int $courseid, bool $board = false): array {
        global $DB;
        $region = regions::get($courseid);
        $groupingid = (int) ($region->groupingid ?? 0);
        $students = self::students($courseid);
        $chars = [];
        if ($students) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($students));
            foreach ($DB->get_records_select(self::T . 'char', "userid $insql", $params) as $char) {
                $chars[$char->userid] = $char;
            }
        }
        $members = [];
        if ($chars) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($c) => $c->id, $chars));
            $params[] = $courseid;
            foreach ($DB->get_records_select(self::T . 'member', "charid $insql AND courseid = ?", $params) as $member) {
                $members[$member->charid] = $member;
            }
        }
        $groups = groups_get_all_groups(games::course($courseid), 0, $groupingid);
        $teamof = [];
        foreach ($groups as $group) {
            foreach (array_keys(groups_get_members($group->id, 'u.id')) as $memberid) {
                $teamof[$memberid] = $teamof[$memberid] ?? (int) $group->id;
            }
        }
        // The team plays a classmate made for each one, which the teacher can take back.
        $teamplays = $board ? [] : teamplay::for_teacher($courseid);
        $list = [];
        foreach ($students as $user) {
            $char = $chars[$user->id] ?? null;
            $member = $char ? ($members[$char->id] ?? null) : null;
            $student = [
                'userid' => (int) $user->id,
                'fullname' => fullname($user),
                'firstname' => $user->firstname,
                'picture' => $board ? '' : self::picture($user),
                'teamid' => $teamof[$user->id] ?? 0,
                'teamplays' => array_map(fn($p) => ['id' => $p['id'], 'scene' => $p['scene'], 'time' => $p['time'],
                    'by' => isset($students[$p['by']]) ? fullname($students[$p['by']]) : '', 'decisions' => $p['decisions'],
                    'decided' => $p['decided']], $teamplays[$user->id] ?? []),
            ];
            if ($char) {
                $student['character'] = self::character($char, $member, !$board);
            }
            $list[] = $student;
        }
        $challenges = [];
        foreach ($board ? [] : classroom::list($courseid) as $challenge) {
            $validations = [];
            foreach (classroom::validations((int) $challenge->id) as $validation) {
                $userid = array_search($validation->charid, array_map(fn($c) => $c->id, $chars));
                if ($userid !== false) {
                    $validations[] = ['userid' => (int) $userid, 'status' => $validation->status,
                        'note' => (string) $validation->note];
                }
            }
            $done = classroom::parts_done($challenge);
            $challenges[] = [
                'id' => (int) $challenge->id,
                'name' => format_string($challenge->name),
                'description' => (string) $challenge->description,
                'saber' => (int) $challenge->saber,
                'gems' => (int) $challenge->gems,
                'steps' => $challenge->steps,
                'teamwork' => (bool) $challenge->teamwork,
                'validations' => $validations,
                'parts' => classroom::parts($challenge),
                'teamparts' => array_map(fn($g) => ['groupid' => $g, 'done' => $done[$g]], array_keys($done)),
            ];
        }
        // How each team says it is doing today.
        $states = teams::states($courseid);
        $view = [
            'region' => self::region($region, !$board),
            'students' => $list,
            'teams' => array_values(array_map(fn($g) => ['id' => (int) $g->id, 'name' => format_string($g->name),
                'banner' => market::team_owns($courseid, (int) $g->id), 'state' => $states[(int) $g->id] ?? '',
                'bannerstage' => banners::view($courseid, (int) $g->id)], $groups)),
            'groupings' => array_values(array_map(
                fn($g) => ['id' => (int) $g->id, 'name' => format_string($g->name),
                    'classtools' => str_starts_with((string) ($g->idnumber ?? ''), 'oksigeniaclasstools-')],
                groups_get_all_groupings(games::course($courseid))
            )),
            // Oksigenia Classtools, if the site has it: its random teams, with the photos of the class, saved as a grouping.
            'classtools' => \core_component::get_component_directory('local_oksigeniaclasstools') ?
                (new \moodle_url('/local/oksigeniaclasstools/index.php', ['id' => $courseid]))->out(false) : '',
            'challenges' => $challenges,
            'energymax' => engine::ENERGY_MAX,
            'breathmax' => engine::BREATH_MAX,
            'standings' => teams::standings($courseid),
            'activities' => $board ? [] : activities::for_teacher($courseid, array_keys($students)),
            'powerspending' => powers::pending_count($courseid),
            'ideasunread' => ideas::unread($courseid),
            'pactspending' => pacts::pending($courseid),
            'namespending' => names::pending($courseid),
            // The marks of the mission the teacher can give as the director of the game.
            'director' => $board ? [] : director::marks($courseid),
            // The tangles of the teams: those waiting for the teacher, and those approved with how they went.
            'tangles' => $board ? ['pending' => [], 'approved' => []] : tangles::for_teacher($courseid),
            // The photos of the classroom: those waiting for the teacher and those on the map; the class screen, only these.
            'photos' => photos::of_course($courseid, $board),
        ];
        if ($board) {
            // The museum of the class in the Library, which the class screen shows.
            $museum = museum::of_course($courseid);
            if ($museum) {
                $view['museum'] = $museum;
            }
            return $view;
        }
        $pacts = pacts::for_teacher($courseid);
        if ($pacts) {
            $view['pacts'] = $pacts;
        }
        // With the postscript of the Embrollo: the teacher sees which mix-up it is and how to lead the class back.
        $today = today::get($courseid, true);
        if ($today) {
            $view['today'] = $today;
        }
        // Who to look at: stuck today, or with a step that may weigh or fall short. Only here, never on the class screen.
        $view['signals'] = signals::of_course($courseid);
        // The requests of powers waiting, for the tray of what is waiting in the panel.
        if ($view['powerspending'] && has_capability('local/oksigeniatesape:approvepowers', games::context($courseid))) {
            $view['powersqueue'] = powers::requests($courseid, 0)['pending'];
        }
        return $view;
    }

    /**
     * The log of a course, newest first, for its teachers.
     *
     * @param int $courseid
     * @param int $userid Only this student (0: everybody).
     * @param int $limit
     * @param int $beforeid Only entries older than this one (0: from the newest).
     * @return array
     */
    public static function log(int $courseid, int $userid, int $limit, int $beforeid = 0): array {
        global $DB;
        $where = 'l.courseid = :courseid';
        $params = ['courseid' => $courseid];
        if ($userid) {
            $where .= ' AND c.userid = :userid';
            $params['userid'] = $userid;
        }
        if ($beforeid) {
            $where .= ' AND l.id < :beforeid';
            $params['beforeid'] = $beforeid;
        }
        $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $entries = $DB->get_records_sql(
            "SELECT l.*, c.userid, $names
               FROM {" . self::T . "ledger} l
               JOIN {" . self::T . "char} c ON c.id = l.charid
               JOIN {user} u ON u.id = c.userid
              WHERE $where
           ORDER BY l.id DESC",
            $params,
            0,
            $limit
        );
        $actors = [];
        $actorids = array_filter(array_unique(array_map(fn($e) => (int) $e->actorid, $entries)));
        if ($actorids) {
            $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
            $actors = $DB->get_records_list('user', 'id', $actorids, '', $fields);
        }
        return array_values(array_map(fn($e) => self::entry($e) + [
            'userid' => (int) $e->userid,
            'fullname' => fullname($e),
            'actor' => isset($actors[$e->actorid]) ? fullname($actors[$e->actorid]) : '',
        ], $entries));
    }

    /**
     * The students of a course who can play, by name.
     *
     * @param int $courseid
     * @return \stdClass[] Keyed by user id.
     */
    public static function students(int $courseid): array {
        $fields = 'u.id, u.picture, u.imagealt, u.email, ' . implode(', ', array_map(
            fn($f) => 'u.' . $f,
            \core_user\fields::get_name_fields()
        ));
        $users = get_enrolled_users(
            games::context($courseid),
            'local/oksigeniatesape:play',
            0,
            $fields,
            'u.lastname, u.firstname',
            0,
            0,
            true
        );
        // Those of the game: in a course with more than one, each student plays in one.
        return games::filter($courseid, $users);
    }

    /**
     * Checks that some users are students of a course who can play.
     *
     * @param int $courseid
     * @param int[] $userids
     */
    public static function require_students(int $courseid, array $userids): void {
        $students = self::students($courseid);
        foreach ($userids as $userid) {
            if (!isset($students[$userid])) {
                throw new \invalid_parameter_exception('Not a student of the course: ' . $userid);
            }
        }
    }

    /**
     * The settings of a region, with the defaults when the game was never set up.
     *
     * @param \stdClass|null $region
     * @param bool $banks With the categories of the question banks the teacher can choose (not for the class screen).
     * @return array
     */
    public static function region(?\stdClass $region, bool $banks = true): array {
        // The badge of the mission, for the teacher to know it is there and whether it is on.
        $badge = ['badge' => '', 'badgeid' => 0, 'badgeon' => false];
        $mission = $region && badges::enabled() ? missions::get((int) $region->courseid) : null;
        $id = $mission ? badges::find((int) $region->courseid, $mission) : 0;
        if ($id) {
            $status = (int) $GLOBALS['DB']->get_field('badge', 'status', ['id' => $id]);
            $on = in_array($status, [BADGE_STATUS_ACTIVE, BADGE_STATUS_ACTIVE_LOCKED], true);
            $badge = ['badge' => badges::name($mission), 'badgeid' => $id, 'badgeon' => $on];
        }
        return $badge + [
            'enabled' => (bool) ($region->enabled ?? false),
            'family' => $region->family ?? 'storyheroes',
            'groupingid' => (int) ($region->groupingid ?? 0),
            'weeklycap' => (int) ($region->weeklycap ?? 0),
            'dailymanualcap' => (int) ($region->dailymanualcap ?? 20),
            'dailyenergylosscap' => (int) ($region->dailyenergylosscap ?? 3),
            'leaderboard' => (bool) ($region->leaderboard ?? false),
            'nicknames' => (int) ($region->nicknames ?? 0),
            'timezone' => (string) ($region->timezone ?? '99'),
            'completionrule' => $region ? regions::rule($region, 'completion') : regions::RULES['completion'],
            'talentson' => !$region || regions::rule($region, 'talents') > 0,
            'skillson' => !$region || regions::rule($region, 'skills') > 0,
            'secretsshown' => $region ? regions::rule($region, 'secrets') : 0,
            'sharelibrary' => $region && regions::rule($region, 'library') > 0,
            'lightart' => $region && regions::rule($region, 'lightart') > 0,
            'stage' => regions::stage($region),
            'creaturename' => classcreature::given_name($region),
            'qbank' => $region ? regions::rule($region, 'qbank') : 0,
            'qbanks' => $region && $banks ? moodlebank::categories((int) $region->courseid) : [],
            'portal' => (string) ($region->portal ?? 'door'),
            'hideacts' => (bool) ($region->hideacts ?? false),
            'allowstealth' => !empty($GLOBALS['CFG']->allowstealth),
            'gradebook' => (bool) ($region->gradebook ?? false),
            'quiet' => (bool) ($region->quiet ?? false),
            'devices' => (bool) ($region->devices ?? true),
            'devicemusic' => (int) ($region->devicemusic ?? 1),
            'mischief' => (string) ($region->mischief ?? 'calm'),
            'setup' => (int) ($region->setup ?? 0),
            'reasons' => regions::reasons($region),
            'reasonsown' => !empty($region->reasons),
            'pauses' => regions::pauses($region),
            'pausesown' => !empty($region->pauses),
            // Without completion of activities, the activities of Moodle give no saber: the first step says so.
            'completion' => !empty($GLOBALS['CFG']->enablecompletion) && (!$region ||
                !empty($GLOBALS['DB']->get_field('course', 'enablecompletion', ['id' => games::course((int) $region->courseid)]))),
            'look' => (string) ($region->look ?? ''),
            'worldlook' => themes::current('', art::manifest()),
        ];
    }

    /**
     * The items of a student with the trade of the day: those their team lent say to whom, and those lent to their team
     * come with who lent them.
     *
     * @param array $items code, name, description.
     * @param int $courseid
     * @param int $groupid Their team (0: none).
     * @return array The same, with lent or borrowed (the name of the other team).
     */
    private static function traded_items(array $items, int $courseid, int $groupid): array {
        global $DB;
        $event = $groupid ? events::active($courseid, 'trade') : null;
        $loans = $event ? ((json_decode($event->data, true) ?: [])['loans'] ?? []) : [];
        foreach ($loans as $from => $loan) {
            if ((int) $from === $groupid) {
                foreach ($items as $i => $item) {
                    if ($item['code'] === $loan['item']) {
                        $items[$i]['lent'] = groups_get_group_name((int) $loan['to']);
                    }
                }
            } else if ((int) $loan['to'] === $groupid && !in_array($loan['item'], array_column($items, 'code'), true)) {
                $item = $DB->get_record(self::T . 'item', ['code' => $loan['item']]);
                if ($item) {
                    $items[] = ['code' => $item->code, 'name' => format_string($item->name),
                        'description' => (string) $item->description, 'borrowed' => groups_get_group_name((int) $from)];
                }
            }
        }
        return $items;
    }

    /**
     * A character, with its state in the course if it plays there.
     *
     * @param \stdClass $char
     * @param \stdClass|null $member
     * @param bool $private With what only the student and the teachers see (why they fell or were paused, an own name for
     *     the creature still to be read).
     * @return array
     */
    private static function character(\stdClass $char, ?\stdClass $member, bool $private): array {
        $level = progress::level_progress((int) $char->saber);
        $creature = (string) $char->creature;
        $worn = market::worn($char);
        if ($creature !== '' && (!$private || isset($worn['paint']))) {
            $traits = json_decode($creature, true) ?: [];
            if (!$private) {
                // An own name for the creature that no teacher has read yet is not for classmates.
                unset($traits['pending']);
            }
            if (isset($worn['paint'])) {
                $traits['paint'] = $worn['paint'];
            }
            $creature = json_encode($traits);
        }
        $character = [
            'role' => $char->role,
            'nameform' => $char->nameform,
            'lineage' => (string) $char->lineage,
            'nature' => implode(',', characters::nature($char)),
            'nickname' => (string) $char->nickname,
            'appearance' => (string) $char->appearance,
            'saber' => (int) $char->saber,
            'level' => $level['level'],
            'levelinto' => $level['into'],
            'levelneeded' => $level['needed'],
            'stage' => progress::stage_for_level($level['level']),
            'gems' => (int) $char->gems,
            'creaturestage' => (int) $char->creaturestage,
            'creature' => $creature,
            'aura' => (string) ($worn['aura'] ?? ''),
            // The talents chosen, and the choice waiting: for the student and their teachers only.
            'talents' => $private ? talents::view($char) : [],
            // Where the teacher turns them off, they wait: the choice too.
            'talentsoff' => $private && $member && !talents::on_in((int) $member->courseid),
            // What they have learnt to do in the course: for the student and their teachers only.
            'skills' => $private && $member ? skills::of($char, (int) $member->courseid) : [],
            // Its creature reached its second stage and chooses its branch.
            'branchpending' => $private && creatures::branch_pending($char),
            // The rite of ascent waits: why (the six lanterns lit, or the final won), or nothing.
            'ascentpending' => $private && $member ? (string) ascents::pending($char, (int) $member->courseid) : '',
        ];
        $pending = $private && $member && talents::on_in((int) $member->courseid) ? talents::pending($char) : null;
        if ($pending) {
            $character['talentpending'] = ['level' => $pending['level'],
                'options' => array_map(fn($code) => talents::card($code), $pending['options'])];
        }
        if ($member) {
            $remote = (int) ($member->remote ?? 0);
            $character['member'] = [
                'renown' => (int) $member->renown,
                'energy' => (int) $member->energy,
                'breath' => (int) $member->breath,
                // What the teacher decides about a student (that they rest and why, their step, what they need) is for
                // them and their teachers only: their classmates see that they are not playing, not why. That they follow
                // from far, the teacher decides whether their team sees it.
                'status' => !$private && $member->status === 'paused' ? 'away' : $member->status,
                'statusreason' => $private ? (string) $member->statusreason : '',
                'pausedto' => $private && $member->status === 'paused' ? (int) ($member->pausedto ?? 0) : 0,
                'entrystep' => $private ? (int) ($member->entrystep ?? 0) : 0,
                'step' => $private ? (int) $member->step : 0,
                'lastseen' => $private ? (int) ($member->lastseen ?? 0) : 0,
                'mood' => $private ? self::mood($char, $member) : '',
                'remote' => ($private && $remote > 0) || $remote === 2,
                'remoteshown' => $private && $remote === 2,
                'adapt' => $private ? adapt::of($member) : [],
                'adaptteacher' => $private ? adapt::teacher($member) : [],
                'adaptown' => $private ? adapt::own($member) : [],
                // The curiosities they read, and whether they told the class their fact (for their teacher to mark it).
                'curios' => $private ? curiosities::for_teacher((int) $member->courseid, (int) $char->id) : [],
            ];
        }
        return $character;
    }

    /**
     * How the creature of a student is: asleep after a week away (it wakes when touched; never ill, never gone), happy
     * after three good endings in a row, or nothing to say.
     *
     * @param \stdClass $char
     * @param \stdClass $member With lastseen as it was before this visit.
     * @return string asleep, happy or empty.
     */
    private static function mood(\stdClass $char, \stdClass $member): string {
        global $DB;
        if ((int) $char->creaturestage < 1) {
            return '';
        }
        $since = (int) ($member->lastseen ?? 0);
        if ($since && time() - $since >= self::ASLEEP_DAYS * DAYSECS) {
            return 'asleep';
        }
        $where = 'charid = ? AND courseid = ? AND ending IS NOT NULL';
        $params = [$char->id, $member->courseid];
        $last = $DB->get_records_select(self::T . 'sceneattempt', $where, $params, 'timefinished DESC, id DESC', 'id, sceneid, ending', 0, 3);
        if (count($last) < 3) {
            return '';
        }
        foreach ($last as $attempt) {
            $definition = json_decode((string) $DB->get_field(self::T . 'scene', 'definition', ['id' => $attempt->sceneid]), true);
            if (($definition['nodes'][$attempt->ending]['outcome'] ?? 'success') !== 'success') {
                return '';
            }
        }
        return 'happy';
    }

    /**
     * The team of a student: its name and its members, as classmates see them.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $groupingid
     * @param \stdClass $region
     * @return array|null
     */
    private static function team(int $courseid, int $userid, int $groupingid, \stdClass $region): ?array {
        global $DB;
        $groups = groups_get_all_groups(games::course($courseid), $userid, $groupingid);
        if (!$groups) {
            return null;
        }
        $group = reset($groups);
        $students = self::students($courseid);
        // The characters of the team and their members in the course, in two queries.
        $users = array_intersect_key(groups_get_members($group->id, 'u.id'), $students);
        $chars = [];
        $members = [];
        if ($users) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($users));
            foreach ($DB->get_records_select(self::T . 'char', "userid $insql", $params) as $char) {
                $chars[(int) $char->userid] = $char;
            }
        }
        if ($chars) {
            [$insql, $params] = $DB->get_in_or_equal(array_map(fn($c) => (int) $c->id, $chars));
            $where = "courseid = ? AND charid $insql";
            foreach ($DB->get_records_select(self::T . 'member', $where, array_merge([$courseid], $params)) as $member) {
                $members[(int) $member->charid] = $member;
            }
        }
        // Who is low on energy, only for the healer and the protector, who can help; the rest see the team's total.
        $mine = $chars[$userid] ?? null;
        $carers = $mine && in_array($mine->role, self::CARERS, true);
        $energy = 0;
        $mates = [];
        foreach ($users as $mate) {
            $char = $chars[(int) $mate->id] ?? null;
            $member = $char ? ($members[(int) $char->id] ?? null) : null;
            $entry = [
                'userid' => (int) $mate->id,
                'name' => self::display_name($students[$mate->id], $char, $region),
                'me' => $mate->id == $userid,
            ];
            if ($char) {
                $entry['character'] = self::character($char, $member ?: null, $mate->id == $userid);
                $entry['gifts'] = array_keys(array_filter(
                    ['guard' => 'teamguard', 'trail' => 'sharedtrail'],
                    fn($code) => talents::has((int) $char->id, $code, $courseid)
                ));
                $energy += $member ? (int) $member->energy : 0;
                if ($member && !$carers && $mate->id != $userid) {
                    $entry['character']['member']['energy'] = -1;
                    if ($member->status === 'fallen') {
                        $entry['character']['member']['status'] = 'active';
                    }
                }
            }
            $mates[] = $entry;
        }
        $playing = count(array_filter($mates, fn($m) => isset($m['character']['member'])));
        // The council of the team, with a mission; and its reserve, for who plays in it.
        $council = self::council($courseid, $group, $userid, $mates);
        $reserve = $mine ? reserve::view($courseid, (int) $group->id, $mine, array_column($mates, 'name', 'userid')) : null;
        return ['id' => (int) $group->id, 'name' => format_string($group->name), 'members' => $mates,
            'energy' => $energy, 'energymax' => $playing * engine::ENERGY_MAX, 'carer' => $carers,
            'banner' => market::team_owns($courseid, (int) $group->id),
            // The library of the team: a book for each curiosity one of them read.
            'library' => curiosities::library($courseid, (int) $group->id),
            'bannerstage' => banners::view($courseid, (int) $group->id),
            'brought' => events::brought($courseid, (int) $group->id),
            'state' => teams::states($courseid)[(int) $group->id] ?? '',
            'first' => teams::firsts($courseid)[(int) $group->id] ?? ''] + ($council ? ['council' => $council] : []) +
            ($reserve ? ['reserve' => $reserve] : []);
    }

    /**
     * The council of a team: the task of the place of today, with its parts and who of the team has the role of each.
     *
     * @param int $courseid
     * @param \stdClass $group
     * @param int $userid Who looks.
     * @param array $mates The members of the team, as team() makes them.
     * @return array|null place, task, parts (role, text, done, who: the names of the team with that role).
     */
    private static function council(int $courseid, \stdClass $group, int $userid, array $mates): ?array {
        global $DB;
        $mission = missions::get($courseid);
        $chapter = $mission ? pacts::chapter($mission) : null;
        if (!$chapter) {
            return null;
        }
        $place = '';
        foreach ($mission->data['chapters'] ?? [] as $info) {
            if (($info['code'] ?? '') === $chapter->code) {
                $place = missions::text($info['name'] ?? '', $mission->skin);
            }
        }
        $tasks = $DB->get_records(self::T . 'challenge', ['courseid' => $courseid, 'chapterid' => $chapter->id, 'cmid' => 0], 'id');
        $task = $tasks ? reset($tasks) : null;
        $parts = [];
        foreach ($task ? classroom::team_parts($task, (int) $group->id, $userid) : [] as $part) {
            $who = array_column(array_filter($mates, fn($m) => ($m['character']['role'] ?? '') === $part['role']), 'name');
            $parts[] = ['role' => (string) $part['role'], 'text' => (string) $part['text'], 'done' => (bool) $part['done'],
                'who' => implode(', ', $who)];
        }
        return ['place' => $place, 'task' => $task ? format_string((string) $task->name) : '', 'parts' => $parts];
    }

    /**
     * The playable prologue of the mission of a course: its vignettes (picture, who speaks and what is told), the
     * question that makes the student choose their role and the mirror where they choose their look, in the skin of
     * the course.
     *
     * @param int $courseid
     * @return array|null
     */
    public static function opening(int $courseid): ?array {
        $mission = missions::get($courseid);
        $data = $mission ? ($mission->data['opening'] ?? null) : null;
        if (!$data || empty($data['beats'])) {
            return null;
        }
        $text = fn($t) => missions::text($t ?? '', $mission->skin, $mission->variant);
        $speakers = $mission->data['speakers'] ?? [];
        return [
            'beats' => array_values(array_map(fn($b) => [
                'image' => (string) ($b['image'] ?? ''),
                'speaker' => (string) ($b['speaker'] ?? ''),
                'speakername' => isset($b['speaker'], $speakers[$b['speaker']]) ? $text($speakers[$b['speaker']]) : '',
                'text' => $text($b['text'] ?? ''),
            ], $data['beats'])),
            'ask' => $text($data['ask'] ?? ''),
            'mirror' => $text($data['mirror'] ?? ''),
            'skin' => (string) $mission->skin,
            'place' => (string) (($mission->data['chapters'][0]['code'] ?? '')),
        ];
    }

    /**
     * The lines of the end of a mission that tell what the class did: each of its marks that at least half of the
     * students who play left.
     *
     * @param int $courseid
     * @param \stdClass $mission
     * @return string[]
     */
    public static function remembered(int $courseid, \stdClass $mission): array {
        $lines = $mission->data['endingmemory'] ?? [];
        if (!$lines) {
            return [];
        }
        $marks = scenes::class_flags($courseid, (int) $mission->id);
        $out = [];
        foreach ($lines as $line) {
            if (in_array($line['flag'] ?? '', $marks, true)) {
                $out[] = missions::text($line['text'] ?? '', $mission->skin, $mission->variant);
            }
        }
        return $out;
    }

    /**
     * The place where each of some students last played a scene of a mission.
     *
     * @param int $courseid
     * @param int $missionid
     * @param int[] $userids
     * @return array userid => chapter id.
     */
    public static function team_places(int $courseid, int $missionid, array $userids): array {
        global $DB;
        if (!$userids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($userids);
        $rows = $DB->get_recordset_sql(
            'SELECT a.id, c.userid, s.chapterid
               FROM {' . self::T . 'sceneattempt} a
               JOIN {' . self::T . 'char} c ON c.id = a.charid
               JOIN {' . self::T . 'scene} s ON s.id = a.sceneid
              WHERE a.courseid = ? AND s.missionid = ? AND a.timefinished > 0 AND c.userid ' . $insql . '
           ORDER BY a.timefinished DESC, a.id DESC',
            array_merge([$courseid, $missionid], $params)
        );
        $at = [];
        foreach ($rows as $row) {
            $at[(int) $row->userid] = $at[(int) $row->userid] ?? (int) $row->chapterid;
        }
        $rows->close();
        return $at;
    }

    /**
     * Be the Embrollo, for a student: the tangle of their team this week, those of other teams to undo, and whether the
     * team can write one now (none this week, or the one waiting, or the one given back).
     *
     * @param int $courseid
     * @param int $userid
     * @param int|null $now
     * @return array mine (if any), others, canwrite.
     */
    public static function tangles(int $courseid, int $userid, ?int $now = null): array {
        $mine = tangles::for_team($courseid, $userid, $now);
        $out = ['others' => tangles::for_student($courseid, $userid),
            'canwrite' => teams::of($courseid, $userid) !== null && (!$mine || $mine['status'] !== 'approved')];
        return $mine ? ['mine' => $mine] + $out : $out;
    }

    /**
     * The name classmates see: the hero name where the course allows it, the first name otherwise.
     *
     * @param \stdClass $user
     * @param \stdClass|null $char
     * @param \stdClass $region
     * @return string
     */
    public static function display_name(\stdClass $user, ?\stdClass $char, \stdClass $region): string {
        if ($region->nicknames && $char && $char->nickname) {
            return $char->nickname;
        }
        return $user->firstname;
    }

    /**
     * The address of a user's picture, empty if the user has none: the teacher sees the portrait of the character
     * instead of the empty picture of Moodle.
     *
     * @param \stdClass $user
     * @return string
     */
    private static function picture(\stdClass $user): string {
        global $PAGE;
        // Only a picture of the site: never one asked to a third party (Gravatar) with the email of a student.
        if (empty($user->picture)) {
            return '';
        }
        $picture = new \user_picture($user);
        $picture->size = 100;
        return $picture->get_url($PAGE)->out(false);
    }

    /**
     * A log entry, as the client shows it.
     *
     * @param \stdClass $entry
     * @return array
     */
    private static function entry(\stdClass $entry): array {
        return [
            'id' => (int) $entry->id,
            'resource' => $entry->resource,
            'amount' => (int) $entry->amount,
            'capped' => (int) $entry->capped,
            'reason' => $entry->reason,
            'note' => (string) ($entry->note ?? ''),
            'undone' => (bool) $entry->undoneby,
            'time' => (int) $entry->timecreated,
        ];
    }

    /**
     * What happens in the world of a mission in each season, as its map tells it: spring, summer, autumn and winter.
     *
     * @param \stdClass $mission With data.
     * @return array season => text ('' if the mission says nothing).
     */
    public static function seasons(\stdClass $mission): array {
        $out = [];
        foreach (['spring', 'summer', 'autumn', 'winter'] as $season) {
            $out[$season] = missions::text($mission->data['seasons'][$season] ?? '', $mission->skin, $mission->variant);
        }
        return $out;
    }

    /**
     * The cinematics of a mission for the class screen, in its story: each one with its steps (a painting, how the
     * camera moves, what is told).
     *
     * @param \stdClass $mission With data.
     * @return array code, title, steps (image, move, text).
     */
    public static function cinematics(\stdClass $mission): array {
        $out = [];
        foreach ($mission->data['cinematics'] ?? [] as $code => $cine) {
            $steps = [];
            foreach ($cine['steps'] ?? [] as $step) {
                $steps[] = ['image' => (string) ($step['image'] ?? ''), 'move' => (string) ($step['move'] ?? 'in'),
                    'text' => missions::text($step['text'] ?? '', $mission->skin, $mission->variant)];
            }
            $out[] = ['code' => (string) $code, 'title' => missions::text($cine['title'] ?? $code, $mission->skin), 'steps' => $steps];
        }
        return $out;
    }
}
