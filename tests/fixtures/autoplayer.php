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

namespace local_oksigeniatesape;

/**
 * Plays a scene the right way, as a student who knows the answers would, for the tests.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class autoplayer {
    /**
     * The events of a good play: the best options available, each item in its group, the steps in order.
     *
     * @param array $scene
     * @param string[] $items Items the student carries.
     * @param string[] $roles Roles in the team.
     * @return array
     */
    public static function play(array $scene, array $items = [], array $roles = []): array {
        return \local_oksigeniatesape\local\autoplayer::play($scene, $items, $roles);
    }

    /**
     * A scene of the mission that comes with the plugin.
     *
     * @param string $code
     * @return array
     */
    public static function scene(string $code): array {
        global $CFG;
        return json_decode(file_get_contents($CFG->dirroot . '/local/oksigeniatesape/missions/livingthings/scenes/' . $code .
            '.json'), true);
    }
}
