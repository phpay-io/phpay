<?php

namespace PHPay\Inter\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a Pix charge (`cob` and `cobv`) in the Inter Pix API.
 *
 * these are the BACEN states, not Inter's: the Pix API follows the central
 * bank's specification, so the same four values come back from any PSP that
 * implements it — they are the very ones `Efi\Enums\PixChargeStatusEnum`
 * carries. The enums stay separate anyway, because a gateway is free to add a
 * state and `of()` has to answer for one gateway's vocabulary, not for a
 * shared one.
 */
enum PixChargeStatusEnum: string
{
    case ATIVA                           = 'ATIVA';
    case CONCLUIDA                       = 'CONCLUIDA';
    case REMOVIDA_PELO_USUARIO_RECEBEDOR = 'REMOVIDA_PELO_USUARIO_RECEBEDOR';
    case REMOVIDA_PELO_PSP               = 'REMOVIDA_PELO_PSP';

    /**
     * the same state in the library's vocabulary.
     *
     * both `REMOVIDA_*` are CANCELED: the charge was called off before anyone
     * paid. Which side removed it — the receiver or the PSP — stays readable
     * in this enum, because it changes who to ask about it.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::ATIVA     => PaymentStatus::PENDING,
            self::CONCLUIDA => PaymentStatus::PAID,
            self::REMOVIDA_PELO_USUARIO_RECEBEDOR,
            self::REMOVIDA_PELO_PSP => PaymentStatus::CANCELED,
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
