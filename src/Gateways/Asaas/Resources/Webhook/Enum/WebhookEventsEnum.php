<?php

namespace PHPay\Asaas\Resources\Webhook\Enum;

use PHPay\Webhooks\EventType;

enum WebhookEventsEnum: string
{
    case PAYMENT_CREATED                         = 'PAYMENT_CREATED';
    case PAYMENT_UPDATED                         = 'PAYMENT_UPDATED';
    case PAYMENT_CONFIRMED                       = 'PAYMENT_CONFIRMED';
    case PAYMENT_RECEIVED                        = 'PAYMENT_RECEIVED';
    case PAYMENT_OVERDUE                         = 'PAYMENT_OVERDUE';
    case PAYMENT_DELETED                         = 'PAYMENT_DELETED';
    case PAYMENT_AWAITING_RISK_ANALYSIS          = 'PAYMENT_AWAITING_RISK_ANALYSIS';
    case PAYMENT_APPROVED_BY_RISK_ANALYSIS       = 'PAYMENT_APPROVED_BY_RISK_ANALYSIS';
    case PAYMENT_REPROVED_BY_RISK_ANALYSIS       = 'PAYMENT_REPROVED_BY_RISK_ANALYSIS';
    case PAYMENT_AUTHORIZED                      = 'PAYMENT_AUTHORIZED';
    case PAYMENT_CREDIT_CARD_CAPTURE_REFUSED     = 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED';
    case PAYMENT_ANTICIPATED                     = 'PAYMENT_ANTICIPATED';
    case PAYMENT_RESTORED                        = 'PAYMENT_RESTORED';
    case PAYMENT_REFUNDED                        = 'PAYMENT_REFUNDED';
    case PAYMENT_PARTIALLY_REFUNDED              = 'PAYMENT_PARTIALLY_REFUNDED';
    case PAYMENT_REFUND_IN_PROGRESS              = 'PAYMENT_REFUND_IN_PROGRESS';
    case PAYMENT_RECEIVED_IN_CASH_UNDONE         = 'PAYMENT_RECEIVED_IN_CASH_UNDONE';
    case PAYMENT_CHARGEBACK_REQUESTED            = 'PAYMENT_CHARGEBACK_REQUESTED';
    case PAYMENT_CHARGEBACK_DISPUTE              = 'PAYMENT_CHARGEBACK_DISPUTE';
    case PAYMENT_AWAITING_CHARGEBACK_REVERSAL    = 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL';
    case PAYMENT_DUNNING_RECEIVED                = 'PAYMENT_DUNNING_RECEIVED';
    case PAYMENT_DUNNING_REQUESTED               = 'PAYMENT_DUNNING_REQUESTED';
    case PAYMENT_BANK_SLIP_VIEWED                = 'PAYMENT_BANK_SLIP_VIEWED';
    case PAYMENT_CHECKOUT_VIEWED                 = 'PAYMENT_CHECKOUT_VIEWED';
    case PAYMENT_SPLIT_CANCELLED                 = 'PAYMENT_SPLIT_CANCELLED';
    case PAYMENT_SPLIT_DIVERGENCE_BLOCK          = 'PAYMENT_SPLIT_DIVERGENCE_BLOCK';
    case PAYMENT_SPLIT_DIVERGENCE_BLOCK_FINISHED = 'PAYMENT_SPLIT_DIVERGENCE_BLOCK_FINISHED';

    /**
     * the same event in the library's vocabulary.
     *
     * `PAYMENT_CONFIRMED` and `PAYMENT_RECEIVED` both become PAID: Asaas
     * separates them by whether the balance is available, which is settlement
     * and not whether the payer paid. The specific case stays readable here.
     *
     * the events that report a *view* (`BANK_SLIP_VIEWED`, `CHECKOUT_VIEWED`)
     * and the split ones become UPDATED rather than UNKNOWN: they are known
     * events, they just do not move the charge between the states this
     * vocabulary describes.
     *
     * @return EventType
     */
    public function type(): EventType
    {
        return match ($this) {
            self::PAYMENT_CREATED    => EventType::CREATED,
            self::PAYMENT_AUTHORIZED => EventType::AUTHORIZED,
            self::PAYMENT_CONFIRMED,
            self::PAYMENT_RECEIVED,
            self::PAYMENT_DUNNING_RECEIVED,
            self::PAYMENT_ANTICIPATED        => EventType::PAID,
            self::PAYMENT_PARTIALLY_REFUNDED => EventType::PARTIALLY_REFUNDED,
            self::PAYMENT_REFUNDED           => EventType::REFUNDED,
            self::PAYMENT_CHARGEBACK_REQUESTED,
            self::PAYMENT_CHARGEBACK_DISPUTE,
            self::PAYMENT_AWAITING_CHARGEBACK_REVERSAL => EventType::DISPUTE_OPENED,
            self::PAYMENT_OVERDUE,
            self::PAYMENT_DUNNING_REQUESTED => EventType::EXPIRED,
            self::PAYMENT_DELETED           => EventType::CANCELED,
            self::PAYMENT_REPROVED_BY_RISK_ANALYSIS,
            self::PAYMENT_CREDIT_CARD_CAPTURE_REFUSED => EventType::FAILED,
            default                                   => EventType::UPDATED,
        };
    }

    /**
     * read a raw event name, tolerating one this version does not know.
     *
     * @param string|null $event
     * @return EventType
     */
    public static function of(?string $event): EventType
    {
        return self::tryFrom($event ?? '')?->type() ?? EventType::UNKNOWN;
    }
}
