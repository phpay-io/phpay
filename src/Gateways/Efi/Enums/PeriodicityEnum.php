<?php

namespace PHPay\Efi\Enums;

/**
 * how often a Pix Automático recurrence charges — the values of the BACEN
 * standard.
 */
enum PeriodicityEnum: string
{
    case WEEKLY     = 'SEMANAL';
    case MONTHLY    = 'MENSAL';
    case QUARTERLY  = 'TRIMESTRAL';
    case SEMIANNUAL = 'SEMESTRAL';
    case YEARLY     = 'ANUAL';
}
