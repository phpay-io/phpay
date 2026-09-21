<?php

namespace PHPay\AbacatePay\Enums;

/**
 * payment methods a billing accepts.
 *
 * only Pix today, and the API takes exactly one — the enum exists so the
 * value is not a loose string, and so a second method has a place to land.
 */
enum BillingMethodEnum: string
{
    case PIX = 'PIX';
}
