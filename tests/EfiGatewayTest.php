<?php

use PHPay\Contracts\GatewayInterface;
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Interface\EfiGatewayInterface;

test('boot efi gateway class', function () {
    expect(EfiGateway::class)
        ->toImplement(GatewayInterface::class)
        ->and(EfiGateway::class)->toImplement(EfiGatewayInterface::class);
})->group('efi');
