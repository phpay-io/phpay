<?php

namespace PHPay\MercadoPago\Enums;

enum SubscriptionStatusEnum: string
{
    case PENDING    = 'pending';
    case AUTHORIZED = 'authorized';
    case PAUSED     = 'paused';
    case CANCELLED  = 'cancelled';
}
