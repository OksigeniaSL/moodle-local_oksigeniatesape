# Contributing to local_oksigeniatesape

Thanks for taking the time. Tesapé is in alpha and changes fast; open an issue before a large PR so we can agree on
the approach.

## Code style

- PHP follows the Moodle Coding Style: <https://moodledev.io/general/development/policies/codingstyle>.
- License header: GPL v3 or later, copyright `Oksigenia SL <dev@oksigenia.cc>`.
- Code, comments and commit messages in **English**. Lang files carry the localised strings.
- No commit message carries the signature of an AI tool. Turn on the hook once in your clone:
  `git config core.hooksPath .githooks`; CI checks the new commits too.
- The code must run on every supported Moodle branch (4.5 to 5.3) and on PHP 8.1.

## Translations

Translations go through **AMOS**, Moodle's translation tool, at <https://lang.moodle.org> once the plugin is on
the Moodle Marketplace. GitHub PRs are for the English strings and for fixes.

## Contributing content

Content does not come through pull requests to this repository:

- **A mission:** the Workshop of the game exports it as a zip, with its draft inside so someone else can open and adapt it. Whoever
  administers a site uploads it in *Missions*. A package can bring its pictures in a folder `images/` (PNG, WebP or JPEG, each named by
  its key, such as `scene.clearing.png`).
- **A translation or an adaptation of a mission:** a layer, the file *Download my changes* of the Workshop. A teacher loads it in their
  course with *Load changes from a file*; it stays through new versions of the mission, and what the version of that course does not have is left out and reported.
- **Another subject:** `missions/examples/` has the nest with primes and composites and the terrarium with words, with the same data and
  engines as the pilot mission.

Each mission states its licence in its `rights` field. The pilot mission is CC BY-SA 4.0 or GPL v3 or later.

## Tests

GitHub Actions runs `moodle-plugin-ci` (validate, lint, code checker, PHPDoc, savepoints, PHPUnit and Behat), a
secret scan (Gitleaks) and the check of commit messages. To run
the PHPUnit tests on your own site:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_oksigeniatesape_testsuite
```

## Pull requests

- One topic per PR. Mixed feature + refactor PRs get split before review.
- Reference an issue when one exists.
- Keep diffs small. If the PR stores anything new about users, update the privacy provider and its tests in the
  same PR.
- Bump `version.php` and add a `CHANGELOG.md` entry when the change reaches users.

## Reporting bugs

Open an issue with: plugin version, Moodle version, PHP version, theme, browser, and a minimal reproduction.

## License

By contributing you agree your changes are licensed under GPL v3 or later, the same license as the plugin.
