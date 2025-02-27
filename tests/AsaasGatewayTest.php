<?php

test('boot asaas gateway class', function () {
    $phpay = \PHPay\PHPay::class;
    $asaas = \PHPay\Asaas\AsaasGateway::class;

    expect(class_exists($asaas))
        ->toBe(true);

    expect($asaas)
        ->toImplement(\PHPay\Contracts\GatewayInterface::class);

    expect($asaas)
        ->toImplement(\PHPay\Asaas\Interface\AsaasGatewayInterface::class);

    $assasInstance = new $phpay(new $asaas('token-here'));

    expect($assasInstance->customer([]))
        ->toBeObject()
        ->toBeInstanceOf(\PHPay\Asaas\Resources\Customer\Customer::class);

    expect($assasInstance->charge())
        ->toBeObject()
        ->toBeInstanceOf(\PHPay\Asaas\Resources\Charge\Charge::class);

    expect($assasInstance->webhook())
        ->toBeObject()
        ->toBeInstanceOf(\PHPay\Asaas\Resources\Webhook\Webhook::class);
})->group('asaas');
