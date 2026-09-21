<?php

namespace PHPay\Asaas\Enums;

enum BillingTypeEnum: string
{
    case UNDEFINED   = 'UNDEFINED';
    case BOLETO      = 'BOLETO';
    case CREDIT_CARD = 'CREDIT_CARD';
    case PIX         = 'PIX';
}
