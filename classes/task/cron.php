<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A scheduled tasks for module cron.
 *
 * @package   tepuycomponents_socket
 * @copyright 2021 David Herney - https://bambuco.co
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tepuycomponents_socket\task;

defined('MOODLE_INTERNAL') || die();

class cron extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('crontask', 'tepuycomponents_socket');
    }

    /**
     * Run cron.
     */
    public function execute() {
        global $CFG, $USER, $DB, $PAGE;

        define("APP_SOURCE_PATH", $CFG->dirroot . "/local/tepuy/components/socket/server/src");
        require $CFG->dirroot . '/local/tepuy/components/socket/server/vendor/autoload.php';

        mtrace('Sending execron action...');

        $config = get_config('tepuycomponents_socket');

        $uris = explode("\n", $config->components_socket_cronuri);

        foreach ($uris as $uri) {
            $uri = trim($uri);

            if (!empty($uri)) {
                $to = strpos($uri, '?');
                $to = $to === false ? strlen($uri) : $to;
                $publicuri = substr($uri, 0, $to);

                mtrace('Calling URI: ' . $publicuri);

                $client = new \WebSocket\Client($uri . '&cron=true');
                $client->send('{"action": "execron" }');

                mtrace('Response: ' . $client->receive());
            }
        }
    }

}
