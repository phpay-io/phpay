<?php

namespace PHPay\Http;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Throwable;

/**
 * decides whether repeating a request can cost the payer money.
 *
 * In a payments library a blind retry is the worst kind of bug: repeating a
 * POST the gateway already processed charges the customer twice, and the
 * failure that tempts you to retry — a timeout — is exactly the one where you
 * cannot tell whether it was processed. So the default here is to repeat only
 * what is provably free to repeat: a read, something that never left this
 * machine, or a write carrying an idempotency key the gateway will honour.
 *
 * PUT, PATCH and DELETE are not repeated, although HTTP calls PUT and DELETE
 * idempotent. The Asaas documentation says not to treat its delete endpoint as
 * idempotent without checking the response, and that warning is worth more
 * than the spec.
 *
 * The two Guzzle majors answer "did it leave the machine?" differently, and
 * this class asks each one in its own language. Guzzle 7 throws a single
 * ConnectException for everything from a failed DNS lookup to a read timeout,
 * so only the cURL errno in its handler context separates them. Guzzle 8
 * removed that accessor and split the cases into classes instead, with its own
 * upgrade guide saying to classify by class and not by error code — there,
 * ConnectException means the connection never happened, and that is the whole
 * question. Neither path names a class that exists in only one major.
 *
 * @see https://docs.asaas.com/reference/excluir-cobranca
 * @see https://github.com/guzzle/guzzle/blob/8.3.0/UPGRADING.md
 */
final class RetryPolicy
{
    /**
     * attempts in total, the first one included.
     */
    public const ATTEMPTS = 3;

    /**
     * delay before the first retry, in milliseconds. doubles after that.
     */
    public const BASE_DELAY_MS = 200;

    /**
     * ceiling for any single delay, in milliseconds.
     */
    public const MAX_DELAY_MS = 2000;

    /**
     * methods that change nothing, so repeating one costs nothing.
     */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * cURL error numbers where the request never left this machine: proxy and
     * host resolution, the connection itself, and the TLS handshake.
     */
    private const NEVER_SENT = [5, 6, 7, 35];

    /**
     * CURLE_OPERATION_TIMEDOUT. Guzzle reports it as a ConnectException even
     * when the body was already on the wire, so it needs a second look.
     */
    private const TIMED_OUT = 28;

    /**
     * construct.
     *
     * @param int $attempts attempts in total, 1 disables retrying
     * @param int $baseDelayMs delay before the first retry
     * @param int $maxDelayMs ceiling for a single delay
     */
    public function __construct(
        private readonly int $attempts = self::ATTEMPTS,
        private readonly int $baseDelayMs = self::BASE_DELAY_MS,
        private readonly int $maxDelayMs = self::MAX_DELAY_MS
    ) {
    }

    /**
     * a policy that never repeats anything.
     *
     * @return self
     */
    public static function disabled(): self
    {
        return new self(1);
    }

    /**
     * whether to try again.
     *
     * @param int $retries retries already made
     * @param RequestInterface $request
     * @param ResponseInterface|null $response answer, when there was one
     * @param Throwable|null $exception transport failure, when there was one
     * @return bool
     */
    public function shouldRetry(
        int $retries,
        RequestInterface $request,
        ?ResponseInterface $response = null,
        ?Throwable $exception = null
    ): bool {
        if ($retries >= $this->attempts - 1) {
            return false;
        }

        /*
         * PSR-18: falha de transporte sem resposta. No Guzzle 7 isso é só a
         * ConnectException; no 8, também a NetworkException e as de timeout.
         * O contrato é o mesmo nas duas majors, a classe não.
         */
        if ($exception instanceof NetworkExceptionInterface) {
            return $this->neverSent($exception) || $this->repeatable($request);
        }

        /* another transport failure: no idea how far it got, so no guessing */
        if ($exception !== null || $response === null) {
            return false;
        }

        $status = $response->getStatusCode();

        /*
         * the gateway asked for longer than we are willing to hold the worker.
         * retrying before the window it named would only be refused again, so
         * the honest answer is to give up and let the caller decide.
         */
        $after = $this->retryAfter($response);

        if ($after !== null && $after > $this->maxDelayMs) {
            return false;
        }

        /* a rate limit refuses before processing, whatever the method */
        if ($status === 429) {
            return true;
        }

        if ($status >= 500 && $status < 600) {
            return $this->repeatable($request);
        }

        /* any other 4xx: the gateway understood and said no */
        return false;
    }

