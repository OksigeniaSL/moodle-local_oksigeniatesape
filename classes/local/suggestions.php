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

namespace local_oksigeniatesape\local;

/**
 * Suggestions of teachers and administrators to Oksigenia, the makers of Tesapé: off unless the site turns them on.
 * They go from the server of the site, never from the browser, with only what the privacy provider declares. Students
 * never send anything out: their ideas stay in the box of ideas of their course.
 *
 * @package    local_oksigeniatesape
 * @copyright  2026 Oksigenia SL <dev@oksigenia.cc>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class suggestions {
    /** @var string The receiver of Oksigenia: here only, so that a move is one change in one version. */
    public const RECEIVER = 'https://n8n.oksigenia.com/webhook/tesape-sugerencias';

    /** @var int Most characters a suggestion carries (the receiver cuts the rest). */
    public const MAX = 3500;

    /** @var int Suggestions one person can send in an hour: the form is no way to fill the box of the makers. */
    public const PER_HOUR = 3;

    /**
     * Whether the site sends suggestions (an administrator turns it on).
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config('local_oksigeniatesape', 'suggestions');
    }

    /**
     * Sends a suggestion of a teacher or an administrator.
     *
     * @param int $userid Who sends it.
     * @param string $text
     * @param string $email To answer them, only if they wrote it.
     * @param int|null $now
     */
    public static function send(int $userid, string $text, string $email = '', ?int $now = null): void {
        global $CFG;
        $now = $now ?? time();
        if (!self::enabled()) {
            throw new \moodle_exception('suggestoff', 'local_oksigeniatesape');
        }
        $text = trim(clean_param($text, PARAM_NOTAGS));
        if ($text === '') {
            throw new \invalid_parameter_exception('A suggestion needs some text');
        }
        $email = trim($email);
        if ($email !== '' && !validate_email($email)) {
            throw new \moodle_exception('suggestemail', 'local_oksigeniatesape');
        }
        $cache = \cache::make('local_oksigeniatesape', 'suggested');
        $sent = array_values(array_filter($cache->get($userid) ?: [], fn($time) => $time > $now - HOURSECS));
        if (count($sent) >= self::PER_HOUR) {
            throw new \moodle_exception('suggestbusy', 'local_oksigeniatesape');
        }
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_oksigeniatesape');
        $body = [
            'text' => \core_text::substr($text, 0, self::MAX),
            'role' => is_siteadmin($userid) ? 'admin' : 'teacher',
            'tesape_version' => (string) ($plugin->release ?? ''),
            'moodle_version' => (string) $CFG->release,
            'lang' => current_language(),
        ];
        if ($email !== '') {
            $body['email'] = $email;
        }
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json']);
        $curl->post(self::RECEIVER, json_encode($body), ['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code === 0 || $code >= 500) {
            throw new \moodle_exception('suggestlater', 'local_oksigeniatesape');
        }
        if ($code === 429) {
            throw new \moodle_exception('suggestbusy', 'local_oksigeniatesape');
        }
        if ($code >= 400) {
            throw new \moodle_exception('suggestinvalid', 'local_oksigeniatesape');
        }
        $sent[] = $now;
        $cache->set($userid, $sent);
    }
}
