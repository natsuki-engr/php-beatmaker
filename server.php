<?php

require 'vendor/autoload.php';

use PhpBeatMaker\AudioWsServer;
use PhpBeatMaker\OscGateway;
use PhpOSC\OSCClient;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

$loop = Loop::get();

$client = new OSCClient($loop);
$client->set_destination('127.0.0.1', 57120);

$osc = new OscGateway($client);

$presets = require __DIR__ . '/presets.php';

$socket = new SocketServer('0.0.0.0:8080', [], $loop);

$server = new IoServer(
    new HttpServer(
        new WsServer(
            new AudioWsServer($osc, $presets)
        )
    ),
    $socket,
    $loop
);

$server->run();
