# Security policy

## Reporting a vulnerability

If you find a security issue in `local_oksigeniatesape`, **do not open a public GitHub issue**. Use one of these
instead:

1. **GitHub private vulnerability reporting** (preferred):
   <https://github.com/OksigeniaSL/moodle-local_oksigeniatesape/security/advisories/new>
2. **Email**: `dev@oksigenia.cc` (PGP key
   [fingerprint `4D0E 67BD 1935 3CE2 A8E8  267F 8290 9111 546B AD97`](https://oksigenia.com/contacto_publickey.asc)).

Please include the affected plugin version, Moodle and PHP versions, a minimal proof of concept or reproduction
steps, and your assessment of impact.

## Response timeline

| Step | Target |
|---|---|
| Acknowledgement of the report | Within 5 business days |
| Initial assessment + severity rating | Within 10 business days |
| Patched release | Depends on severity; critical issues get out-of-band releases |

## Supported versions

Security patches land on the latest released line. Older versions are not back-patched. While the plugin is in
alpha, only the latest release is supported.

## Scope

This policy covers the code in this repository. It does **not** cover Moodle core; for Moodle core issues follow the
[official Moodle security process](https://docs.moodle.org/en/Reporting_security_issues).

The players are mostly minors. The plugin keeps each student's character and what they do in the game in each
course, and shows teachers the students of their courses. Reports about data reaching users who should not see it
(a student seeing why a classmate was paused, a teacher seeing students of another course) are in scope.

## Out of scope

- Configuration choices by the site administrator (e.g. capability overrides that grant the game's teacher
  capabilities to roles you did not intend).
- Visual collisions with site-specific themes (file as a bug).
