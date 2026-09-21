<?php

namespace PHPay\Exceptions;

use Throwable;

/**
 * marker interface for every exception thrown by PHPay.
 *
 * allows consumers to catch all library failures with a single catch block:
 * `catch (\PHPay\Exceptions\PHPayException $e)`.
 */
interface PHPayException extends Throwable
{
}
