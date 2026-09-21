<?php

namespace PHPay\Cielo\Enums;

enum RecurrentIntervalEnum: string
{
    case MONTHLY    = 'Monthly';
    case BIMONTHLY  = 'Bimonthly';
    case QUARTERLY  = 'Quarterly';
    case SEMIANNUAL = 'SemiAnnual';
    case ANNUAL     = 'Annual';
}
