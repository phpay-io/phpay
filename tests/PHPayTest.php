<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Contracts\GatewayInterface;
use PHPay\PHPay;

test('boot phpay class', function () {
    expect(PHPay::class)
        ->toImplement(GatewayInterface::class)
        ->toHaveMethods(['__construct', 'customer', 'charge', 'webhook', 'pix', 'subscription']);
})->group('phpay');

test('a facade delega todos os recursos para o gateway injetado', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));
    $phpay   = PHPay::gateway($gateway);

    expect($phpay->customer([]))->toEqual($gateway->customer([]))
        ->and($phpay->charge())->toEqual($gateway->charge())
        ->and($phpay->webhook())->toEqual($gateway->webhook())
        ->and($phpay->pix())->toEqual($gateway->pix())
        ->and($phpay->subscription())->toEqual($gateway->subscription());
})->group('phpay');

test('todo gateway declara o contrato completo do GatewayInterface', function () {
    $contrato = new ReflectionClass(GatewayInterface::class);

    expect(array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        $contrato->getMethods()
    ))->toEqualCanonicalizing(['customer', 'charge', 'webhook', 'pix', 'subscription']);
})->group('phpay');
