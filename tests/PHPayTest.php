<?php

use PHPay\PHPay;

test('boot phpay class', function () {
    $phpay = PHPay::class;

    expect(class_exists($phpay))
        ->toBe(true);

    expect($phpay)
        ->toImplement(\PHPay\Contracts\GatewayInterface::class);

    expect($phpay)->hasMethod('__construct');
    expect($phpay)->hasMethod('customer');
    expect($phpay)->hasMethod('charge');
    expect($phpay)->hasMethod('webhook');
    expect($phpay)->hasMethod('pix');
})->group('phpay');
