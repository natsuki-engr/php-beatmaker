<?php

require 'vendor/autoload.php';

use PhpBeatMaker\AudioWsServer;
use PhpBeatMaker\OscGateway;
use PhpOSC\OSCClient;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;

$loop = Loop::get();

$client = new OSCClient($loop);
$client->set_destination('127.0.0.1', 57120);

$osc = new OscGateway($client);

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new AudioWsServer($osc)
        )
    ),
    8080
);

$server->run();
