<?php

require 'vendor/autoload.php';

use PhpBeatMaker\AudioWsServer;
use PhpBeatMaker\OscGateway;
use PhpBeatMaker\OscListener;
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

$wsApp = new AudioWsServer($osc, $presets);

// Receive OSC replies from SuperCollider (e.g. the audio device list).
$listener = new OscListener($loop);
$listener->listen('127.0.0.1:57121', function (string $address, array $args) use ($wsApp) {
    if ($address === '/devices') {
        $wsApp->broadcastDevices($args);
    }
});

$socket = new SocketServer('0.0.0.0:8080', [], $loop);

$server = new IoServer(
    new HttpServer(
        new WsServer($wsApp)
    ),
    $socket,
    $loop
);

$server->run();
