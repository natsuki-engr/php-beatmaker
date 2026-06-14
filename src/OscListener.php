<?php

namespace PhpBeatMaker;

use React\Datagram\Factory;
use React\Datagram\Socket;
use React\EventLoop\LoopInterface;

/**
 * Listens for OSC replies from SuperCollider over UDP and dispatches them.
 */
class OscListener
{
    public function __construct(private LoopInterface $loop) {}

    /**
     * @param string $bind e.g. "127.0.0.1:57121"
     * @param callable(string $address, array<int, mixed> $args): void $onMessage
     */
    public function listen(string $bind, callable $onMessage): void
    {
        $factory = new Factory($this->loop);

        $factory->createServer($bind)->then(function (Socket $server) use ($onMessage, $bind) {
            echo "OSC listener on {$bind}\n";

            $server->on('message', function (string $data) use ($onMessage) {
                $parsed = self::parse($data);
                if ($parsed !== null) {
                    $onMessage($parsed['address'], $parsed['args']);
                }
            });
        }, function (\Throwable $e) use ($bind) {
            echo "OSC listener failed to bind {$bind}: {$e->getMessage()}\n";
        });
    }

    /**
     * Parse a single OSC message packet.
     *
     * @return array{address: string, args: array<int, mixed>}|null
     */
    public static function parse(string $packet): ?array
    {
        $pos = 0;

        $address = self::readString($packet, $pos);
        if ($address === null) {
            return null;
        }

        $typetags = self::readString($packet, $pos);
        if ($typetags === null || $typetags === '' || $typetags[0] !== ',') {
            return ['address' => $address, 'args' => []];
        }

        $args = [];
        $len = strlen($typetags);
        for ($i = 1; $i < $len; $i++) {
            switch ($typetags[$i]) {
                case 's':
                    $args[] = self::readString($packet, $pos);
                    break;
                case 'i':
                    $unpacked = unpack('Nval', substr($packet, $pos, 4));
                    // interpret as signed 32-bit
                    $val = $unpacked['val'];
                    $args[] = $val >= 0x80000000 ? $val - 0x100000000 : $val;
                    $pos += 4;
                    break;
                case 'f':
                    $unpacked = unpack('Gval', substr($packet, $pos, 4));
                    $args[] = $unpacked['val'];
                    $pos += 4;
                    break;
                case 'T':
                    $args[] = true;
                    break;
                case 'F':
                    $args[] = false;
                    break;
                default:
                    // unsupported type tag; stop parsing further args
                    break 2;
            }
        }

        return ['address' => $address, 'args' => $args];
    }

    /**
     * Read a null-terminated OSC string padded to a 4-byte boundary,
     * advancing $pos past it.
     */
    private static function readString(string $packet, int &$pos): ?string
    {
        $end = strpos($packet, "\0", $pos);
        if ($end === false) {
            return null;
        }

        $str = substr($packet, $pos, $end - $pos);
        $strlen = $end - $pos;
        // bytes consumed = smallest multiple of 4 strictly greater than $strlen
        $pos += (intdiv($strlen, 4) + 1) * 4;

        return $str;
    }
}
