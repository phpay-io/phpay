<?php

namespace PHPay\AbacatePay\Enums;

/**
 * how often a billing charges.
 *
 * the API documents ONE_TIME as the only accepted value, which is why the
 * gateway does not declare SupportsSubscriptions.
 */
enum BillingFrequencyEnum: string
{
    case ONE_TIME = 'ONE_TIME';
}
