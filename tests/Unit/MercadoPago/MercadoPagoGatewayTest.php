<?php

use PHPay\Contracts\Capability;
use PHPay\Exceptions\NotImplementedException;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\MercadoPago\Resources\Charge\Charge;
use PHPay\MercadoPago\Resources\Customer\Customer;
use PHPay\MercadoPago\Resources\Subscription\Subscription;
use PHPay\PHPay;

it('declara clientes, cobranças, estornos, assinaturas e verificação de assinatura', function () {
    $gateway = new MercadoPagoGateway('TEST-token', mpClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CUSTOMERS,
        Capability::CHARGES,
        Capability::REFUNDS,
        Capability::SUBSCRIPTIONS,
        Capability::WEBHOOK_SIGNATURE,
        Capability::WEBHOOK_EVENTS,
        Capability::CHARGE_INTENTS,
    ]);
})->group('mercadopago');

it('não declara webhooks nem chaves pix', function (Capability $capability) {
    $phpay = PHPay::gateway(new MercadoPagoGateway('TEST-token', mpClient([])));

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => $capability === Capability::WEBHOOKS ? $phpay->webhook() : $phpay->pix())
        ->toThrow(NotImplementedException::class, 'Mercado Pago não suporta');
})->with([Capability::WEBHOOKS, Capability::PIX_KEYS])->group('mercadopago');

it('devolve a instância de cada recurso suportado', function () {
    $phpay = PHPay::gateway(new MercadoPagoGateway('TEST-token', mpClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('mercadopago');

it('identifica o ambiente pelo prefixo do token, não por url', function () {
    expect((new MercadoPagoGateway('TEST-abc'))->isSandbox())->toBeTrue()
        ->and((new MercadoPagoGateway('APP_USR-abc'))->isSandbox())->toBeFalse();
})->group('mercadopago');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new MercadoPagoGateway('TEST-token', mpClient([], $history));

    expect($history)->toBeEmpty();
})->group('mercadopago');
