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
 * Library of internal classes and functions for component socket into local Tepuy plugin
 *
 * @package   tepuycomponents_socket
 * @copyright 2019 David Herney - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Tepuy;

class Messages {

    public static function error($code, $vars = null, $client = null, $toclose = false) {
        $o = new \stdClass();
        $o->errorcode = $code;

        try {
            $o->error = get_string($code, 'tepuycomponents_socket', $vars);
        } catch (\Exception $e) {
            $o->error = $code;
        }

        //ToDo: implement
        $o->stacktrace = '//ToDo:';

        $msg = json_encode($o);

        if ($client) {
            $client->send($msg);

            if ($toclose) {
                throw new \Exception($code);
            } else {
                throw new AppException($code);
            }
        } else {
            return $msg;
        }
    }

}
