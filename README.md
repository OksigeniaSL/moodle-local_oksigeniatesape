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

**Alpha, under development: not for use with students yet.** This first version lays the ground the game is built
on:

- The tables of the game: characters, courses (regions), members, teams, challenges, the log of everything that
  adds and takes away, powers and their requests, items, missions, chapters, scenes and their attempts, and
  minigame results.
- The capabilities: students play; teachers set the game up, give knowledge by hand, approve powers, pause a
  student, see and undo the log, and run the class screen.
- The privacy provider, from the first day: export and deletion of everything the game keeps about a student.
- Levels, the stages of the character's look and the weekly cap per course, with their tests.
- A made-up class to try it on a development site (see Development).

Next come the game client with the decision scenes of the pilot mission, the teacher panel, the rules that turn
Moodle activity into progress, the minigames and the class screen.

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
data is deleted.

Minors play this game, so it keeps only what the game needs: no external services, no CDN, no tracking, no
individual public rankings and no purchases.

## Development

To try the game on a development site, `cli/demo.php` makes a made-up class: a course, a teacher, students with
invented names, teams, characters and, with `--weeks`, some weeks of progress. Never on a site with real students.

```bash
php local/oksigeniatesape/cli/demo.php --run --weeks=3
```

Code, comments and commit messages in English; texts in the language packs (`lang/en`, `lang/es`). The tests run
with `moodle-plugin-ci` in GitHub Actions on Moodle 4.5, 5.2 and 5.3, and on `main` to see what is coming. See
[CONTRIBUTING.md](CONTRIBUTING.md).

## License

GNU GPL v3 or later. © 2026 Oksigenia.
