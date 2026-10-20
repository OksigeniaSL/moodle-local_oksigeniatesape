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
 * Installs an art pack for the game, or turns the packs off.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'install' => '', 'off' => false, 'remove' => '', 'list' => false],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)), 2);
}

if ($options['help'] || ($options['install'] === '' && !$options['off'] && $options['remove'] === '' && !$options['list'])) {
    echo "Installs an art pack for Tesapé in the site, takes one out, lists them, or turns the packs off (the game
goes back to its own drawings).

A pack is a zip with pack.json and its images (png, webp or jpg). pack.json has a code, a name, a version, a kind
and files: a map from keys (character.storyheroes.healer, character.storyheroes.healer.fairy.f.1, creature.2.feathers,
icon.saber, map.livingthings…) to the images in the zip. The kind is its layer: world (the default; the site has one),
style (the heroes) or mission (its scenes, album and maps). Each key is taken from the mission, then the style, then
the world.

Options:
--install=FILE      Install the pack in this zip
--remove=CODE       Take the pack with this code out, with its files
--list              List the packs of the site
--off               Turn the packs off
-h, --help          Print this help

Example:
\$ sudo -u www-data /usr/bin/php local/oksigeniatesape/cli/art.php --install=/tmp/valley.zip
";
    exit(0);
}

\core\session\manager::set_user(get_admin());
if ($options['off']) {
    \local_oksigeniatesape\local\art::off();
    cli_writeln('Art packs off.');
    exit(0);
}
if ($options['remove'] !== '') {
    $there = \local_oksigeniatesape\local\art::remove((string) $options['remove']);
    cli_writeln($there ? 'Taken out: ' . $options['remove'] : 'No pack ' . $options['remove'] . ' in the site.');
    exit(0);
}
if ($options['list']) {
    $art = \local_oksigeniatesape\local\art::manifest();
    foreach ($art['layers'] ?? [] as $layer) {
        cli_writeln($layer['kind'] . ': ' . $layer['code'] . ' (version ' . $layer['version'] . ')');
    }
    cli_writeln(count($art['files'] ?? []) . ' keys in all.');
    exit(0);
}
if (!is_readable($options['install'])) {
    cli_error('Cannot read ' . $options['install']);
}
$manifest = \local_oksigeniatesape\local\art::install($options['install']);
cli_writeln('Installed: ' . $manifest['name'] . ' (' . $manifest['code'] . ', ' . $manifest['kind'] . ', version ' .
    $manifest['version'] . '), ' . count($manifest['files']) . ' images.');
