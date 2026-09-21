<?php

namespace PHPay\Cielo\Enums;

/**
 * status codes of a Cielo sale, as returned in Payment.Status.
 */
enum SaleStatusEnum: int
{
    case NOT_FINISHED      = 0;
    case AUTHORIZED        = 1;
    case PAYMENT_CONFIRMED = 2;
    case DENIED            = 3;
    case VOIDED            = 10;
    case REFUNDED          = 11;
    case PENDING           = 12;
    case ABORTED           = 13;
    case SCHEDULED         = 20;
}
