<?php

namespace PHPay\Exceptions;

use InvalidArgumentException;

/**
 * thrown when a payload fails local validation, before any HTTP call.
 */
class ValidationException extends InvalidArgumentException implements PHPayException
{
    /**
     * build a validation exception for a given gateway.
     *
     * @param string $gateway
     * @param string $message
     * @return self
     */
    public static function make(string $gateway, string $message): self
    {
        return new self("{$gateway}: {$message}", 400);
    }
}
