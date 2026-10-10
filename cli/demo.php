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
 * Makes a made-up class to try the game on a development site.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'run' => false,
        'shortname' => 'TESAPE-DEMO',
        'students' => 25,
        'teamsize' => 5,
        'family' => 'storyheroes',
        'weeks' => 0,
        'seed' => 1,
        'mission' => 'livingthings',
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || !$options['run']) {
    echo "Makes a made-up class to try Tesapé on a development site: a course with the mission and its Moodle
activities, a teacher, students with invented names, teams, characters and, if asked, some weeks of progress (with
powers waiting for the teacher and ideas in the box). Nobody in it exists. Do not run it on a site with real students.

The users are called " . \local_oksigeniatesape\local\demo::USERNAME_PREFIX . "<courseid>NN and cannot log in until
someone sets their password.

Options:
--run               Make the class (without it, this help is shown)
--shortname=NAME    Short name of the new course (default TESAPE-DEMO)
--students=N        Students, up to 30 (default 25)
--teamsize=N        Students per team (default 5)
--family=FAMILY     animals, dinosaurs, storyheroes, epic, elements or nomagic (default storyheroes)
--weeks=N           Weeks of made-up progress (default 0)
--seed=N            Seed of the random choices: the same seed gives the same class (default 1)
--mission=CODE      Mission to install (default livingthings, the sample that comes with the plugin; empty for none)
-h, --help          Print this help

Example:
\$ sudo -u www-data /usr/bin/php local/oksigeniatesape/cli/demo.php --run --weeks=3
";
    exit(0);
}

if ($DB->record_exists('course', ['shortname' => $options['shortname']])) {
    cli_error('There is already a course with the short name ' . $options['shortname'] . '; choose another with --shortname.');
}

\core\session\manager::set_user(get_admin());
$class = \local_oksigeniatesape\local\demo::create_class(
    $options['shortname'],
    (int) $options['students'],
    (int) $options['teamsize'],
    $options['family'],
    (int) $options['weeks'],
    (int) $options['seed'],
    (string) $options['mission']
);
cli_writeln('Course: ' . (new moodle_url('/course/view.php', ['id' => $class->course->id]))->out(false));
cli_writeln('Teacher: ' . $class->teacher->username);
cli_writeln('Students: ' . count($class->students) . ' in ' . count($class->teams) . ' teams');
