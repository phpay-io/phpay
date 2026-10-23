<?php

namespace PHPay\Efi\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a charge in the Efí **Cobranças** API — boleto, carnê and card.
 *
 * the Pix API of the same gateway answers in another vocabulary entirely
 * (`ATIVA`, `CONCLUIDA`, …), which is `PixChargeStatusEnum`. Two APIs with the
 * same credentials, two sets of states: reading one with the other's enum
 * silently yields UNKNOWN.
 */
enum ChargeStatusEnum: string
{
    case NEW        = 'new';
    case WAITING    = 'waiting';
    case LINK       = 'link';
    case IDENTIFIED = 'identified';
    case APPROVED   = 'approved';
    case PAID       = 'paid';
    case SETTLED    = 'settled';
    case UNPAID     = 'unpaid';
    case REFUNDED   = 'refunded';
    case CONTESTED  = 'contested';
    case CANCELED   = 'canceled';
    case EXPIRED    = 'expired';

    /**
     * the same state in the library's vocabulary.
     *
     * `identified` and `approved` both mean the payment was detected or the
     * card was approved while the funds are **not yet credited**. They map to
     * PAID because the payer has paid, which is what the state is about; the
     * credit is a separate concern and the gateway does not report it here.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::NEW, self::WAITING, self::LINK => PaymentStatus::PENDING,
            self::IDENTIFIED, self::APPROVED     => PaymentStatus::PAID,
            self::PAID, self::SETTLED            => PaymentStatus::PAID,
            self::UNPAID                         => PaymentStatus::FAILED,
            self::REFUNDED                       => PaymentStatus::REFUNDED,
            self::CONTESTED                      => PaymentStatus::IN_DISPUTE,
            self::CANCELED                       => PaymentStatus::CANCELED,
            self::EXPIRED                        => PaymentStatus::EXPIRED,
        };
    }

    /**
     * whether the charge reached a state Efí calls resolved.
     *
     * Efí publishes its own list, and it is **not** the same as
     * `PaymentStatus::isFinal()`: `paid` and `contested` are resolved for Efí,
     * while the library keeps PAID open because a refund can still follow.
     *
     * @return bool
     */
    public function isResolved(): bool
    {
        return match ($this) {
            self::PAID, self::CONTESTED, self::REFUNDED,
            self::SETTLED, self::CANCELED => true,
            default                       => false,
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
