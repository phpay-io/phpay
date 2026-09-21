<?php

namespace PHPay\AbacatePay\Enums;

enum BillingStatusEnum: string
{
    case PENDING   = 'PENDING';
    case EXPIRED   = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
    case PAID      = 'PAID';
    case REFUNDED  = 'REFUNDED';
}
