<?php

namespace PHPay\Cielo\Enums;

use PHPay\Webhooks\EventType;

/**
 * the `ChangeType` of a Cielo notification post.
 *
 * the whole notification is three fields — `PaymentId`, `ChangeType` and
 * sometimes `RecurrentPaymentId` — and the documentation's own instruction for
 * type 1 is to query the transaction to see the current status. So most of
 * these are UPDATED, honestly: Cielo says something moved, not what it became.
 */
enum NotificationChangeTypeEnum: int
{
    case TRANSACTION_STATUS             = 1;
    case RECURRENCE_CREATED             = 2;
    case ANTIFRAUD_STATUS               = 3;
    case RECURRENT_PAYMENT              = 4;
    case CANCELLATION_DENIED            = 5;
    case BOLETO_PAID_SHORT              = 6;
    case CHARGEBACK                     = 7;
    case FRAUD_ALERT                    = 8;
    case CANCELED_OR_PARTIALLY_REFUNDED = 25;

    /**
     * the same change in the library's vocabulary.
     *
     * three are left as UPDATED on purpose, and the reason is the same each
     * time — the code conflates outcomes the library would have to guess
     * between:
     *
     * `BOLETO_PAID_SHORT` (6) means money arrived but short. The event
     * vocabulary has no case for that; the status side does
     * (`PaymentStatus::PARTIALLY_PAID`), reachable after the lookup Cielo asks
     * for anyway.
     *
     * `CANCELED_OR_PARTIALLY_REFUNDED` (25) is, by its own name, two different
     * things. Picking either would be wrong half the time.
     *
     * `CANCELLATION_DENIED` (5) is a failed cancellation, which says nothing
     * about the charge's own state.
     *
     * @return EventType
     */
    public function type(): EventType
    {
        return match ($this) {
            self::RECURRENCE_CREATED => EventType::CREATED,
            self::CHARGEBACK         => EventType::CHARGEBACK,
            self::FRAUD_ALERT        => EventType::DISPUTE_OPENED,
            default                  => EventType::UPDATED,
        };
    }

    /**
     * read a raw change type, tolerating one this version does not know.
     *
     * @param mixed $changeType
     * @return EventType
     */
    public static function of(mixed $changeType): EventType
    {
        if (is_int($changeType)) {
            return self::tryFrom($changeType)?->type() ?? EventType::UNKNOWN;
        }

        /* the field is documented as numeric, but arrives as a string in some posts */
        if (is_string($changeType) && ctype_digit($changeType)) {
            return self::tryFrom((int) $changeType)?->type() ?? EventType::UNKNOWN;
        }

        return EventType::UNKNOWN;
    }
}
