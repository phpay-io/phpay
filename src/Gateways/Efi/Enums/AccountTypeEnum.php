<?php

namespace PHPay\Efi\Enums;

/**
 * account that receives a Pix Automático charge — the values of the BACEN
 * standard.
 */
enum AccountTypeEnum: string
{
    case CHECKING = 'CORRENTE';
    case SAVINGS  = 'POUPANCA';
    case PAYMENT  = 'PAGAMENTO';
}
