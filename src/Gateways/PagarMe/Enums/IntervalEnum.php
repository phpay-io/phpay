<?php

namespace PHPay\PagarMe\Enums;

enum IntervalEnum: string
{
    case DAY   = 'day';
    case WEEK  = 'week';
    case MONTH = 'month';
    case YEAR  = 'year';
}
