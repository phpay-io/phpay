<?php

namespace PHPay\Http;

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
 * Whether a failure left the machine is asked of {@see Delivery}, which is
 * also what FallbackGateway consults — the answer must never have two copies.
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
            return Delivery::neverSent($exception) || $this->repeatable($request);
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
