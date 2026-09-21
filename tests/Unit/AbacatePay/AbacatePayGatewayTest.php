<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\AbacatePay\Resources\Coupon\Coupon;
use PHPay\AbacatePay\Resources\Customer\Customer;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PHPay;

it('declara apenas clientes e cobranças', function () {
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CUSTOMERS,
        Capability::CHARGES,
    ]);
})->group('abacatepay');

it('não declara chaves pix, mesmo sendo um gateway pix-nativo', function () {
    $phpay = PHPay::gateway(new AbacatePayGateway('token', abacateClient([])));

    expect($phpay->supports(Capability::PIX_KEYS))->toBeFalse();

    expect(fn () => $phpay->pix())
        ->toThrow(NotImplementedException::class, 'AbacatePay não suporta chaves Pix');
})->group('abacatepay');

it('não declara assinaturas, porque a api só aceita ONE_TIME', function () {
    $phpay = PHPay::gateway(new AbacatePayGateway('token', abacateClient([])));

    expect($phpay->supports(Capability::SUBSCRIPTIONS))->toBeFalse()
        ->and($phpay->supports(Capability::WEBHOOKS))->toBeFalse();

    expect(fn () => $phpay->subscription())->toThrow(NotImplementedException::class);
})->group('abacatepay');

it('expõe cupons só no gateway concreto, fora da facade', function () {
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect($gateway->coupons())->toBeInstanceOf(Coupon::class)
        ->and(method_exists(PHPay::class, 'coupons'))->toBeFalse();
})->group('abacatepay');

it('devolve a instância de cada recurso suportado', function () {
    $phpay = PHPay::gateway(new AbacatePayGateway('token', abacateClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class);
})->group('abacatepay');

it('usa host único com bearer, sem flag de sandbox', function () {
    $charge = (new AbacatePayGateway('minha-chave'))->charge();

    $property = new ReflectionProperty($charge, 'client');
    $client   = $property->getValue($charge);

    expect((string) $client->getConfig('base_uri'))->toBe('https://api.abacatepay.com/v1/')
        ->and($client->getConfig('headers')['Authorization'])->toBe('Bearer minha-chave')
        ->and(method_exists(AbacatePayGateway::class, 'isSandbox'))->toBeFalse();
})->group('abacatepay');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new AbacatePayGateway('token', abacateClient([], $history));

    expect($history)->toBeEmpty();
})->group('abacatepay');
