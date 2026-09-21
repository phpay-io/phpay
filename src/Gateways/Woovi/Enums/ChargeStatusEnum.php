<?php

namespace PHPay\Woovi\Enums;

enum ChargeStatusEnum: string
{
    case ACTIVE             = 'ACTIVE';
    case COMPLETED          = 'COMPLETED';
    case EXPIRED            = 'EXPIRED';
    case PARTIALLY_REFUNDED = 'PARTIALLY_REFUNDED';
    case REFUNDED           = 'REFUNDED';
}
