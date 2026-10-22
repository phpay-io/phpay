<?php

namespace PHPay\Http;

use Psr\Http\Message\RequestInterface;

/**
 * idempotency keys — the only thing that makes repeating a POST safe.
 *
 * a gateway that stores the key answers the second request with the result of
 * the first instead of charging again. Without one, a repeated POST is a
 * second charge, which is why the retry policy looks for these headers before
 * it repeats anything that is not a read.
 */
final class Idempotency
{
    /**
     * headers the supported gateways read a key from, lowercased.
     *
     * Mercado Pago and PagBank prefix it, Pagar.me does not. PSR-7 compares
     * header names case-insensitively, so one spelling of each is enough.
     *
     * @var array<int, string>
     */
    public const HEADERS = ['idempotency-key', 'x-idempotency-key'];

    /**
     * a fresh key, 32 hex characters from the CSPRNG.
     *
     * @return string
     */
    public static function key(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * whether this request carries a key a gateway would honour.
     *
     * @param RequestInterface $request
     * @return bool
     */
    public static function present(RequestInterface $request): bool
    {
        foreach (self::HEADERS as $header) {
            if (trim($request->getHeaderLine($header)) !== '') {
                return true;
            }
        }

        return false;
    }
}
