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

namespace local_oksigeniatesape\output;

use local_oksigeniatesape\local\art;
use local_oksigeniatesape\local\games;
use local_oksigeniatesape\local\portal;

/**
 * What the game shows in the Moodle app: the games of the user, as on «my world», each with its ways in. The game
 * opens inside the app, with the session.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
    /**
     * The entry of the main menu, only for who plays or teaches somewhere.
     *
     * @param array $args What the app sends.
     * @return array
     */
    public static function mobile_games_init(array $args): array {
        global $USER;
        return ['disabled' => !portal::has_world((int) $USER->id), 'javascript' => '', 'templates' => []];
    }

    /**
     * The page of the menu entry: all the games of the user.
     *
     * @param array $args What the app sends.
     * @return array
     */
    public static function mobile_games_view(array $args): array {
        global $USER;
        return self::page(portal::world((int) $USER->id));
    }

    /**
     * The tab of a course, only in the courses with a game.
     *
     * @param array $args What the app sends.
     * @return array
     */
    public static function mobile_course_init(array $args): array {
        global $USER;
        $courses = array_values(array_unique(array_map(
            fn($game) => games::course((int) $game['id']),
            portal::world((int) $USER->id)
        )));
        return ['restrict' => ['courses' => $courses], 'disabled' => !$courses, 'javascript' => '', 'templates' => []];
    }

    /**
     * The tab of a course: its games.
     *
     * @param array $args What the app sends: courseid among others.
     * @return array
     */
    public static function mobile_course_view(array $args): array {
        global $USER;
        $courseid = (int) ($args['courseid'] ?? 0);
        return self::page(array_values(array_filter(
            portal::world((int) $USER->id),
            fn($game) => games::course((int) $game['id']) === $courseid
        )));
    }

    /**
     * A page of games for the app.
     *
     * @param array $world As portal::world().
     * @return array
     */
    private static function page(array $world): array {
        global $OUTPUT;
        $games = [];
        foreach ($world as $game) {
            $games[] = [
                'name' => $game['name'],
                'mission' => $game['mission'],
                'line' => $game['line'],
                'note' => $game['note'],
                // The app has no session of the site: the address carries the key of the user.
                'image' => $game['image'] !== '' ? art::small($game['image'], true) : '',
                'buttons' => array_map(fn($b) => ['url' => $b[0]->out(false), 'label' => $b[1], 'main' => $b[2]], $game['buttons']),
            ];
        }
        $data = [
            'games' => $games,
            'hasgames' => (bool) $games,
            'none' => get_string('mobile_none', 'local_oksigeniatesape'),
            'hint' => get_string('mobile_hint', 'local_oksigeniatesape'),
        ];
        return [
            'templates' => [['id' => 'main', 'html' => $OUTPUT->render_from_template('local_oksigeniatesape/mobile_games', $data)]],
            'javascript' => '',
            'otherdata' => '',
        ];
    }
}
