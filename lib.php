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

/**
 * Callbacks: any activity can be a challenge of the game (its settings form), and the images of the art packs.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_oksigeniatesape\local\activities;
use local_oksigeniatesape\local\art;
use local_oksigeniatesape\local\missions;
use local_oksigeniatesape\local\regions;

/**
 * Adds the game to the settings of an activity, where the game is on and the teacher can set it up.
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_oksigeniatesape_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;
    $course = $formwrapper->get_course();
    if (!$course || !regions::enabled((int) $course->id)) {
        return;
    }
    if (!has_capability('local/oksigeniatesape:manageregion', context_course::instance($course->id))) {
        return;
    }
    $cm = $formwrapper->get_coursemodule();
    $challenge = $cm ? activities::challenge((int) $cm->id) : null;
    $component = 'local_oksigeniatesape';
    $mform->addElement('header', 'tesapeheader', get_string('form_header', $component));
    $mform->addElement(
        'advcheckbox',
        'tesape_challenge',
        get_string('form_challenge', $component),
        get_string('form_challenge_label', $component)
    );
    $mform->addHelpButton('tesape_challenge', 'form_challenge', $component);
    $mform->setDefault('tesape_challenge', $challenge ? 1 : 0);

    $mform->addElement('text', 'tesape_saber', get_string('form_saber', $component), ['size' => 4]);
    $mform->setType('tesape_saber', PARAM_INT);
    $mform->setDefault('tesape_saber', $challenge ? (int) $challenge->saber : 20);
    $mform->hideIf('tesape_saber', 'tesape_challenge', 'notchecked');

    $mform->addElement('text', 'tesape_gems', get_string('form_gems', $component), ['size' => 4]);
    $mform->setType('tesape_gems', PARAM_INT);
    $mform->setDefault('tesape_gems', $challenge ? (int) $challenge->gems : 0);
    $mform->hideIf('tesape_gems', 'tesape_challenge', 'notchecked');

    $steps = $challenge ? explode(',', $challenge->steps) : ['1', '2', '3', '4'];
    $boxes = [];
    for ($step = 1; $step <= 4; $step++) {
        $boxes[] = $mform->createElement('advcheckbox', 'tesape_step' . $step, '', get_string('teach_step_' . $step, $component));
        $mform->setDefault('tesape_step' . $step, in_array((string) $step, $steps, true) ? 1 : 0);
    }
    $mform->addGroup($boxes, 'tesape_steps', get_string('form_steps', $component), ' ', false);
    $mform->hideIf('tesape_steps', 'tesape_challenge', 'notchecked');

    $mform->addElement('advcheckbox', 'tesape_teamwork', get_string('teach_teamwork', $component));
    $mform->setDefault('tesape_teamwork', $challenge ? (int) $challenge->teamwork : 0);
    $mform->hideIf('tesape_teamwork', 'tesape_challenge', 'notchecked');

    $mission = missions::get((int) $course->id);
    if ($mission) {
        $chapters = [0 => get_string('form_nochapter', $component)];
        foreach ($DB->get_records('local_oksigeniatesape_chapter', ['missionid' => $mission->id], 'sortorder') as $chapter) {
            $info = array_values(array_filter($mission->data['chapters'] ?? [], fn($c) => $c['code'] === $chapter->code));
            $chapters[$chapter->id] = missions::text($info[0]['name'] ?? $chapter->code, $mission->skin);
        }
        $mform->addElement('select', 'tesape_chapter', get_string('form_chapter', $component), $chapters);
        $mform->setDefault('tesape_chapter', $challenge ? (int) $challenge->chapterid : 0);
        $mform->hideIf('tesape_chapter', 'tesape_challenge', 'notchecked');

        $items = [0 => get_string('form_noitem', $component)];
        foreach ($mission->data['items'] ?? [] as $code => $item) {
            $items[missions::item_id($code, $item['name'] ?? $code, $item['description'] ?? null, $mission->code)] =
                format_string($item['name'] ?? $code);
        }
        $mform->addElement('select', 'tesape_item', get_string('form_item', $component), $items);
        $mform->setDefault('tesape_item', $challenge ? (int) $challenge->itemid : 0);
        $mform->hideIf('tesape_item', 'tesape_challenge', 'notchecked');
    }
}

/**
 * Checks the game settings of an activity.
 *
 * @param moodleform_mod $formwrapper
 * @param array $data
 * @return array Errors.
 */
function local_oksigeniatesape_coursemodule_validation($formwrapper, $data) {
    $errors = [];
    if (empty($data['tesape_challenge'])) {
        return $errors;
    }
    $component = 'local_oksigeniatesape';
    if (isset($data['tesape_saber']) && ($data['tesape_saber'] < 0 || $data['tesape_saber'] > activities::MAX_SABER)) {
        $errors['tesape_saber'] = get_string('form_between', $component, (object) ['min' => 0, 'max' => activities::MAX_SABER]);
    }
    if (isset($data['tesape_gems']) && ($data['tesape_gems'] < 0 || $data['tesape_gems'] > activities::MAX_GEMS)) {
        $errors['tesape_gems'] = get_string('form_between', $component, (object) ['min' => 0, 'max' => activities::MAX_GEMS]);
    }
    return $errors;
}

/**
 * Saves the game settings of an activity: it becomes a challenge, changes, or stops being one.
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return stdClass
 */
function local_oksigeniatesape_coursemodule_edit_post_actions($data, $course) {
    if (!isset($data->tesape_challenge) || empty($data->coursemodule)) {
        return $data;
    }
    if (!has_capability('local/oksigeniatesape:manageregion', context_course::instance($course->id))) {
        return $data;
    }
    if (empty($data->tesape_challenge)) {
        activities::remove((int) $data->coursemodule);
        return $data;
    }
    $steps = [];
    for ($step = 1; $step <= 4; $step++) {
        if (!empty($data->{'tesape_step' . $step})) {
            $steps[] = $step;
        }
    }
    $settings = [
        'saber' => (int) ($data->tesape_saber ?? 20),
        'gems' => (int) ($data->tesape_gems ?? 0),
        'steps' => $steps,
        'teamwork' => !empty($data->tesape_teamwork),
    ];
    if (isset($data->tesape_chapter)) {
        $settings['chapterid'] = (int) $data->tesape_chapter;
    }
    if (isset($data->tesape_item)) {
        $settings['itemid'] = (int) $data->tesape_item;
    }
    activities::save((int) $course->id, (int) $data->coursemodule, $settings);
    return $data;
}

/**
 * Serves the images of the art packs to anyone logged in.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool False when there is nothing to serve.
 */
function local_oksigeniatesape_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_COURSE && $filearea === \local_oksigeniatesape\local\photos::FILEAREA) {
        // A photo of the classroom: only to who may see it.
        require_login($course, false);
        return \local_oksigeniatesape\local\photos::serve($context, $args, $options);
    }
    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== art::FILEAREA) {
        return false;
    }
    require_login(null, false);
    return art::serve($args, $options);
}
