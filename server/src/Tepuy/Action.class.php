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
use Tepuy\SocketSessions;

class Action {

    // The only valid actions list.
    const AVAILABLES = array('chatmsg', 'chathistory', 'playerconnected', 'playerdisconnected');

    public $action;

    public $request;

    public $from;

    public $session;

    public $user;

    public static $chats = array();

    public function __construct($from, $request, $validate = true) {
        global $DB;

        if ($validate) {
            if (!in_array($request->action, self::AVAILABLES)) {

                if ($request->action == 'execron') {

                    //ToDo: validar con una clave configurada desde la administración y pasada por el cron.

                } else {
                    // Check specific game actions.
                    $gameactions = SocketSessions::getGameActions($from->resourceId);
                    if (!in_array($request->action, $gameactions)) {
                        Messages::error('invalidaction', $request->action, $from);
                    }
                }
            }
        }

        $this->from = $from;
        $this->action = $request->action;
        $this->request = $request;
        $this->session = SocketSessions::getSSById($from->resourceId);
        $this->user = $DB->get_record('user', array('id' => $this->session->userid));
    }

    public function run($params = null) {
        $method = 'action_' . $this->action;

        if ($params) {
            return $this->$method($params);
        } else {
            return $this->$method();
        }
    }

    // General actions.
    private function action_chatmsg($params = null) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/chat/lib.php');

        if ($params && is_array($params) && isset($params['groupid'])) {
            $chatuser = $this->getChatUserByGroupid($params['groupid']);
        } else {
            $chatuser = $this->getChatUser();
        }

        if (!property_exists($this->request, 'issystem')) {
            $this->request->issystem = false;
        }

        if (!property_exists($this->request, 'tosender')) {
            $this->request->tosender = false;
        }

        if (property_exists($this->request, 'data') && !is_string($this->request->data)) {
            Logging::trace(Logging::LVL_DEBUG, 'Chat message is not a string. Develop error?');
            return false;
        }

        $data = new \stdClass();
        $moodlemsg = '';

        if ($this->request->issystem) {
            if (strpos($this->request->data, 'action') === 0) {
                if ($params && isset($params['lang'])) {
                    $a = $params['lang'];
                    $moodlemsg = $this->request->data . '|' . $a;
                } else {
                    $a = $this->user->firstname;
                }

                $data->msg = get_string('message' . $this->request->data, 'tepuycomponents_socket', $a) . '';
            }
            else {
                $msg->msg = $this->request->data;
            }
        } else {
            $data->msg = $this->request->data;
        }

        if (empty($moodlemsg)) {
            $moodlemsg = $this->request->data;
        }

        $originalaction = property_exists($this->request, 'originalaction') ? $this->request->originalaction : null;

        // Not save the playerconnect/disconnect because generates noise.
        if (!in_array($originalaction, ['playerconnected', 'playerdisconnected'])) {
            //A Moodle action to save a chat message.
            $msgid = chat_send_chatmessage($chatuser, $moodlemsg, $this->request->issystem);
        } else {
            $msgid = 0;
        }

        $data->id = $msgid;
        $data->user = new \stdClass();
        $data->user->id = $this->user->id;
        $data->user->name = $this->user->firstname;
        $data->timestamp = time();
        $data->issystem = $this->request->issystem ? 1 : 0;
        $data->originalaction = $originalaction;

        $msg = $this->getResponse($data);
        $msg = json_encode($msg);

        $clients = SocketSessions::getClientsById($this->from->resourceId);
        foreach ($clients as $client) {
            if (($client !== $this->from || $this->request->tosender) &&
                    SocketSessions::getSSById($client->resourceId)->groupid == $chatuser->groupid) {
                // The sender is not the receiver, send to each client connected into same group.
                $client->send($msg);
            }
        }
        Logging::trace(Logging::LVL_DETAIL, 'Chat message sended.');

