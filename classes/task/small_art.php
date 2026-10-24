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

namespace local_oksigeniatesape\task;

/**
 * Makes the small copies of the big images of the art packs already installed, once: those installed from now on get
 * them as they are installed.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class small_art extends \core\task\adhoc_task {
    /**
     * Runs the task.
     */
    public function execute(): void {
        $world = (string) get_config('local_oksigeniatesape', 'artpack');
        $layers = array_filter(explode(',', (string) get_config('local_oksigeniatesape', 'artlayers')));
        foreach (array_unique(array_merge($world !== '' ? [$world] : [], $layers)) as $code) {
            mtrace('Small copies of ' . $code . ': ' . \local_oksigeniatesape\local\art::make_small($code));
        }
    }
}
