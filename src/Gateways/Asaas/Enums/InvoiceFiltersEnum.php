<?php

namespace PHPay\Asaas\Enums;

enum InvoiceFiltersEnum: string
{
    case OFFSET                        = 'page';
    case LIMIT                         = 'limit';
    case CUSTOMER                      = 'client';
    case CUSTMER_GROUP                 = 'client_group';
    case BILLING_TYPE                  = BillingTypeEnum::class;
    case STATUS                        = InvoiceStatusEnum::class;
    case SUBSCRIPTION                  = 'subscription';
    case INSTALLMENT                   = 'installment';
    case EXTERNAL_REFERENCE            = 'external_reference';
    case PAYMENT_DATE                  = 'payment_date';
    case INVOICE_STATUS                = InvoiceStatusFiscalDocumentEnum::class;
    case ESTIMAL_CREDIT_DATE           = 'estimal_credit_date';
    case PIX_QR_CODE_ID                = 'pix_qr_code_id';
    case ANTECIPATED                   = 'anticipated';
    case ANTECIPABLE                   = 'antecipable';
    case DATE_CREATED_INITIAL          = 'date_created_initial';
    case DATE_CREATED_FINAL            = 'date_created_final';
    case DATE_PAYMENT_INITIAL          = 'date_payment_initial';
    case DATE_PAYMENT_FINAL            = 'date_payment_final';
    case ESTIMATED_CREDIT_DATE_INITIAL = 'estimated_credit_date_initial';
    case ESTIMATED_CREDIT_DATE_FINAL   = 'estimated_credit_date_final';
    case DUE_DATE_INITIAL              = 'due_date_initial';
    case DUE_DATE_FINAL                = 'due_date_final';
}
