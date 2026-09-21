<?php

namespace PHPay\Cielo\Enums;

enum PaymentTypeEnum: string
{
    case CREDIT_CARD = 'CreditCard';
    case DEBIT_CARD  = 'DebitCard';
    case PIX         = 'Pix';
    case BOLETO      = 'Boleto';
}
