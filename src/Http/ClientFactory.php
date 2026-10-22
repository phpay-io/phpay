<?php

namespace PHPay\Http;

use GuzzleHttp\{Client, HandlerStack, Middleware};

/**
 * the one place a gateway builds its http client.
 *
 * Guzzle waits forever by default: a gateway that hangs holds the worker
 * until the process dies, and in a queue worker or a CLI script nothing else
 * ever stops it. Every client of the library goes through here, so none of
 * them can be born without a deadline — or without the retry policy, which
 * repeats only what is provably free to repeat.
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
     * name of the retry middleware on the handler stack.
     */
    public const RETRY = 'phpay_retry';

    /**
     * build a client with the library defaults. keys in $config win.
     *
     * @param array<string, mixed> $config Guzzle request options
     * @param RetryPolicy|null $policy defaults to the conservative policy
     * @return Client
     */
    public static function make(array $config = [], ?RetryPolicy $policy = null): Client
    {
        $config += self::defaults();
        $config['handler'] = self::stack($config['handler'] ?? null, $policy ?? new RetryPolicy());

        return new Client($config);
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

    /**
     * the handler stack with the retry middleware on it.
     *
     * the middleware is pushed last, which makes it the innermost: it sees the
     * raw response before `http_errors` turns a 5xx into an exception, and the
     * transport failure before anything wraps it.
     *
     * @param mixed $handler whatever the caller put in `handler`
     * @param RetryPolicy $policy
     * @return HandlerStack
     */
    private static function stack(mixed $handler, RetryPolicy $policy): HandlerStack
    {
        $stack = $handler instanceof HandlerStack
            ? $handler
            : HandlerStack::create(is_callable($handler) ? $handler : null);

        $stack->remove(self::RETRY);
        $stack->push(Middleware::retry($policy->decider(), $policy->delay()), self::RETRY);

        return $stack;
    }
}
