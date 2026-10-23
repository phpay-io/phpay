<?php

namespace PHPay\PagarMe\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a Pagar.me charge, as returned in `status` by the Core API v5.
 *
 * the order (`pedido`) carries a shorter set — only pending, paid, canceled
 * and failed — so these extra states are reachable on the charge alone.
 */
enum ChargeStatusEnum: string
{
    case PENDING     = 'pending';
    case PROCESSING  = 'processing';
    case PAID        = 'paid';
    case OVERPAID    = 'overpaid';
    case UNDERPAID   = 'underpaid';
    case CANCELED    = 'canceled';
    case FAILED      = 'failed';
    case CHARGEDBACK = 'chargedback';

    /**
     * the same state in the library's vocabulary.
     *
     * two mappings are judgement calls worth knowing about:
     *
     * `underpaid` becomes PARTIALLY_PAID, not PAID. Money arrived, but less
     * than was due, and reading it as paid is how an order ships for less than
     * it was sold.
     *
     * `processing` becomes PENDING, because that is what it is — not final.
     * Pagar.me's own guidance is to treat it as a failure to avoid a duplicate
     * purchase, but that is advice about what to do, not a statement about the
     * charge, and the library reports the state rather than the advice.
     *
     * **`canceled` cannot be told apart from a refund here**, and that is a
     * limit of the gateway, not of the mapping: Pagar.me cancels and refunds
     * through one `DELETE` and reports both as `canceled`, so a charge that was
     * paid and given back reads as CANCELED rather than REFUNDED. To tell the
     * two apart, look at whether the charge had been paid — the status alone
     * does not carry it.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::PENDING     => PaymentStatus::PENDING,
            self::PROCESSING  => PaymentStatus::PENDING,
            self::PAID        => PaymentStatus::PAID,
            self::OVERPAID    => PaymentStatus::PAID,
            self::UNDERPAID   => PaymentStatus::PARTIALLY_PAID,
            self::CANCELED    => PaymentStatus::CANCELED,
            self::FAILED      => PaymentStatus::FAILED,
            self::CHARGEDBACK => PaymentStatus::CHARGEBACK,
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
