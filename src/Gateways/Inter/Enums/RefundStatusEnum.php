<?php

namespace PHPay\Inter\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a Pix return (`devolucao`) in the Inter Pix API.
 *
 * also BACEN states. Only `DEVOLVIDO` means the money went back: the other
 * two describe a return that is still being processed or that failed, and
 * reading either as a refund done would release an order twice.
 */
enum RefundStatusEnum: string
{
    case EM_PROCESSAMENTO = 'EM_PROCESSAMENTO';
    case DEVOLVIDO        = 'DEVOLVIDO';
    case NAO_REALIZADO    = 'NAO_REALIZADO';

    /**
     * the same state in the library's vocabulary.
     *
     * a return in progress is reported as the state of the **charge**, which
     * is still paid — the money has not left yet. A failed one is the same:
     * nothing was reversed.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::DEVOLVIDO                             => PaymentStatus::REFUNDED,
            self::EM_PROCESSAMENTO, self::NAO_REALIZADO => PaymentStatus::PAID,
        };
    }

    /**
     * whether the money actually went back.
     *
     * @return bool
     */
    public function isSettled(): bool
    {
        return $this === self::DEVOLVIDO;
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
