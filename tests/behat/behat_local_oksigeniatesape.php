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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps of Tesapé for Behat: a course with the game and a mission, a student with a character, and its pages.
 *
 * @package    local_oksigeniatesape
 * @category   test
 * @copyright  2026 Oksigenia <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_oksigeniatesape extends behat_base {
    /**
     * Turns the game on in a course and installs a mission in it.
     *
     * @Given /^Tesapé is on in course "(?P<shortname_string>(?:[^"]|\\")*)" with the mission "(?P<mission_string>(?:[^"]|\\")*)"$/
     * @param string $shortname
     * @param string $mission
     */
    public function tesape_is_on_in_course(string $shortname, string $mission): void {
        $courseid = $this->course_id($shortname);
        \local_oksigeniatesape\local\regions::save($courseid, ['enabled' => 1]);
        \local_oksigeniatesape\local\missions::install($courseid, $mission, 'valley', 'es');
    }

    /**
     * Gives a student a character and makes them a member of the game of a course.
     *
     * @Given /^"(?P<username_string>(?:[^"]|\\")*)" plays Tesapé as a "(?P<role_string>(?:[^"]|\\")*)" in course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $username
     * @param string $role protector, healer, sage or scout.
     * @param string $shortname
     */
    public function plays_tesape_as(string $username, string $role, string $shortname): void {
        global $DB;
        $userid = (int) $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        $char = \local_oksigeniatesape\local\characters::create($userid, $role, 'f');
        \local_oksigeniatesape\local\engine::member($char, $this->course_id($shortname));
    }

    /**
     * Opens a page of the game of a course.
     *
     * @When /^I open the Tesapé (?P<page_string>teacher panel|world|class screen) of course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @param string $page
     * @param string $shortname
     */
    public function i_open_the_tesape_page(string $page, string $shortname): void {
        $file = ['teacher panel' => 'teach', 'world' => 'play', 'class screen' => 'board'][$page];
        $url = new moodle_url('/local/oksigeniatesape/' . $file . '.php', ['id' => $this->course_id($shortname)]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
    }

    /**
     * Closes «How Tesapé works, in two minutes» if it opened by itself (the first time a teacher opens the panel on a
     * browser).
     *
     * @When /^I close how Tesapé works if it shows$/
     */
    public function i_close_how_tesape_works_if_it_shows(): void {
        $this->getSession()->wait(2000, "document.querySelector('.how-moment') !== null");
        $this->getSession()->executeScript("var b = document.querySelector('.how-moment [data-close]'); if (b) { b.click(); }");
    }

    /**
     * The id of a course from its short name.
     *
     * @param string $shortname
     * @return int
     */
    private function course_id(string $shortname): int {
        global $DB;
        return (int) $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
    }
}
