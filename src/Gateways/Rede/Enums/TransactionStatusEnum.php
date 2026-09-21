<?php

namespace PHPay\Rede\Enums;

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
}
