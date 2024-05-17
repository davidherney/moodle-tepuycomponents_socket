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

defined('MOODLE_INTERNAL') || die();

/**
 * Get a skey for a socket session.
 *
 * @param object $cm
 * @param object $course
 * @param int $groupid
 * @return bool|string Returns the skey or false
 */
function local_tepuy_component_socket_getskey($cm, $uid, $course, $groupid) {
    global $USER, $DB;

    if ($sess = $DB->get_record('local_tepuy_socket_sessions', array('cmid' => $cm->id,
                                                                    'userid' => $USER->id,
                                                                    'groupid' => $groupid))) {
        // This will update logged user information.
        $sess->ip       = $USER->lastip;
        $sess->lastping = time();

        // Sometimes $USER->lastip is not setup properly during login.
        // Update with current value if possible or provide a dummy value for the db.
        if (empty($sess->ip)) {
            $sess->ip = getremoteaddr();
        }

        if (($sess->course != $course->id) or ($sess->userid != $USER->id)) {
            return false;
        }

        $DB->update_record('local_tepuy_socket_sessions', $sess);

    } else {
        $sess = new stdClass();
        $sess->cmid     = $cm->id;
        $sess->uid      = $uid;
        $sess->userid   = $USER->id;
        $sess->groupid  = $groupid;
        $sess->ip       = $USER->lastip;
        $sess->lastping = $sess->firstping = $sess->lastmessageping = time();
        $sess->skey      = random_string(32);
        $sess->course   = $course->id;

        // Sometimes $USER->lastip is not setup properly during login.
        // Update with current value if possible or provide a dummy value for the db.
        if (empty($sess->ip)) {
            $sess->ip = getremoteaddr();
        }

        $sess->id = $DB->insert_record('local_tepuy_socket_sessions', $sess, true);

    }

    return $sess;
}
