<?php

namespace PHPay\Woovi\Enums;

use PHPay\Support\PaymentStatus;

enum ChargeStatusEnum: string
{
    case ACTIVE             = 'ACTIVE';
    case COMPLETED          = 'COMPLETED';
    case EXPIRED            = 'EXPIRED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case REFUNDED           = 'REFUNDED';

    /**
     * the same state in the library's vocabulary.
     *
     * the Woovi set lines up almost one to one, which is why it is the
     * shortest mapping of the ten: it is also the only gateway that reports a
     * partial refund as a status of its own.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::ACTIVE             => PaymentStatus::PENDING,
            self::COMPLETED          => PaymentStatus::PAID,
            self::EXPIRED            => PaymentStatus::EXPIRED,
            self::PARTIALLY_REFUNDED => PaymentStatus::PARTIALLY_REFUNDED,
            self::REFUNDED           => PaymentStatus::REFUNDED,
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
