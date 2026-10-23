<?php

namespace PHPay\PagBank\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a PagBank charge, as returned in `charges[].status`.
 *
 * `AUTHORIZED` is the one worth care: it is a pre-authorization waiting for
 * capture, with a deadline in `capture_before` — money has not moved, and
 * treating it as paid ships goods against an authorization that may expire.
 */
enum ChargeStatusEnum: string
{
    case WAITING     = 'WAITING';
    case IN_ANALYSIS = 'IN_ANALYSIS';
    case AUTHORIZED  = 'AUTHORIZED';
    case PAID        = 'PAID';
    case DECLINED    = 'DECLINED';
    case CANCELED    = 'CANCELED';

    /**
     * the same state in the library's vocabulary.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::WAITING     => PaymentStatus::PENDING,
            self::IN_ANALYSIS => PaymentStatus::IN_ANALYSIS,
            self::AUTHORIZED  => PaymentStatus::AUTHORIZED,
            self::PAID        => PaymentStatus::PAID,
            self::DECLINED    => PaymentStatus::FAILED,
            self::CANCELED    => PaymentStatus::CANCELED,
        };
    }

    /**
     * read a raw status, tolerating one this version does not know.
     *
     * @param string|null $status
     * @return PaymentStatus
     */
    public static function of(?string $status): PaymentStatus
    {
        return self::tryFrom($status ?? '')?->status() ?? PaymentStatus::UNKNOWN;
    }
}
