<?php
namespace Tepuy;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Tepuy\Messages;
use Tepuy\Logging;
use Tepuy\Action;
use Tepuy\ByCodeException;
use Tepuy\AppCodeException;
use Tepuy\SocketSessions;


class SocketController implements MessageComponentInterface {

    public function __construct() {
        global $DB;

        if (!$settings = $DB->get_records('local_tepuy_settings')) {
            Logging::trace(Logging::LVL_DETAIL, get_string('settingsnotfound', 'tepuycomponents_socket') . "\n");
            return;
        }

        // ToDo: Se debería recargar cuando se genera una adición en local_tepuy_settings, porque si el servicio sigue corriendo
        // no tomará los nuevos chats. Igual funcionan el chat, pero porque no se hece uso de todas las propiedades, solamente
        // bygroup osea que si el chat es por grupo no lo tomará.
        SocketSessions::setSettings($settings);
    }

    public function onOpen(ConnectionInterface $conn) {
        global $DB;

        $query = $conn->httpRequest->getUri()->getQuery();
        parse_str($query, $params);

        if (empty($params['skey'])) {
            Messages::error('skeyrequired', null, $conn, true);
        }

        $iscron = !empty($params['cron']);

        if (!$sess = $DB->get_record('local_tepuy_socket_sessions', array('skey' => $params['skey']))) {
            Messages::error('invalidkey', null, $conn, true);
        }

        $sess->lastping = time();
        $DB->update_record('local_tepuy_socket_sessions', $sess);

        SocketSessions::addConnection($conn, $sess, $iscron);

        if (!$iscron) {
            Logging::trace(Logging::LVL_ALL, "New connection! ({$conn->resourceId})");

            $data = new \stdClass();
            $data->action = 'playerconnected';

            $action = new Action($conn, $data);
            $action->run();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        global $DB, $CFG;

        Logging::trace(Logging::LVL_DETAIL, 'Message received from: ' . $from->resourceId);
        Logging::trace(Logging::LVL_DEBUG, 'Message: ', $msg);
        Logging::trace(Logging::LVL_DEBUG, 'Clients: ' . SocketSessions::countClients($from->resourceId));

        $json = @json_decode($msg);

        if (!$json) {
            Messages::error('invalidjson', null, $from);
        }

        if (empty($json->action)) {
            Messages::error('actionrequired', null, $from);
        }

        $action = new Action($from, $json);
        $action->run();
    }

    public function onClose(ConnectionInterface $conn) {

        $iscron = SocketSessions::isCron($conn->resourceId);

        // The connection is closed, remove it, as we can no longer send it messages
        SocketSessions::rmConnection($conn);

        Action::customUnset($conn);

        if ($iscron) {
        } else {

            Logging::trace(Logging::LVL_ALL, "Connection {$conn->resourceId} has disconnected");

            $data = new \stdClass();
            $data->action = 'playerdisconnected';

            if (SocketSessions::isActiveSessKey($conn->resourceId)) {
                $action = new Action($conn, $data);
                $action->run();
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {

        Logging::trace(Logging::LVL_ALL, "An error has occurred: {$e->getMessage()}");
        Logging::trace(Logging::LVL_DEBUG, "Trace: {$e->getTraceAsString()}");

        if (get_class($e) == 'dml_read_exception') {


            // Destroy the global DB Moodle variable in order to rebuild it.
            unset($GLOBALS['DB']);

            // Moodle function. Used to restart the database connection ($DB global variable).
            setup_DB();

            $msg = Messages::error('restardbconnection');
            $conn->send($msg);
            $conn->close();
        } else if (!($e instanceof AppException)) {
            $conn->close();
        }
    }
}