        return true;
    }

    private function action_chathistory() {
        global $DB;

        $n = 10;
        $s = 0;
        if (property_exists($this->request, 'data')) {
            if (property_exists($this->request->data, 'n')) {
                $n = $this->request->data->n;
            }

            if (property_exists($this->request->data, 's')) {
                $s = $this->request->data->s;
            }
        }

        $chatuser = $this->getChatUser();

        $params = array('groupid' => $chatuser->groupid, 'chatid' => $chatuser->chatid);

        $scondition = '';
        if ($s) {
            $params['s'] = $s;
            $scondition = " AND m.id < :s";
        }

        $groupselect = $chatuser->groupid ? " AND (groupid=" . $chatuser->groupid . " OR groupid=0) " : "";

        $sql = "SELECT m.id, m.message, m.timestamp, m.issystem, u.id AS userid, u.firstname
                    FROM {chat_messages_current} AS m
                    INNER JOIN {user} AS u ON m.userid = u.id
                    WHERE chatid = :chatid " . $scondition . $groupselect . ' ORDER BY timestamp DESC';

        $data = array();
        if ($msgs = $DB->get_records_sql($sql, $params, 0, $n)) {
            foreach($msgs as $one) {
                $msg = new \stdClass();
                $msg->id = $one->id;
                $msg->user = new \stdClass();
                $msg->user->id = $one->userid;
                $msg->user->name = $one->firstname;
                $msg->issystem = $one->issystem;
                $msg->originalaction = null;

                if ($msg->issystem) {
                    if (strpos($one->message, 'action') === 0) {

                        if (strpos($one->message, '|') > 0) {
                            $parts = explode('|', $one->message);
                            $msg->msg = get_string('message' . $parts[0], 'tepuycomponents_socket', $parts[1]) . '';
                            $msg->originalaction = substr($parts[0], 6);
                        } else {
                            $msg->msg = get_string('message' . $one->message, 'tepuycomponents_socket', $one->firstname) . '';
                            $msg->originalaction = substr($one->message, 6);
                        }

                    } else if (in_array($one->message, array('beepseveryone', 'beepsyou', 'enter', 'exit', 'youbeep'))) {
                        $msg->msg = get_string('message' . $one->message, 'mod_chat', $one->firstname);
                    }
                    else {
                        $msg->msg = $one->message;
                    }
                } else {
                    $msg->msg = $one->message;
                }

                $msg->timestamp = $one->timestamp;
                $data[] = $msg;
            }
        }

        $msg = $this->getResponse($data);
        $msg = json_encode($msg);

        $this->from->send($msg);

        Logging::trace(Logging::LVL_DETAIL, 'Chat history message sended.');

        return true;
    }

    private function action_playerconnected() {

        if (SocketSessions::getSetting($this->from->resourceId, 'bygroup') && !$this->session->groupid) {
            Messages::error('notgroupnotteam', null, $this->from);
        }

        $data = new \stdClass();
        $data->id = $this->user->id;
        $data->name = $this->user->firstname;

        $msg = $this->getResponse($data);
        $msg = json_encode($msg);

        $clients = SocketSessions::getClientsById($this->from->resourceId);
        foreach ($clients as $client) {
            if ($client !== $this->from &&
                    SocketSessions::getSSById($client->resourceId)->groupid == $this->session->groupid) {
                // The sender is not the receiver, send to each client connected into same group.
                $client->send($msg);
            }
        }

        $this->notifyActionToAll();

        return true;
    }

    private function action_playerdisconnected() {

        if (SocketSessions::getSetting($this->from->resourceId, 'bygroup') && !$this->session->groupid) {
            Messages::error('notgroupnotteam', null, $this->from);
        }

        $data = new \stdClass();
        $data->id = $this->user->id;
        $data->name = $this->user->firstname;

        $msg = $this->getResponse($data);
        $msg = json_encode($msg);

        $clients = SocketSessions::getClientsById($this->from->resourceId);
        foreach ($clients as $client) {
            if ($client !== $this->from &&
                    SocketSessions::getSSById($client->resourceId)->groupid == $this->session->groupid) {
                // The sender is not the receiver, send to each client connected into same group.
                $client->send($msg);
            }
        }

        $this->notifyActionToAll();

        return true;
    }

    private function action_execron() {

        $res = array();
        $processed = 0;

        $gamekey = SocketSessions::getGameKey($this->from->resourceId);
        $res[$gamekey] = new \stdClass();

        //ToDo: We need change this for any game.
        /*if ($gamekey == 'SmartCity') {
            $matches = SmartCity::getMatches();

            $processed = 0;
            foreach($matches as $match) {
                $game = new SmartCity($match->groupid);

                $activegame = $game->currentGame();

                if($game->summary->state != SmartCity::STATE_ENDED && $activegame) {
                    $game->cron($this);
                    $processed++;
                }
            }

        }*/

        $res[$gamekey]->matches = $processed;

        $msg = $this->getResponse($res);
        $msg = json_encode($msg);

        $this->from->send($msg);

        Logging::trace(Logging::LVL_DETAIL, 'Cron excecuted.');

        return true;
    }

    // Internal methods.
    private function getChatUser() {
        global $DB;

        if (!isset(self::$chats[$this->from->resourceId])) {
            if (!$socketchat = $DB->get_record('local_tepuy_socket_chat', array('sid' => $this->session->id))) {
                Messages::error('chatnotavailable', null, $this->from);
            }

            $chatuser = $DB->get_record('chat_users', array('sid' => $socketchat->chatsid));
            if ($chatuser === false) {
                Messages::error('userchatnotfound', null, $this->from);
            }

            self::$chats[$this->from->resourceId] = $chatuser;
        } else {
            $chatuser = self::$chats[$this->from->resourceId];
        }

        return $chatuser;
    }

    private function getChatUserByGroupid($groupid) {
        global $DB;

        $params = array('groupid' => $groupid,  'cmid' => $this->session->cmid);
        if (!$socketsess = $DB->get_records('local_tepuy_socket_sessions', $params)) {
            Messages::error('chatnotavailable', null, $this->from);
        }

        $sss = array_shift($socketsess);

        if (!$socketchat = $DB->get_record('local_tepuy_socket_chat', array('sid' => $sss->id))) {
            Messages::error('chatnotavailable', null, $this->from);
        }

        $chatuser = $DB->get_record('chat_users', array('sid' => $socketchat->chatsid));
        if ($chatuser === false) {
            Messages::error('userchatnotfound', null, $this->from);
        }

        return $chatuser;
    }

    public static function customUnset($conn) {
        unset(self::$chats[$conn->resourceId]);
    }

    private function getResponse($data, $action = '') {
        $msg = new \stdClass();

        if (empty($action)) {
            $msg->action = $this->action;
        } else {
            $msg->action = $action;
        }

        $msg->data = $data;

        return $msg;
    }

    private function notifyActionToAll($msg = null, $tosender = false, $params = null) {

        try {
            $data = new \stdClass();
            $data->action = 'chatmsg';
            $data->issystem = true;
            $data->tosender = $tosender;

            if ($msg) {
                $data->data = $msg;
            } else {
                $data->data = 'action' . $this->action;
            }

            $data->originalaction = $this->action;

            $action = new Action($this->from, $data);
            $action->run($params);

            Logging::trace(Logging::LVL_DETAIL, 'Chat system message: ' . $data->data);

            return true;

        } catch(\Exception $e) {
            return false;
        }
    }
}
