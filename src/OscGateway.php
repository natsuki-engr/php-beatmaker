<?php

namespace PhpBeatMaker;

use PhpOSC\OSCClient;
use PhpOSC\OSCMessage;

class OscGateway
{
    public function __construct(private OSCClient $client) {}

    public function play(string $pad, float $start = 0.0, float $end = 1.0): void
    {
        $this->client->sendAsync(
            new OSCMessage('/play', [$pad, $start, $end])
        );
    }

    public function load(string $pad, string $path): void
    {
        $this->client->sendAsync(
            new OSCMessage('/load', [$pad, $path])
        );
    }

    public function listDevices(): void
    {
        $this->client->sendAsync(
            new OSCMessage('/listDevices', [])
        );
    }

    public function setOutDevice(string $device): void
    {
        $this->client->sendAsync(
            new OSCMessage('/setOutDevice', [$device])
        );
    }
}
