<?php

namespace PHPay\AbacatePay\Enums;

use PHPay\Support\PaymentStatus;

enum BillingStatusEnum: string
{
    case PENDING   = 'PENDING';
    case EXPIRED   = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
    case PAID      = 'PAID';
    case REFUNDED  = 'REFUNDED';

    /**
     * the same state in the library's vocabulary.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::PENDING   => PaymentStatus::PENDING,
            self::EXPIRED   => PaymentStatus::EXPIRED,
            self::CANCELLED => PaymentStatus::CANCELED,
            self::PAID      => PaymentStatus::PAID,
            self::REFUNDED  => PaymentStatus::REFUNDED,
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
