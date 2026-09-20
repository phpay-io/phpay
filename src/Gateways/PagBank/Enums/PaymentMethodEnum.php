<?php

namespace PHPay\PagBank\Enums;

/**
 * payment methods accepted inside a charge of an order.
 *
 * Pix is absent on purpose: it does not travel as a charge, it is requested
 * through the order's `qr_codes` array.
 */
enum PaymentMethodEnum: string
{
    case CREDIT_CARD = 'CREDIT_CARD';
    case DEBIT_CARD  = 'DEBIT_CARD';
    case BOLETO      = 'BOLETO';
}
