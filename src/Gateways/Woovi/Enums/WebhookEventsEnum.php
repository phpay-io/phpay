<?php

namespace PHPay\Woovi\Enums;

use PHPay\Webhooks\EventType;

/**
 * events the Woovi/OpenPix webhook sends, in the `event` field.
 *
 * the names carry the `OPENPIX:` prefix from before the brands merged, the
 * same inheritance that leaves the webhook resource on a different path
 * prefix from the rest of the API.
 *
 * the authoritative list is `GET /api/v1/webhook/events` — the published docs
 * and the integration pages disagree on a few names, so only the ones the
 * webhook documentation itself describes are listed here, and anything else
 * degrades to UNKNOWN instead of being guessed.
 */
enum WebhookEventsEnum: string
{
    case CHARGE_CREATED                     = 'OPENPIX:CHARGE_CREATED';
    case CHARGE_COMPLETED                   = 'OPENPIX:CHARGE_COMPLETED';
    case CHARGE_COMPLETED_NOT_SAME_CUSTOMER = 'OPENPIX:CHARGE_COMPLETED_NOT_SAME_CUSTOMER_PAYER';
    case CHARGE_EXPIRED                     = 'OPENPIX:CHARGE_EXPIRED';
    case TRANSACTION_RECEIVED               = 'OPENPIX:TRANSACTION_RECEIVED';
    case TRANSACTION_REFUND_RECEIVED        = 'OPENPIX:TRANSACTION_REFUND_RECEIVED';

    /**
     * the same event in the library's vocabulary.
     *
     * `CHARGE_COMPLETED_NOT_SAME_CUSTOMER_PAYER` is PAID like the plain one:
     * the money arrived. That the payer differs from the charge's customer is
     * a reconciliation concern, and the raw event name says which it was.
     *
     * @return EventType
     */
    public function type(): EventType
    {
        return match ($this) {
            self::CHARGE_CREATED => EventType::CREATED,
            self::CHARGE_COMPLETED,
            self::CHARGE_COMPLETED_NOT_SAME_CUSTOMER,
            self::TRANSACTION_RECEIVED        => EventType::PAID,
            self::TRANSACTION_REFUND_RECEIVED => EventType::REFUNDED,
            self::CHARGE_EXPIRED              => EventType::EXPIRED,
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
