<?php

namespace PHPay\Rede\Enums;

enum TransactionKindEnum: string
{
    case CREDIT = 'credit';
    case DEBIT  = 'debit';
}
