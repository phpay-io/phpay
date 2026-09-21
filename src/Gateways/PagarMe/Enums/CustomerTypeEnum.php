<?php

namespace PHPay\PagarMe\Enums;

enum CustomerTypeEnum: string
{
    case INDIVIDUAL = 'individual';
    case COMPANY    = 'company';
}
