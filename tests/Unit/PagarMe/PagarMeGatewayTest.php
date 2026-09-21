<?php

use PHPay\Contracts\Capability;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PagarMe\Resources\Customer\Customer;
use PHPay\PagarMe\Resources\Subscription\Subscription;
use PHPay\PagarMe\Resources\WebhookDelivery\WebhookDelivery;
use PHPay\PHPay;

it('declara apenas clientes, cobranças e assinaturas', function () {
    $gateway = new PagarMeGateway('sk_test_abc', pagarmeClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CUSTOMERS,
        Capability::CHARGES,
        Capability::SUBSCRIPTIONS,
    ]);
})->group('pagarme');

it('não declara webhooks, porque /hooks lê entregas e não cadastra endpoints', function (Capability $capability) {
    $phpay = PHPay::gateway(new PagarMeGateway('sk_test_abc', pagarmeClient([])));

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => $capability === Capability::WEBHOOKS ? $phpay->webhook() : $phpay->pix())
        ->toThrow(NotImplementedException::class, 'Pagar.me não suporta');
})->with([Capability::WEBHOOKS, Capability::PIX_KEYS])->group('pagarme');

it('expõe a leitura de entregas só no gateway concreto, fora da facade', function () {
    $gateway = new PagarMeGateway('sk_test_abc', pagarmeClient([]));

    expect($gateway->webhookDeliveries())->toBeInstanceOf(WebhookDelivery::class)
        ->and(method_exists(PHPay::class, 'webhookDeliveries'))->toBeFalse();
})->group('pagarme');

it('devolve a instância de cada recurso suportado', function () {
    $phpay = PHPay::gateway(new PagarMeGateway('sk_test_abc', pagarmeClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('pagarme');

it('identifica o ambiente pelo prefixo da chave, não por host', function () {
    expect((new PagarMeGateway('sk_test_abc'))->isSandbox())->toBeTrue()
        ->and((new PagarMeGateway('sk_live_abc'))->isSandbox())->toBeFalse();
})->group('pagarme');

it('autentica com basic auth e senha vazia', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['data' => []])], $history);

    (new Customer('sk_test_abc', [], $client))->getAll();

    /* o mock não carrega os headers do client real, então validamos o boot */
    $charge = new Charge('sk_test_abc');

    $property = new ReflectionProperty($charge, 'client');
    $headers  = $property->getValue($charge)->getConfig('headers');

    expect($headers['Authorization'])->toBe('Basic ' . base64_encode('sk_test_abc:'))
        ->and((string) $property->getValue($charge)->getConfig('base_uri'))
        ->toBe('https://api.pagar.me/core/v5/');
})->group('pagarme');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new PagarMeGateway('sk_test_abc', pagarmeClient([], $history));

    expect($history)->toBeEmpty();
})->group('pagarme');
