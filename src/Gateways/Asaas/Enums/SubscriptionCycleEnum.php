<?php

namespace PHPay\Asaas\Enums;

/**
 * how often an Asaas subscription generates a charge.
 */
enum SubscriptionCycleEnum: string
{
    case WEEKLY       = 'WEEKLY';
    case BIWEEKLY     = 'BIWEEKLY';
    case MONTHLY      = 'MONTHLY';
    case BIMONTHLY    = 'BIMONTHLY';
    case QUARTERLY    = 'QUARTERLY';
    case SEMIANNUALLY = 'SEMIANNUALLY';
    case YEARLY       = 'YEARLY';
}
