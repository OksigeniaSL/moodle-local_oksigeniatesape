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
 * Deletes what the students who left a course long ago did there.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class forget_left extends \core\task\scheduled_task {
    /**
     * Name shown to administrators.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:forgetleft', 'local_oksigeniatesape');
    }

    /**
     * Runs the task.
     */
    public function execute(): void {
        $count = \local_oksigeniatesape\local\leaving::forget();
        mtrace("$count students who left a course long ago: what they did there, deleted.");
    }
}
