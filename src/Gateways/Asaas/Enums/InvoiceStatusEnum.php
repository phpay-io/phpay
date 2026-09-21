<?php

namespace PHPay\Asaas\Enums;

enum InvoiceStatusEnum: string
{
    case PENDING                      = 'PENDING';
    case RECEIVED                     = 'RECEIVED';
    case CONFIRMED                    = 'CONFIRMED';
    case OVERDUE                      = 'OVERDUE';
    case REFUNDED                     = 'REFUNDED';
    case REFUND_REQUESTED             = 'REFUNDED_REQUESTED';
    case REFUND_IN_PROGRESS           = 'REFUND_IN_PROGRESS';
    case CHARGEBACK_REQUESTED         = 'CHARGEBACK_REQUESTED';
    case CHARGEBACK_DISPUT            = 'CHARGEBACK_DISPUT';
    case AWAITING_CHARGEBACK_REVERSAL = 'AWAITING_CHARGEBACK_REVERSAL';
    case DUNNING_REQUESTED            = 'DUNNING_REQUESTED';
    case DUNNING_RECEIVED             = 'DUNNING_RECEIVED';
    case AWAITING_RISK_ANALYSIS       = 'AWAITING_RISK_ANALYSIS';
}
