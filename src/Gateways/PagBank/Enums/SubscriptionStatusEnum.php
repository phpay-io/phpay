<?php

namespace PHPay\PagBank\Enums;

enum SubscriptionStatusEnum: string
{
    case ACTIVE    = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case CANCELED  = 'CANCELED';
    case EXPIRED   = 'EXPIRED';
}
