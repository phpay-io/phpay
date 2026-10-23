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
     * the shape lists the options the library actually uses, not every option
     * Guzzle accepts. Guzzle 8 declares a closed shape for the constructor, and
     * `array<string, mixed>` does not satisfy it — describing only what the
     * gateways pass satisfies both majors and turns a mistyped option name into
     * an error here instead of a key Guzzle silently ignores.
     *
     * `handler` is typed as a plain callable on purpose: a HandlerStack is
     * callable and so qualifies, while naming the class would pin the
     * annotation to one major — Guzzle 8 made HandlerStack generic and 7 did
     * not, so either spelling fails on the other side.
     *
     * @param array{
     *     base_uri?: string,
     *     headers?: array<string, string>,
     *     auth?: array{0: string, 1: string},
     *     cert?: string|array{0: string, 1: string},
     *     timeout?: int,
     *     connect_timeout?: int,
     *     handler?: callable
     * } $config Guzzle request options
     * @param RetryPolicy|null $policy defaults to the conservative policy
     * @return Client
     */
    public static function make(array $config = [], ?RetryPolicy $policy = null): Client
    {
        $config += self::defaults();
        $policy ??= new RetryPolicy();

        $handler = $config['handler'] ?? null;

        // built here instead of in a helper: a method returning HandlerStack
        // would have to name the type, and Guzzle 8 made the class generic
        // while 7 did not. As a local, the type is whatever the installed
        // major infers, and neither side has an annotation to disagree with.
        $stack = $handler instanceof HandlerStack
            ? $handler
            : HandlerStack::create(is_callable($handler) ? $handler : null);

        // the middleware goes on last, which makes it the innermost: it sees
        // the raw response before `http_errors` turns a 5xx into an exception,
        // and the transport failure before anything wraps it.
        $stack->remove(self::RETRY);
        $stack->push(Middleware::retry($policy->decider(), $policy->delay()), self::RETRY);

        $config['handler'] = $stack;

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

}
