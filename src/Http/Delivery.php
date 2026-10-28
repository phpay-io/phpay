<?php

namespace PHPay\Http;

use GuzzleHttp\Exception\ConnectException;
use Throwable;

/**
 * whether a failed request provably never left this machine.
 *
 * it is the one question two different policies need answered, and they must
 * never answer it twice: `RetryPolicy` asks it to decide whether repeating
 * the same request can double charge, and `FallbackGateway` asks it to
 * decide whether handing the same charge to another gateway can. A second
 * copy of this logic would drift from the first, and the drift would show up
 * as a customer paying twice.
 *
 * The two Guzzle majors answer the question differently, and this class asks
 * each one in its own language. Guzzle 7 throws a single ConnectException for
 * everything from a failed DNS lookup to a read timeout, so only the cURL
 * errno in its handler context separates them. Guzzle 8 removed that accessor
 * and split the cases into classes instead, with its own upgrade guide saying
 * to classify by class and not by error code — there, ConnectException means
 * the connection never happened, and that is the whole question. Neither path
 * names a class that exists in only one major.
 *
 * @see RetryPolicy
 * @see https://github.com/guzzle/guzzle/blob/8.3.0/UPGRADING.md
 */
final class Delivery
{
    /**
     * cURL errors that prove nothing left: DNS, host, connection, TLS.
     *
     * @var array<int, int>
     */
    private const NEVER_SENT = [5, 6, 7, 35];

    /**
     * cURL timeout, which may or may not have left — see below.
     */
    private const TIMED_OUT = 28;

    /**
     * whether the request provably never reached the gateway.
     *
     * @param Throwable $exception a transport failure, in either Guzzle major
     * @return bool
     */
    public static function neverSent(Throwable $exception): bool
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
}
