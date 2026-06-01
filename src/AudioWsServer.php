<?php

namespace PhpBeatMaker;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\Frame;

class AudioWsServer implements MessageComponentInterface
{
    /** @var array<int, array{pad: string, size: int}> */
    private array $pending = [];

    /**
     * @param array<string, string> $presets pad → wav path
     */
    public function __construct(
        private OscGateway $osc,
        private array $presets = [],
    ) {}

    public function onOpen(ConnectionInterface $conn)
    {
        echo "WS connected: {$conn->resourceId}\n";

        foreach ($this->presets as $pad => $path) {
            if (!is_file($path)) {
                echo "preset missing: pad={$pad} path={$path}\n";
                continue;
            }
            $this->osc->load((string) $pad, $path);
            $bytes = file_get_contents($path);
            $conn->send(json_encode([
                'type' => 'preset',
                'pad'  => (string) $pad,
                'size' => strlen($bytes),
            ]));
            $conn->send(new Frame($bytes, true, Frame::OP_BINARY));
        }
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        if (isset($this->pending[$from->resourceId])) {
            $this->saveUpload($from, $msg);
            return;
        }

        echo "WS msg: $msg\n";

        $data = json_decode($msg, true);

        if (!$data || !isset($data['type'])) {
            echo "invalid message\n";
            return;
        }

        match ($data['type']) {
            'play' => $this->play($data),
            'load' => $this->load($data),
            'upload' => $this->upload($from, $data),
            'clear' => $this->clear($data),

            default => (function () {
                echo "unknown type\n";
            })()
        };
    }

    private function clear(array $data): void
    {
        $pad = $data['pad'] ?? null;

        if (!$pad) {
            echo "missing pad\n";
            return;
        }

        $upload = __DIR__ . "/../samples/uploads/pad-{$pad}.wav";
        if (is_file($upload)) {
            unlink($upload);
            echo "removed: {$upload}\n";
        }
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

    private function upload(ConnectionInterface $from, array $data): void
    {
        $pad  = $data['pad']  ?? null;
        $size = $data['size'] ?? null;

        if (!$pad || !$size) {
            echo "missing upload params\n";
            return;
        }

        $this->pending[$from->resourceId] = ['pad' => $pad, 'size' => $size];
        echo "expecting upload: pad={$pad}, size={$size}\n";
    }

    private function saveUpload(ConnectionInterface $from, string $bytes): void
    {
        $info = $this->pending[$from->resourceId];
        unset($this->pending[$from->resourceId]);

        $pad      = $info['pad'];
        $expected = $info['size'];
        $actual   = strlen($bytes);

        $dir = __DIR__ . '/../samples/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = "{$dir}/pad-{$pad}.wav";

        file_put_contents($path, $bytes);
        echo "saved: {$path} ({$actual} bytes, expected {$expected})\n";

        $this->osc->load($pad, $path);
    }

    public function onClose(ConnectionInterface $conn)
    {
        unset($this->pending[$conn->resourceId]);
        echo "WS closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "WS error: {$e->getMessage()}\n";
        $conn->close();
    }
}
