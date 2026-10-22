<?php

namespace PHPay\Http;

use GuzzleHttp\Client;

/**
 * the one place a gateway builds its http client.
 *
 * Guzzle waits forever by default: a gateway that hangs holds the worker
 * until the process dies, and in a queue worker or a CLI script nothing else
 * ever stops it. Every client of the library goes through here, so none of
 * them can be born without a deadline.
 */
final class ClientFactory
{
    /**
     * seconds to wait for the whole request. generous on purpose: a card
     * authorization with antifraud can take a while, and giving up on a
     * charge the gateway is still processing invites a duplicate on retry.
     */
    public const TIMEOUT = 60;

    /**
     * seconds to wait for the connection itself — a gateway that does not
     * even answer the handshake is down, not busy.
     */
    public const CONNECT_TIMEOUT = 10;

    /**
     * build a client with the library defaults. keys in $config win.
     *
     * @param array<string, mixed> $config Guzzle request options
     * @return Client
     */
    public static function make(array $config = []): Client
    {
        return new Client($config + self::defaults());
    }

    /**
     * options every client gets unless the gateway says otherwise.
     *
     * @return array{timeout: int, connect_timeout: int}
     */
    public static function defaults(): array
    {
        return [
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
        ];
    }
}
