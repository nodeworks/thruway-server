<?php

/**
 * @file
 * Thruway Server for WebSockets and WAMP.
 */

require __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/load.environment.php')) {
    require __DIR__ . '/load.environment.php';
}

use React\EventLoop\Factory;
use React\ZMQ\Context;
use Thruway\Logging\Logger;
use Thruway\Peer\Client;
use Thruway\Peer\Router;
use Thruway\Transport\RatchetTransportProvider;

$ratchet_port = getenv('RATCHET_PORT') ?: 8190;
$socket_port = getenv('SOCKET_PORT') ?: 5555;
$loop   = Factory::create();
$pusher = new Client('realtime', $loop);

$pusher->on('open', function ($session) use ($loop, $socket_port) {
    $context = new Context($loop);
    $pull = $context->getSocket(\ZMQ::SOCKET_PULL);
    $pull->bind("tcp://127.0.0.1:{$socket_port}");

    // Handle incoming messages.
    $pull->on('message', function ($data) use ($session) {
        Logger::info(NULL, 'Incoming message...');

        $dataArray = json_decode($data, true);
        if (isset($dataArray['channel'])) {
            $session->publish($dataArray['channel'], [$dataArray]);
        }
    });
});

$router = new Router($loop);
$router->addInternalClient($pusher);
$router->addTransportProvider(new RatchetTransportProvider('0.0.0.0', $ratchet_port));
$router->start();
