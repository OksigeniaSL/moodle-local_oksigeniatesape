# Changelog

## 0.2.0 (in progress)

- **The game engine**: knowledge with the weekly cap of the course and the daily cap for what is given by hand;
  gems; energy, with its daily cap of losses and falling worn out at 0 (without losing knowledge or level) until the
  recovery challenge or a rescue; breath for the powers; expedition items without duplicates; pausing a student;
  undoing any entry of the log (it adds the opposite entry and keeps the original, marked).
- **Classroom challenges**: challenges that happen off the screen (a drawing, a model, a talk, a reading), with no
  Moodle activity behind them. The teacher says done, done with a mention (extra gems, never extra knowledge) or not
  yet, for a student or a whole team, with a note; changing their word gives or takes back what the challenge gives.
- **From Moodle to the game**: a completed activity gives the knowledge of its challenge, or the course rule for
  activities that are not challenges (10 unless the teacher changes it); once per activity, and taken back if the
  completion is. A deleted activity stops being a challenge.
- Energy and breath are filled once a day for those who are playing.
- The look of the character is kept as data (parts and colours), ready for the character workshop.
- **Web services** for the game client and the teacher panel: the world of a student (character, team, classroom
  challenges, own log), making the character and changing its look; for teachers, the class, the settings of the
  course, giving by hand, the word on classroom challenges, making and deleting them, pausing, recovery, steps, the
  log of the course and undo. Classmates see each other's character and energy inside the team, never why someone
  fell or was paused.

## 0.1.0 (2026-10-10)

First alpha: the ground the game is built on. Nothing is shown to students or teachers yet.

- **Tables** of the game: characters (one per student for the whole site), regions (the game in a course), members,
  teams, challenges, the log of everything that adds and takes away, powers and their requests, items and
  inventories, missions, chapters, scenes and their attempts, and minigame results.
- **Capabilities**: play; set the game up in a course; give by hand; approve powers; pause; see and undo the log;
  run the class screen.
- **Privacy provider**: the character in the student's user context, what they did in each course in the course;
  export and deletion of all of it. Teachers stay in the students' records without their name when their data is
  deleted.
- **Levels**: the knowledge each level asks for (the first ones come soon), the five stages of the character's look
  and the weekly cap per course.
- **A made-up class** for development sites, `cli/demo.php`: a course, a teacher, students with invented names,
  teams with the four roles, steps and, if asked, some weeks of progress within the weekly cap.
