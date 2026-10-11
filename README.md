# Classroom role-playing game for Moodle (Oksigenia Tesapé)

[![License: GPLv3](https://img.shields.io/badge/license-GPLv3+-blue.svg)](LICENSE)
![Moodle](https://img.shields.io/badge/Moodle-4.5%E2%80%935.3-orange)
![Status](https://img.shields.io/badge/status-alpha-red)

**Tesapé** is a role-playing game for the classroom, played on top of Moodle. Each student has one character for
the whole site; each course is a region of the world, with its own mission, team and powers. Students learn by
playing decision scenes and minigames, by doing the course activities and by helping their team. Teachers set the
game up in their course, follow it from their panel and run it on the classroom screen.

Moodle is the backbone the students do not see: their accounts, courses, groups, activity completion, backups and
privacy come from Moodle, and the game is a full-screen application of its own.

*Tesapé* means "light" in Guarani, from the verb to light up, to illuminate.

[Leer en español](README.es.md)

## Status

**Alpha, under development: not for use with students yet.** What there is today:

- **A mission to play**: the pilot mission, *Living things* (ages 9 to 11, in Spanish), comes with the plugin. Its
  map has six places with their lanterns; each place has its decision scenes and a classroom challenge. The scenes
  are played in the game: choices with consequences, things to put in their group, steps to
  put in order. The server plays each scene again with what the student did and decides the ending, so the result
  does not depend on the browser. The first good ending gives knowledge, pages of the book, items and, in the first
  scene, the companion creature; lanterns light for the student and, when half of the class has them, for the class.
- **The game client**: the first time is a story that ends with the student's character; then the map of the
  mission, their team, classroom challenges, the book, the diary and their character sheet with their creature. Full
  screen, light, without external services.
- **The teacher panel**, made for the phone: the class with names, photos and characters, touches of knowledge, gems
  and breath, the mission of the course (with its story and local examples) and how the class is doing in each
  scene and objective, with the mix-ups that come up most; classroom challenges (also validated without
  connection), pause and recovery, the log with undo and the settings of the course.
- **Teams and powers**: teams made in one go with the roles spread, a weekly goal for each team and an optional
  ranking; powers that cost breath, asked by students and approved by the teacher with a tap, some of which act in
  the game (shield, wall, breather, revive, cheer).
- **Moodle activities in the game**: any activity can be a challenge from its settings; the pilot mission brings its
  forums, assignments and field guide, which open inside the game.
- **Defensa del nido**, the first minigame, by steps and with a mode without hurry; and **the box of ideas** from
  students to their teachers.
- **The class screen**, for the projector: the map of the mission with the lanterns of the class, the teams with their
  portraits and quick touches of knowledge and gems, and **the event of the day** (calm, fair breeze, mysterious
  footprint with a question for the whole class, exchange of breath between teams). It refreshes itself, so what the
  teacher does from the phone shows up on the board.
- **The game engine**: knowledge with weekly and daily caps, gems, energy and falling worn out, breath, items, undo,
  and completed Moodle activities turned into progress. Privacy provider from the first day.

- **The battle against the Embrollo**: questions of the mission for the whole class, with the students' phones or
  aloud by teams; each right answer takes life from it, a wrong one takes nothing from anybody, and winning gives saber
  and gems, and an object of the mission for the team that helped most. The sage can glance at the next question.
- **Scenes with the whole class**: a scene on the class screen where the class decides at each step, with phones or
  with A, B, C, D cards, and printable material for classes without devices: vote cards, team cards, the characters
  of the class and a diploma for each student.

- **A plan for the next class with the AI of the site** (Moodle's AI subsystem, any provider): what confuses the
  class turned into what to review, a new example, a question to open the class and the classroom challenge that fits.
  Counts only, never a name.

The game is ready for **art packs** (`cli/art.php`), and its first pack is nearly done.

## Requirements

- Moodle 4.5 to 5.3: the branches that still get security fixes. Developed on 5.3.
- PHP as required by your Moodle.

## Install

```bash
cd /path/to/moodle/public/local/   # /path/to/moodle/local/ before Moodle 5.1
git clone https://github.com/OksigeniaSL/moodle-local_oksigeniatesape.git oksigeniatesape
```

Then visit *Site administration → Notifications* to finish the install.

## Privacy

The character belongs to the student's user context; what happens in a course belongs to the course. The privacy
provider covers every table that holds something about a student (export and deletion), and teachers who paused a
student, gave them something or decided on a power stay in the student's records without their name once their own
data is deleted. When a course is deleted, the game in it goes too; the characters stay with their students. Course
backups keep how the game is set up, never what students did; a course reset that takes the students out clears what
was played.

Minors play this game, so it keeps only what the game needs: no external services, no CDN, no tracking, no
individual public rankings and no purchases. The ideas students send stay in Moodle, for their teachers only; the
images of art packs are served by Moodle itself. The plan for the next class goes to the AI provider the site has
chosen, with counts and the content of the mission only.

## Development

To try the game on a development site, `cli/demo.php` makes a made-up class: a course with the mission and its
activities, a teacher, students with invented names, teams, characters and, with `--weeks`, some weeks of progress,
powers waiting and ideas in the box. Never on a site with real students.

```bash
php local/oksigeniatesape/cli/demo.php --run --weeks=3
```

An art pack is a zip with `pack.json` (code, name, version, and `files`: keys such as `character.storyheroes.healer`,
`creature.2.feathers`, `icon.saber` or `map.livingthings`, each with an image of the zip in PNG, WebP or JPEG):

```bash
php local/oksigeniatesape/cli/art.php --install=/path/to/pack.zip
```

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`). The tests run
with `moodle-plugin-ci` in GitHub Actions on Moodle 4.5, 5.2 and 5.3, and on `main` to see what is coming. See
[CONTRIBUTING.md](CONTRIBUTING.md).

## License

GNU GPL v3 or later. © 2026 Oksigenia. The fonts Lexend and Cherry Swash, in `app/fonts`, are under the SIL Open Font
License 1.1 (listed in `thirdpartylibs.xml`).
