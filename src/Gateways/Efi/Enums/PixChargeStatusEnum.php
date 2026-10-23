<?php

namespace PHPay\Efi\Enums;

use PHPay\Support\PaymentStatus;

/**
 * status of a Pix charge (`cob`) in the Efí **Pix** API.
 *
 * these are the BACEN states, not Efí's: the Pix API follows the central
 * bank's specification, so the same four values come back from any PSP that
 * implements it. The Cobranças API of this same gateway answers in lowercase
 * English instead — that is `ChargeStatusEnum`.
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