    /**
     * how long to wait before the next attempt, in milliseconds.
     *
     * @param int $retries retries already made, so 1 on the first wait
     * @param ResponseInterface|null $response
     * @return int
     */
    public function delayFor(int $retries, ?ResponseInterface $response = null): int
    {
        $after = $this->retryAfter($response);

        if ($after !== null) {
            return min($after, $this->maxDelayMs);
        }

        $backoff = $this->baseDelayMs * 2 ** min(max(0, $retries - 1), 10);

        /* jitter, so a gateway coming back up is not hit by every worker at once */
        $jittered = $backoff + random_int(0, intdiv($backoff, 2));

        /* the ceiling is a ceiling: it applies after the jitter, not before */
        return (int) min($jittered, $this->maxDelayMs);
    }

    /**
     * the decider, in the shape Guzzle's retry middleware calls.
     *
     * the fourth argument is `mixed` because that is how Guzzle 8 declares it,
     * and a callable handed to the middleware has to accept at least as much as
     * the middleware may pass it — a narrower `?Throwable` is rejected on that
     * major. Guzzle only ever passes a Throwable or null, so the value is
     * narrowed back before it reaches shouldRetry(), which keeps the decision
     * itself typed.
     *
     * @return callable(int, RequestInterface, ?ResponseInterface, mixed): bool
     */
    public function decider(): callable
    {
        return fn (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            mixed $exception             = null
        ): bool => $this->shouldRetry(
            $retries,
            $request,
            $response,
            $exception instanceof Throwable ? $exception : null
        );
    }

    /**
     * the delay, in the shape Guzzle's retry middleware calls.
     *
     * @return callable(int, ?ResponseInterface): int
     */
    public function delay(): callable
    {
        return fn (int $retries, ?ResponseInterface $response = null): int => $this->delayFor($retries, $response);
    }

    /**
     * whether repeating this request is free: a read, or a write the gateway
     * will recognise as one it already answered.
     *
     * @param RequestInterface $request
     * @return bool
     */
    private function repeatable(RequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), self::SAFE_METHODS, true)
            || Idempotency::present($request);
    }

    /**
     * whether the request provably never reached the gateway.
     *
     * @param Throwable $exception a transport failure, in either Guzzle major
     * @return bool
     */
    private function neverSent(Throwable $exception): bool
    {
        /*
         * Guzzle 8 não tem getHandlerContext(): ele trocou o errno por
         * classes, e a própria migração manda classificar pela classe. Lá a
         * ConnectException significa o que o nome diz — a conexão não se
         * estabeleceu, nada saiu — e a ConnectTimeoutException herda dela.
         * Todo o resto (NetworkException, NetworkTimeoutException e a família
         * Response*) aconteceu com a requisição já na linha.
         */
        if (!method_exists($exception, 'getHandlerContext')) {
            return $exception instanceof ConnectException;
        }

        $context = $exception->getHandlerContext();
        $errno   = $context['errno'] ?? null;

        if (!is_int($errno)) {
            return false;
        }

        if (in_array($errno, self::NEVER_SENT, true)) {
            return true;
        }

        if ($errno !== self::TIMED_OUT) {
            return false;
        }

        /*
         * a timeout that happened before the connection was established is a
         * connect timeout: nothing was sent. with a connection open, the body
         * may well have been processed.
         */
        $connectTime = $context['connect_time'] ?? null;

        return is_numeric($connectTime) && (float) $connectTime === 0.0;
    }

    /**
     * Retry-After, in milliseconds, when the gateway sent one.
     *
     * @param ResponseInterface|null $response
     * @return int|null
     */
    private function retryAfter(?ResponseInterface $response): ?int
    {
        if ($response === null) {
            return null;
        }

        $value = trim($response->getHeaderLine('Retry-After'));

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value * 1000;
        }

        $date = strtotime($value);

        if ($date === false) {
            return null;
        }

        return max(0, ($date - time()) * 1000);
    }
}
