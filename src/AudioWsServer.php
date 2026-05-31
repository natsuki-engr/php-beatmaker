<?php

namespace PhpBeatMaker;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class AudioWsServer implements MessageComponentInterface
{
    public function __construct(
        private OscGateway $osc
    ) {}

    public function onOpen(ConnectionInterface $conn)
    {
        echo "WS connected: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        echo "WS msg: $msg\n";

        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            echo "invalid message\n";
            return;
        }

        match ($data['type']) {

            'play' => $this->play($data),

            'load' => $this->load($data),

            default => (function () {
                echo "unknown type\n";
            })()
        };
    }

    private function play(array $data): void
    {
        $pad = $data['pad'] ?? null;

        if (!$pad) {
            echo "missing pad\n";
            return;
        }

        $this->osc->play($pad);
    }

    private function load(array $data): void
    {
        $pad = $data['pad'] ?? null;
        $path = $data['path'] ?? null;

        if (!$pad || !$path) {
            echo "missing load params\n";
            return;
        }

        $this->osc->load($pad, $path);
    }

    public function onClose(ConnectionInterface $conn)
    {
        echo "WS closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "WS error: {$e->getMessage()}\n";
        $conn->close();
    }
}
