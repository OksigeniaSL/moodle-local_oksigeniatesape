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

**Alpha, under development.** Complete for its pilot mission, and tested with made-up classes: in the tests, a whole
class plays the mission from start to end; in the browser, made-up students play every scene of their map. What there
is today:

- **A mission to play**: the pilot mission, *Living things* (ages 9 to 11, in Spanish, with a story for Spain and
  for Paraguay), comes with the plugin. Its map has six places with their lanterns; each place has its decision
  scenes and a classroom challenge, and scenes for first steps. The scenes are played in the game: choices with
  consequences, things to put in their group, steps to put in order, hints that cost breath, an option for each role
  and endings that can be made better by playing again; what a student does in one scene changes later ones. The
  server plays each scene again with what the student did and decides the ending, so the result does not depend on
  the browser. When the class lights a lantern, the Embrollo leaves a letter in that place.
- **The game client**: the first time is a story that ends with the student's character; then the map of the
  mission, their team (with its commitments for the chapter), classroom challenges, the book, the diary and their
  character sheet with their creature. Each day the creature asks for **the page of the day**, a quick review question
  that gives breath, never knowledge. Students can name their creature and, where the course allows it, choose a hero
  name; own names wait for a teacher. Full screen, light, without external services.
- **The market of the valley and the album**: gems buy paints for the creature and auras for the hero, which
  classmates see, and the team buys the lantern of its banner together; each entry of the field guide made by the
  student or their team is a card in the album.
- **Expeditions**: a mission the course made before stays lit, and its map can be visited.
- **A living valley**: in each place a living thing peeks out each day, recognised with clues and kept in the field
  guide of each student; the Embrollo stains the place of the day with a sheet of its mistakes, which a student cleans
  for a gem, and some days it takes a page of the Great Book, which the class recovers with a question (one try a day
  each).
- **The teacher panel**, made for the phone: the first week step by step (mission, role names, how the class plays,
  teams, a rehearsal) and a list for the first day; the session as the unit of work, with its sheet (steps with
  minutes, what to say, what each step launches on the class screen, questions and mistakes to watch); «To review»,
  one tray with what waits (own names, powers, team plays of the day) and «Approve all»; «Today in the valley», the
  class with names, photos and characters; touches with a reason («Helped their team · +10 knowledge»), which the
  student sees on their tablet, editable by course; adaptations in one touch for one student or many; the mission of
  the course and how the class is doing in each scene and objective; classroom challenges (also validated without
  connection), pause and recovery, the log with undo and the settings of the course, in two layers.
- **Teams and powers**: teams made in one go with the roles spread, a weekly goal that three of every four members
  reach, and an optional ranking, hidden by default; commitments chosen by each member in each chapter (one not kept
  costs energy, all kept give breath); powers that cost breath, the cheap ones approved by themselves, some of which
  act in the game (shield, wall, breather, revive, cheer).
- **Moodle activities in the game**: any activity can be a challenge from its settings; the pilot mission brings its
  forums, assignments and field guide, which open inside the game.
- **Minigames** by steps, each with a mode without hurry: **Who is alive?** in the clearing, **The two circles**,
  **Who am I?**, **Defensa del nido** in the forest and, in every place, **the hunt for mistakes of the Embrollo**;
  each role has a passive of its own in them. And **the box of ideas** from students to their teachers.
- **The class screen**, for the projector: «Today in the valley», the map of the mission with the lanterns of the
  class and the last letter of the Embrollo, the teams with their portraits and quick touches of knowledge and gems,
  and **the event of the day** (calm, fair breeze, mysterious footprint with a question for the whole class, exchange
  of breath between teams). It refreshes itself, so what the teacher does from the phone shows up on the board. In a
  session it is the stage: the letter of the entry, a timer, the commitments, the lantern lit with the class; beside
  each team, a lantern when it says it is ready, working or needs help (never a name).
