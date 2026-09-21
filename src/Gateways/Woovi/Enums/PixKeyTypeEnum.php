<?php

namespace PHPay\Woovi\Enums;

/**
 * types of Pix key that can be registered.
 *
 * PHONE and EMAIL need extra permission on the account, per the Woovi docs.
 */
enum PixKeyTypeEnum: string
{
    case CPF    = 'CPF';
    case CNPJ   = 'CNPJ';
    case EMAIL  = 'EMAIL';
    case PHONE  = 'PHONE';
    case RANDOM = 'EVP';
}
