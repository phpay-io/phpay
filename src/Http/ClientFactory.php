<?php

namespace PHPay\Http;

use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

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
     * name of the recorder installed while faking.
     */
    public const RECORDER = 'phpay_recorder';

    /**
     * handler that answers instead of the network, while faking.
     */
    private static ?MockHandler $fake = null;

    /**
     * requests the application made while faking.
     *
     * @var array<int, array{request: RequestInterface, options: array<mixed>}>
     */
    private static array $recorded = [];

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
 *     ssl_key?: string|array{0: string, 1: string},
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

        $handler = $config['handler'] ?? self::$fake;

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
        /*
         * o gravador entra antes do retry, o que o deixa mais externo: o
         * histórico mostra as requisições que a aplicação fez, não cada
         * tentativa. Quem precisa ver tentativa empurra o próprio, depois da
         * fábrica.
         */
        if (self::$fake !== null) {
            $stack->remove(self::RECORDER);
            $stack->push(self::recorder(), self::RECORDER);
        }

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

    /**
     * answer from a queue instead of from the network.
     *
     * **for tests, and only for tests.** Every client the library builds goes
     * through this factory — all fourteen call sites, and a test over `src/`
     * refuses any `new Client(` outside it — so faking here reaches every
     * gateway at once, including the second clients that Efí, Cielo and Rede
     * each keep for another host. That is the reason this lives here and not
     * in the registry: a single injected client would cover half of those
     * three, and the half it missed would quietly go to the network.
     *
     * the retry middleware stays installed. Faking the network should not
     * switch off the policy that decides what may be repeated — a test that
     * wants to exercise retrying puts two answers in the queue.
     *
     * it is static state, with the same contract as Laravel's `Mail::fake()`:
     * opt-in, and `stopFaking()` restores. Call it in a test's setup, not
     * anywhere a request can reach.
     *
     * @param array<int, ResponseInterface|\Throwable>|MockHandler $responses
     * @return MockHandler the handler, for whoever wants to append to it
     */
    public static function fake(array|MockHandler $responses = []): MockHandler
    {
        self::$recorded = [];
        self::$fake     = $responses instanceof MockHandler ? $responses : new MockHandler($responses);

        return self::$fake;
    }

    /**
     * whether the factory is answering from a queue.
     *
     * @return bool
     */
    public static function faking(): bool
    {
        return self::$fake !== null;
    }

    /**
     * the requests the application made while faking.
     *
     * each entry carries the `request` and the `options` it went out with.
     *
     * **not Guzzle's history shape**, and not for lack of trying: that
     * middleware takes its container by reference as
     * `array|ArrayAccess<int, array>`, whose template is invariant, so no
     * precisely typed property satisfies it at level 9. Writing the recorder
     * here keeps the types and costs ten lines.
     *
     * the response is not recorded on purpose: while faking, the responses are
     * the ones the test queued, so repeating them says nothing. What the test
     * cannot know in advance is the **request**, and that is what this holds.
     *
     * @return array<int, array{request: RequestInterface, options: array<mixed>}>
     */
    public static function recorded(): array
    {
        return self::$recorded;
    }

    /**
     * back to the network, with the history cleared.
     *
     * clients already built keep the fake handler they were born with — this
     * only changes what the *next* `make()` produces. In a test that is what
     * you want; outside one, do not rely on it.
     *
     * @return void
     */
    public static function stopFaking(): void
    {
        self::$fake     = null;
        self::$recorded = [];
    }

    /**
     * middleware that records what goes out.
     *
     * @return callable(callable): callable
     */
    private static function recorder(): callable
    {
        return static function (callable $handler): callable {
            return static function (RequestInterface $request, array $options) use ($handler): mixed {
                self::$recorded[] = ['request' => $request, 'options' => $options];

                return $handler($request, $options);
            };
        };
    }
}
