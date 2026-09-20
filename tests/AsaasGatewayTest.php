<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Interface\AsaasGatewayInterface;
use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Asaas\Resources\Webhook\Webhook;
use PHPay\Contracts\GatewayInterface;
use PHPay\PHPay;

test('boot asaas gateway class', function () {
    expect(AsaasGateway::class)
        ->toImplement(GatewayInterface::class)
        ->and(AsaasGateway::class)->toImplement(AsaasGatewayInterface::class);
})->group('asaas');

test('cada recurso do asaas devolve a instância esperada', function () {
    $phpay = new PHPay(new AsaasGateway('token', true, mockClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->webhook())->toBeInstanceOf(Webhook::class)
        ->and($phpay->pix())->toBeInstanceOf(Pix::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('asaas');

test('usa a url de sandbox por padrão e a de produção quando desligado', function () {
    $sandbox    = (new ReflectionClass(Customer::class))->newInstance('token', [], true);
    $producao   = (new ReflectionClass(Customer::class))->newInstance('token', [], false);
    $lerCliente = function (Customer $customer): string {
        $property = new ReflectionProperty(Customer::class, 'client');

        return (string) $property->getValue($customer)->getConfig('base_uri');
    };

    expect($lerCliente($sandbox))->toBe('https://sandbox.asaas.com/api/v3/')
        ->and($lerCliente($producao))->toBe('https://www.asaas.com/api/v3/');
})->group('asaas');
