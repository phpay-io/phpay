<?php

namespace PHPay\Asaas\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of an Asaas charge.
 *
 * the cases are the ones this enum already carried; **their raw values were
 * not re-verified** when the mapping below was added, and `REFUND_REQUESTED`
 * reads as `REFUNDED_REQUESTED`, which does not look like the rest of the set.
 * If that value is wrong, `of()` answers UNKNOWN for that state instead of
 * guessing — which is the safe failure, but it is worth checking against the
 * API before relying on it. Asaas also keeps a separate `chargeback.status`
 * field (`REQUESTED`, `IN_DISPUTE`, `DISPUTE_LOST`, `REVERSED`, `DONE`), so a
 * dispute is readable there in more detail than here.
 */
enum InvoiceStatusEnum: string
{
    case PENDING                      = 'PENDING';
    case RECEIVED                     = 'RECEIVED';
    case CONFIRMED                    = 'CONFIRMED';
    case OVERDUE                      = 'OVERDUE';
    case REFUNDED                     = 'REFUNDED';
    case REFUND_REQUESTED             = 'REFUNDED_REQUESTED';
    case REFUND_IN_PROGRESS           = 'REFUND_IN_PROGRESS';
    case CHARGEBACK_REQUESTED         = 'CHARGEBACK_REQUESTED';
    case CHARGEBACK_DISPUT            = 'CHARGEBACK_DISPUT';
    case AWAITING_CHARGEBACK_REVERSAL = 'AWAITING_CHARGEBACK_REVERSAL';
    case DUNNING_REQUESTED            = 'DUNNING_REQUESTED';
    case DUNNING_RECEIVED             = 'DUNNING_RECEIVED';
    case AWAITING_RISK_ANALYSIS       = 'AWAITING_RISK_ANALYSIS';

    /**
     * the same state in the library's vocabulary.
     *
     * `CONFIRMED` and `RECEIVED` are both PAID. Asaas separates them by
     * whether the balance is available — confirmed means the payer paid and
     * the money is not in the account yet — and that difference is about
     * settlement, not about whether the charge was paid. The distinction stays
     * readable in this enum for whoever needs it.
     *
     * **A refund that was asked for but has not happened is still PAID.** The
     * money has not gone back, and Asaas itself only considers a refund done
     * when it reaches `DONE`; reading `REFUND_IN_PROGRESS` as REFUNDED would
     * report money as returned before it was.
     *
     * `DUNNING_*` is credit recovery: requested means overdue and sent to
     * collection, received means it was recovered.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::PENDING                                    => PaymentStatus::PENDING,
            self::AWAITING_RISK_ANALYSIS                     => PaymentStatus::IN_ANALYSIS,
            self::RECEIVED, self::CONFIRMED                  => PaymentStatus::PAID,
            self::REFUND_REQUESTED, self::REFUND_IN_PROGRESS => PaymentStatus::PAID,
            self::DUNNING_RECEIVED                           => PaymentStatus::PAID,
            self::REFUNDED                                   => PaymentStatus::REFUNDED,
            self::CHARGEBACK_REQUESTED, self::CHARGEBACK_DISPUT,
            self::AWAITING_CHARGEBACK_REVERSAL     => PaymentStatus::IN_DISPUTE,
            self::OVERDUE, self::DUNNING_REQUESTED => PaymentStatus::EXPIRED,
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
