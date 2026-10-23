<?php

namespace PHPay\Webhooks;

/**
 * what happened, in one vocabulary.
 *
 * the counterpart of `PaymentStatus`: that one says what a charge **is**, this
 * one says what just **happened** to it. They are not the same question — a
 * notification of type REFUNDED tells you a refund occurred, while a status of
 * REFUNDED tells you the charge currently sits refunded, and a gateway can
 * report the second without ever sending the first.
 */
enum EventType: string
{
    /** the charge came into existence */
    case CREATED = 'created';

    /** something changed and the gateway did not say what */
    case UPDATED = 'updated';

    /** pre-authorized on the card, not captured */
    case AUTHORIZED = 'authorized';

    /** the money arrived */
    case PAID = 'paid';

    /** part of it went back */
    case PARTIALLY_REFUNDED = 'partially-refunded';

    /** all of it went back */
    case REFUNDED = 'refunded';

    /** the payer opened a dispute */
    case DISPUTE_OPENED = 'dispute-opened';

    /** the dispute was lost and the issuer took the money back */
    case CHARGEBACK = 'chargeback';

    /** the window closed with no payment */
    case EXPIRED = 'expired';

    /** called off */
    case CANCELED = 'canceled';

    /** refused */
    case FAILED = 'failed';

    /**
     * an event this version of the library does not know.
     *
     * same reasoning as `PaymentStatus::UNKNOWN`: gateways add events without
     * warning, and throwing would lose a notification the gateway will not
     * necessarily send again. The raw name stays on the Event.
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
            self::CREATED            => 'cobrança criada',
            self::UPDATED            => 'cobrança alterada',
            self::AUTHORIZED         => 'autorizada, aguardando captura',
            self::PAID               => 'pagamento recebido',
            self::PARTIALLY_REFUNDED => 'estorno parcial',
            self::REFUNDED           => 'estorno',
            self::DISPUTE_OPENED     => 'disputa aberta',
            self::CHARGEBACK         => 'chargeback',
            self::EXPIRED            => 'cobrança vencida',
            self::CANCELED           => 'cobrança cancelada',
            self::FAILED             => 'pagamento recusado',
            self::UNKNOWN            => 'evento desconhecido',
        };
    }

    /**
     * whether this event means money arrived.
     *
     * @return bool
     */
    public function isPayment(): bool
    {
        return $this === self::PAID;
    }

    /**
     * whether this event means money left, by refund or by chargeback.
     *
     * the two are grouped because both reverse a payment, and whoever is
     * releasing an order cares that it was reversed. Which of the two it was
     * stays readable in the case itself.
     *
     * @return bool
     */
    public function isReversal(): bool
    {
        return match ($this) {
            self::REFUNDED, self::PARTIALLY_REFUNDED, self::CHARGEBACK => true,
            default                                                    => false,
        };
    }
}
