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

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Tepuy\SocketController;

// Moodle controller code.
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../../config.php');
// End Moodle controller code.

define("APP_SOURCE_PATH", __DIR__."/src");

require __DIR__ . '/vendor/autoload.php';
require 'autoload.php';

// Single server: ws protocol.
$port = 8080;
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new SocketController()
        )
    ),
    $port
);

echo "Server running on port $port" . PHP_EOL;
$server->run();


//Complex server: wss protocol.
/*
$app = new HttpServer(
    new WsServer(
        new SocketController()
    )
);

$loop = \React\EventLoop\Factory::create();

$secure_websockets = new \React\Socket\Server('0.0.0.0:8080', $loop);
$secure_websockets = new \React\Socket\SecureServer($secure_websockets, $loop, [
    'local_cert' => '/etc/ssl/certs/ssl-cert-snakeoil.pem',
    'local_pk' => '/etc/ssl/private/ssl-cert-snakeoil.key',
    'allow_self_signed' => TRUE,
    'verify_peer' => false
]);

$secure_websockets_server = new \Ratchet\Server\IoServer($app, $secure_websockets, $loop);

echo "Server running" . PHP_EOL;
$secure_websockets_server->run();
*/
