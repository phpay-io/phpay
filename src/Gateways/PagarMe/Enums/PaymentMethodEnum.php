<?php

namespace PHPay\PagarMe\Enums;

enum PaymentMethodEnum: string
{
    case CREDIT_CARD = 'credit_card';
    case DEBIT_CARD  = 'debit_card';
    case BOLETO      = 'boleto';
    case PIX         = 'pix';
}
