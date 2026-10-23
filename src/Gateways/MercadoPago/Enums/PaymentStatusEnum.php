<?php

namespace PHPay\MercadoPago\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a Mercado Pago payment, as returned in `status` by /v1/payments.
 *
 * the Orders API uses a different set — `in_review` instead of `in_mediation`,
 * and no `authorized` — so this enum is about payments only, which is what the
 * library's Charge resource talks to.
 */
enum PaymentStatusEnum: string
{
    case PENDING      = 'pending';
    case APPROVED     = 'approved';
    case AUTHORIZED   = 'authorized';
    case IN_PROCESS   = 'in_process';
    case IN_MEDIATION = 'in_mediation';
    case REJECTED     = 'rejected';
    case CANCELLED    = 'cancelled';
    case REFUNDED     = 'refunded';
    case CHARGED_BACK = 'charged_back';

    /**
     * the same state in the library's vocabulary.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::PENDING      => PaymentStatus::PENDING,
            self::APPROVED     => PaymentStatus::PAID,
            self::AUTHORIZED   => PaymentStatus::AUTHORIZED,
            self::IN_PROCESS   => PaymentStatus::IN_ANALYSIS,
            self::IN_MEDIATION => PaymentStatus::IN_DISPUTE,
            self::REJECTED     => PaymentStatus::FAILED,
            self::CANCELLED    => PaymentStatus::CANCELED,
            self::REFUNDED     => PaymentStatus::REFUNDED,
            self::CHARGED_BACK => PaymentStatus::CHARGEBACK,
        };
    }

    /**
     * read a raw status, tolerating one this version does not know.
     *
     * @param string|null $status the `status` of the response
     * @return PaymentStatus
     */
    public static function of(?string $status): PaymentStatus
    {
        return self::tryFrom($status ?? '')?->status() ?? PaymentStatus::UNKNOWN;
    }
}
