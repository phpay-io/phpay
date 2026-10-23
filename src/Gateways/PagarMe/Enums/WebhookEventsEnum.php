<?php

namespace PHPay\PagarMe\Enums;

use PHPay\Webhooks\EventType;

/**
 * events the Pagar.me v5 webhook sends, in the `type` field.
 *
 * both levels are listed, order and charge, because the same account receives
 * both and the payload differs: `order.*` carries the order with its
 * `charges[]`, `charge.*` carries one charge.
 */
enum WebhookEventsEnum: string
{
    case ORDER_CREATED         = 'order.created';
    case ORDER_PAID            = 'order.paid';
    case ORDER_PAYMENT_FAILED  = 'order.payment_failed';
    case ORDER_CANCELED        = 'order.canceled';
    case CHARGE_CREATED        = 'charge.created';
    case CHARGE_PENDING        = 'charge.pending';
    case CHARGE_PAID           = 'charge.paid';
    case CHARGE_PAYMENT_FAILED = 'charge.payment_failed';
    case CHARGE_REFUNDED       = 'charge.refunded';
    case CHARGE_CHARGEDBACK    = 'charge.chargedback';
    case CHARGEBACK_RECEIVED   = 'chargeback.received';

    /**
     * the same event in the library's vocabulary.
     *
     * `charge.chargedback` and `chargeback.received` map to the same thing on
     * purpose: Pagar.me is replacing the first with the second, with migration
     * due by 30/09/2026, so both have to read alike while they coexist.
     *
     * @return EventType
     */
    public function type(): EventType
    {
        return match ($this) {
            self::ORDER_CREATED, self::CHARGE_CREATED => EventType::CREATED,
            self::CHARGE_PENDING                      => EventType::UPDATED,
            self::ORDER_PAID, self::CHARGE_PAID       => EventType::PAID,
            self::CHARGE_REFUNDED                     => EventType::REFUNDED,
            self::CHARGE_CHARGEDBACK,
            self::CHARGEBACK_RECEIVED => EventType::CHARGEBACK,
            self::ORDER_CANCELED      => EventType::CANCELED,
            self::ORDER_PAYMENT_FAILED,
            self::CHARGE_PAYMENT_FAILED => EventType::FAILED,
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
