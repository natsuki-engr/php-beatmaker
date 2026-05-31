<?php

require 'vendor/autoload.php';

use PhpOSC\OSCClient;
use PhpOSC\OSCMessage;
use React\EventLoop\Loop;

$loop = Loop::get();

$client = new OSCClient($loop);
$client->set_destination('127.0.0.1', 57120);

$client->sendAsync(
    new OSCMessage('/play', ['pad1'])
);

$loop->run();