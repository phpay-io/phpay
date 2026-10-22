<?php

namespace PHPay\Webhooks;

/**
 * finds a header among the many shapes a framework hands one over.
 *
 * the same header reaches the application as `x-signature` from a PSR-7
 * request, `X-Signature` from a framework and `HTTP_X_SIGNATURE` from
 * $_SERVER, so a verifier that compares key names literally works in one
 * stack and silently fails in the next.
 */
final class Headers
{
    /**
     * value of the given header, whatever the key looks like.
     *
     * @param array<mixed> $headers
     * @param string $name header name, as the gateway documents it
     * @return string|null
     */
    public static function find(array $headers, string $name): ?string
    {
        $wanted = self::normalize($name);

        foreach ($headers as $key => $value) {
            if (!is_string($key) || self::normalize($key) !== $wanted) {
                continue;
            }

            /* PSR-7 keeps every header as a list of values */
            if (is_array($value)) {
                $value = reset($value);
            }

            if (is_string($value)) {
                return $value;
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * header name without the differences that are not the name.
     *
     * @param string $name
     * @return string
     */
    private static function normalize(string $name): string
    {
        $name = strtolower($name);

        if (str_starts_with($name, 'http_')) {
            $name = substr($name, 5);
        }

        return str_replace('_', '-', $name);
    }
}
