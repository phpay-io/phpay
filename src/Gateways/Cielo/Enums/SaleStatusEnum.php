<?php

namespace PHPay\Cielo\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status codes of a Cielo sale, as returned in Payment.Status.
 */
enum SaleStatusEnum: int
{
    case NOT_FINISHED      = 0;
    case AUTHORIZED        = 1;
    case PAYMENT_CONFIRMED = 2;
    case DENIED            = 3;
    case VOIDED            = 10;
    case REFUNDED          = 11;
    case PENDING           = 12;
    case ABORTED           = 13;
    case SCHEDULED         = 20;

    /**
     * the same state in the library's vocabulary.
     *
     * `AUTHORIZED` (1) is **not** paid: it is a pre-authorization waiting for
     * `capture()`, and Cielo keeps the two as separate codes precisely because
     * the money has not moved yet.
     *
     * `VOIDED` (10) and `REFUNDED` (11) are both reached through the same
     * `void` endpoint; which code comes back depends on whether the sale had
     * been captured.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::NOT_FINISHED      => PaymentStatus::PENDING,
            self::AUTHORIZED        => PaymentStatus::AUTHORIZED,
            self::PAYMENT_CONFIRMED => PaymentStatus::PAID,
            self::DENIED            => PaymentStatus::FAILED,
            self::VOIDED            => PaymentStatus::CANCELED,
            self::REFUNDED          => PaymentStatus::REFUNDED,
            self::PENDING           => PaymentStatus::PENDING,
            self::ABORTED           => PaymentStatus::FAILED,
            self::SCHEDULED         => PaymentStatus::PENDING,
        };
    }

    /**
     * read a raw status code, tolerating one this version does not know.
     *
     * @param int|null $status the numeric Payment.Status
     * @return PaymentStatus
     */
    public static function of(?int $status): PaymentStatus
    {
        return $status === null
            ? PaymentStatus::UNKNOWN
            : self::tryFrom($status)?->status() ?? PaymentStatus::UNKNOWN;
    }
}
