<?php

test('boot efi gateway class', function () {
    $efi = PHPay\Efi\EfiGateway::class;

    expect(class_exists($efi))
        ->toBe(true);

    expect($efi)
        ->toImplement(\PHPay\Contracts\GatewayInterface::class);

    expect($efi)
        ->toImplement(\Efi\Interface\EfiGatewayInterface::class);
})->group('efi');