- **The battle against the Embrollo**: by turns against its knots (each knot, a mistaken idea), each team untying its
  lane (choose, put in order, sort, find the mistakes), with the students' phones or aloud by teams. Each role has a
  token once a battle (the sage strikes out a wrong option for their team, the scout tells where the answer is, the
  protector stops the counterattack, the healer doubles the team's right answers); it can be lost, and nobody loses
  anything. The knots left hanging show up in the review of the week, and a lost battle leaves them for the rematch. The final battle of the mission gives what
  the mission saves for the end; a rehearsal with three test questions checks the phones without prizes.
- **Scenes with the whole class**: a scene on the class screen where the class decides at each step, with phones or
  with A, B, C, D cards, and printable material for classes without devices: vote cards, team cards, the characters
  of the class and a diploma for each student.
- **The game engine**: knowledge with a weekly cap for each character in the whole site (what the mission gives goes
  under no cap), gems, energy and falling worn out, breath that comes back a little each day, items, undo, and
  completed Moodle activities turned into progress. Privacy provider from the first day.
- **Moodle competencies, badge and gradebook**: the mission brings its own framework of competencies (optional and
  replaceable by any of the site); the scenes leave evidence and the teacher rates; a badge of the course when the
  class lights every lantern; the rating can go to the gradebook, if the teacher wants. Each answer records how it came
  (alone at the first try, with help, after the consequence, the same mistake again), and the grid suggests a level
  that the teacher confirms or changes. A report of the class for the teacher (Excel, ODS, CSV or PDF), with what each
  student did and, by objective, the share right at the first try and how the answers came; its PDF adds what confuses
  the class (also what it voted on the class screen, as nobody's try) and the knots left hanging.
- **The workshop**: the teacher changes the texts and questions of a mission (kept apart, as a layer), sees each scene
  as a student and with the evidence each answer leaves, takes a scene out of the game, skips a place, chooses the
  classroom challenge of each place and, with the AI of the site, gets lanes proposed for each knot, taken one by
  one. Or makes a new mission from scratch in six steps with guided scenes (questions, things to sort, steps to order
  and decisions with a consequence), a checker, advice so that it is a game and «Try this scene»; a mission is
  exported as a package and loaded in another course or site. The AI of the site can draft a scene, if the site
  allows it.
- **A game that sounds and moves**: night and day themes (each world can bring its colours), sounds, the sound of each
  place and the music of the class screen from the art pack, a road that draws itself, little living things that visit
  the screens and say what they are when touched. **The encounter of the day is walked** in each place, with the hero
  and the creature as puppets that move by pieces, the voices of the clues and the music of the moment; as a list, for
  who prefers it or has «no movement».
- **Scenes as a team** on one device, with the teammates who are there (the teacher can take one back if someone was
  not); **without connection**, a scene opened stays on the device and a play finished without connection goes by
  itself later, once; and **a «What if…?»** at the end of every scene, to think about.
- **No one is left behind**: who comes back after many days or arrives late is welcomed with a short way to catch up;
  who follows the expedition from home or hospital gets the votes and battles on their device and, when they come in,
  how the battles they missed went (their team sees it only if the teacher allows it).
- **Adaptations for each student**, marked by the teacher (never a diagnosis): big text, reading aloud with a voice of
  the device, no hurry, no movement, high contrast, colours for colour blindness, no sounds and more help. The student
  can turn on those of comfort. Oksigenia Access, if the site has it, also comes into the game.
- **A plan for the next class with the AI of the site** (Moodle's AI subsystem, any provider): what confuses the
  class turned into what to review, a new example, a question to open the class and the classroom challenge that fits.
  Counts only, never a name.

The art comes in **art packs** (`cli/art.php`): the first one, *The Valley of Tesapé*, with its heroes, creature,
maps, scenes, sounds and music, is distributed apart from the plugin.

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
was played (what each student bought in the market for their character stays with them).

Minors play this game, so it keeps only what the game needs: no external services, no CDN, no tracking, no individual
public rankings and nothing bought with money (the market of the valley takes only gems won in the game). The ideas
students send stay in Moodle, for their teachers only; the images of art packs are served by Moodle itself. The plan
for the next class goes to the AI provider the site has chosen, with counts and the content of the mission only.

## Development

To try the game on a development site, `cli/demo.php` makes a made-up class: a course with the mission and its
activities, a teacher, students with invented names, teams, characters and, with `--weeks`, some weeks of progress,
powers waiting and ideas in the box. Never on a site with real students.

```bash
php local/oksigeniatesape/cli/demo.php --run --weeks=3
```

An art pack is a zip with `pack.json` (code, name, version, and `files`: keys such as `character.storyheroes.healer`,
`creature.2.feathers`, `icon.saber` or `map.livingthings`, each with an image of the zip in PNG, WebP or JPEG). Under
`sound.` and `voice.` it may bring sounds and what the characters say (MP3, Ogg or M4A), and under `rig.` and `anim.`
the puppets of the moments that are played: the pieces of a figure in a WebP with its atlas in JSON
(`rig.<key of the picture it was cut from>` and `….atlas`), the skeleton of each kind of body (`rig.hero`,
`rig.creature`) and its animations (`anim.rig.hero`, `anim.rig.creature`):

```bash
php local/oksigeniatesape/cli/art.php --install=/path/to/pack.zip
```

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`). The tests run
with `moodle-plugin-ci` in GitHub Actions on Moodle 4.5, 5.2 and 5.3 (PostgreSQL), on 5.3 with MariaDB 11.4 too, and on
`main` to see what is coming. See
[CONTRIBUTING.md](CONTRIBUTING.md).

## License

GNU GPL v3 or later. © 2026 Oksigenia. The fonts Lexend and Cherry Swash, in `app/fonts`, are under the SIL Open Font
License 1.1 (listed in `thirdpartylibs.xml`).

The sample mission, in `missions/livingthings`, is under two licences, to choose from: CC BY-SA 4.0 or GNU GPL v3 or
later ([its licence](missions/livingthings/LICENSE.md)). The art of the valley comes in its own package, with its own
licence: free to use with Tesapé.
