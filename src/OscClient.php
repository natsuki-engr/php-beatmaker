<?php

namespace PhpBeatMaker;

final class OscClient
{
    public function __construct(
        private string $host,
        private int $port,
    ) {}

    public function send(string $address, int|float|string $value): void
    {
        $packet =
            $this->oscString($address)
            . $this->oscString($this->typeTag($value))
            . $this->oscArgument($value);

        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_sendto(
            $socket,
            $packet,
            strlen($packet),
            0,
            $this->host,
            $this->port,
        );

        socket_close($socket);
    }

    private function oscString(string $value): string
    {
        $value .= "\0";

        while (strlen($value) % 4 !== 0) {
            $value .= "\0";
        }

        return $value;
    }

    private function typeTag(int|float|string $value): string
    {
        return match (true) {
            is_int($value) => ',i',
            is_float($value) => ',f',
            default => ',s',
        };
    }

    private function oscArgument(int|float|string $value): string
    {
        return match (true) {
            is_int($value) => pack('N', $value),
            is_float($value) => pack('G', $value),
            default => $this->oscString($value),
        };
    }
}
