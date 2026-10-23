<?php

namespace PHPay\Rede\Enums;

use PHPay\Support\PaymentStatus;

/**
 * return codes of a Rede transaction, as sent in returnCode.
 *
 * only the codes worth branching on are listed — Rede publishes dozens of
 * decline reasons, and treating them as an exhaustive enum would break every
 * time they add one.
 */
enum TransactionStatusEnum: string
{
    case APPROVED = '00';
    case PENDING  = '220';

    /**
     * whether the given return code means the transaction went through.
     *
     * @param string $returnCode
     * @return bool
     */
    public static function approved(string $returnCode): bool
    {
        return $returnCode === self::APPROVED->value;
    }

    /**
     * the same state in the library's vocabulary.
     *
     * @return PaymentStatus
     */
    public function status(): PaymentStatus
    {
        return match ($this) {
            self::APPROVED => PaymentStatus::PAID,
            self::PENDING  => PaymentStatus::PENDING,
        };
    }

    /**
     * read a raw return code.
     *
     * **This is the one gateway where an unlisted value is not UNKNOWN but
     * FAILED**, and that follows from what the enum is: Rede's dozens of other
     * codes are all decline reasons, so a code that is neither `00` nor `220`
     * means the transaction did not go through. Answering UNKNOWN there would
     * hide a decline behind "I don't know", which is the worse reading of the
     * two. A missing code is still UNKNOWN — that is an absent field, not a
     * decline.
     *
     * @param string|null $returnCode
     * @return PaymentStatus
     */
    public static function of(?string $returnCode): PaymentStatus
    {
        if ($returnCode === null || $returnCode === '') {
            return PaymentStatus::UNKNOWN;
        }

        return self::tryFrom($returnCode)?->status() ?? PaymentStatus::FAILED;
    }
}
