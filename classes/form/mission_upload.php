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

namespace local_oksigeniatesape\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The site administration uploads the package of a mission (a zip).
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mission_upload extends \moodleform {
    /**
     * The file and the button.
     */
    protected function definition() {
        $form = $this->_form;
        // The label and the button may change: the page of the site, or the workshop of a course.
        $label = $this->_customdata['label'] ?? get_string('missions_upload', 'local_oksigeniatesape');
        $form->addElement(
            'filepicker',
            'package',
            $label,
            null,
            ['accepted_types' => ['.zip'], 'maxbytes' => 0]
        );
        $form->addRule('package', null, 'required');
        $button = $this->_customdata['button'] ?? get_string('missions_upload_go', 'local_oksigeniatesape');
        $this->add_action_buttons(false, $button);
    }
}
