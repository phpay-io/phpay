<?php

namespace PHPay\Support;

/**
 * the state of a payment, in one vocabulary.
 *
 * the same problem `Money` and `Customer` solve on the way in, solved on the
 * way out: ten gateways answer with ten vocabularies — `RECEIVED`,
 * `COMPLETED`, `approved`, `PAID`, `paid`, `CONCLUIDA`, `'00'` — and whoever
 * changes gateway rewrites every `if`. Each gateway's own enum stays where it
 * is and gains a `status()` that answers in these terms, so the specific state
 * is never out of reach.
 *
 * support, not capability: there is no gateway that fails to report status, so
 * there is nothing to declare. It belongs next to `Money`, not in the
 * `Capability` enum.
 */
enum PaymentStatus: string
{
    /** created, waiting for the payer */
    case PENDING = 'pending';

    /** pre-authorized on the card, waiting for capture — money has not moved */
    case AUTHORIZED = 'authorized';

    /** held for risk or antifraud review */
    case IN_ANALYSIS = 'in-analysis';

    /** money arrived, but less than was due */
    case PARTIALLY_PAID = 'partially-paid';

    /** received */
    case PAID = 'paid';

    /** part of it was given back */
    case PARTIALLY_REFUNDED = 'partially-refunded';

    /** all of it was given back */
    case REFUNDED = 'refunded';

    /** the payer disputed it and the outcome is open */
    case IN_DISPUTE = 'in-dispute';

    /** the dispute was lost: the money was taken back by the issuer */
    case CHARGEBACK = 'chargeback';

    /** called off before anyone paid */
    case CANCELED = 'canceled';

    /** the window closed with no payment */
    case EXPIRED = 'expired';

    /** refused */
    case FAILED = 'failed';

    /**
     * a status this version of the library does not know.
     *
     * not laziness: gateways add states without warning, and the alternative
     * is throwing while reading a **successful** response, which contradicts
     * the rule that a returned array is always a success. Reading an unknown
     * state as unknown degrades; throwing loses the charge. The raw value is
     * still in the response for whoever needs to handle the new case.
     */
    case UNKNOWN = 'unknown';

    /**
     * label used in messages, in Portuguese like the rest of the library.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING            => 'aguardando pagamento',
            self::AUTHORIZED         => 'autorizada, aguardando captura',
            self::IN_ANALYSIS        => 'em análise',
            self::PARTIALLY_PAID     => 'paga parcialmente',
            self::PAID               => 'paga',
            self::PARTIALLY_REFUNDED => 'estornada parcialmente',
            self::REFUNDED           => 'estornada',
            self::IN_DISPUTE         => 'em disputa',
            self::CHARGEBACK         => 'chargeback',
            self::CANCELED           => 'cancelada',
            self::EXPIRED            => 'vencida',
            self::FAILED             => 'recusada',
            self::UNKNOWN            => 'desconhecida',
        };
    }

    /**
     * whether the full amount was received and is still with you.
     *
     * false for a partial payment, for an authorization that was never
     * captured, and for money that went back — by refund or by chargeback.
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * whether any part of the money went back to the payer.
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this === self::REFUNDED || $this === self::PARTIALLY_REFUNDED;
    }

    /**
     * whether nothing further is expected to happen on its own.
     *
     * **`PAID` is not final**, on purpose: a received payment can still be
     * refunded or charged back, and treating it as settled is how a refund
     * goes unnoticed. `UNKNOWN` is not final either — the library cannot
     * promise anything about a state it does not know.
     *
     * @return bool
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::REFUNDED, self::CHARGEBACK, self::CANCELED,
            self::EXPIRED, self::FAILED => true,
            default                     => false,
        };
    }
}
