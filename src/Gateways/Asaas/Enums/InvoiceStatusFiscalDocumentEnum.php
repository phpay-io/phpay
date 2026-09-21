<?php

namespace PHPay\Asaas\Enums;

enum InvoiceStatusFiscalDocumentEnum: string
{
    case SCHEDULED              = 'scheduled';
    case AUTHORIZED             = 'authorized';
    case PROCESSING_CANCELATION = 'processing_cancelation';
    case CANCELED               = 'canceled';
    case CANCELED_DENIED        = 'canceled_denied';
    case ERROR                  = 'error';
}
