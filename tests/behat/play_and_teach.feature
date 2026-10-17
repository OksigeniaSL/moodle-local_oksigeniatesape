@local @local_oksigeniatesape
Feature: A teacher runs the game from the panel and a student plays the mission
  In order to use the game in class
  As a teacher and as a student
  We need the panel and the world to work in the browser

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And Tesapé is on in course "C1" with the mission "livingthings"
    And "student1" plays Tesapé as a "healer" in course "C1"

  @javascript
  Scenario: The teacher goes through the tabs of the panel
    Given I log in as "teacher1"
    When I open the Tesapé teacher panel of course "C1"
    Then I should see "Teacher panel"
    And I should see "Student One"
    When I click on "nav.tabs [data-tab='mission']" "css_element"
    Then I should see "Los seres vivos"
    When I click on "nav.tabs [data-tab='competencies']" "css_element"
    Then I should see "Which competency each objective leaves evidence in"
    When I click on "[data-tab='settings']" "css_element"
    Then I should see "Cover of the course"

  @javascript
  Scenario: The student begins the mission and plays the start of a scene
    Given I log in as "student1"
    When I open the Tesapé world of course "C1"
    Then I should see "Begin the mission"
    When I click on "Begin the mission" "button"
    And I click on "[data-chapter]" "css_element"
    And I click on "[data-scene]" "css_element"
    # A told moment is a dialogue: its parchment goes on with the same button, «Next» or «Continue».
    Then ".dlg-box [data-next]" "css_element" should exist
    And I click on ".dlg-box [data-next]" "css_element"
