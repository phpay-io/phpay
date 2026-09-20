<?php

namespace PHPay\MercadoPago\Enums;

/**
 * payment methods that are addressed by a fixed id.
 *
 * card payments are not listed: their payment_method_id comes from the
 * tokenization step and varies by issuer.
 */
enum PaymentMethodEnum: string
{
    case PIX           = 'pix';
    case BOLETO        = 'bolbradesco';
    case ACCOUNT_MONEY = 'account_money';
}
