<?php

use PHPay\Cielo\CieloGateway;
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Contracts\Capability;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PHPay;

it('declara apenas cobranças e assinaturas', function () {
    $gateway = new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CHARGES,
        Capability::SUBSCRIPTIONS,
    ]);
})->group('cielo');

it('não declara clientes, porque a venda carrega o cliente embutido', function (Capability $capability) {
    $phpay = PHPay::gateway(new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([])));

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => match ($capability) {
        Capability::CUSTOMERS => $phpay->customer(),
        Capability::WEBHOOKS  => $phpay->webhook(),
        default               => $phpay->pix(),
    })->toThrow(NotImplementedException::class, 'Cielo não suporta');
})->with([Capability::CUSTOMERS, Capability::WEBHOOKS, Capability::PIX_KEYS])->group('cielo');

it('devolve a instância de cada recurso suportado', function () {
    $phpay = PHPay::gateway(new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([])));

    expect($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('cielo');

it('separa os hosts por tipo de operação, não por domínio', function () {
    $lerUri = function (object $recurso, string $propriedade): string {
        $property = new ReflectionProperty($recurso, $propriedade);

        return (string) $property->getValue($recurso)->getConfig('base_uri');
    };

    $sandbox  = (new CieloGateway('id', 'key'))->charge();
    $producao = (new CieloGateway('id', 'key', false))->charge();

    expect($lerUri($sandbox, 'client'))->toBe('https://apisandbox.cieloecommerce.cielo.com.br/')
        ->and($lerUri($sandbox, 'queryClient'))->toBe('https://apiquerysandbox.cieloecommerce.cielo.com.br/')
        ->and($lerUri($producao, 'client'))->toBe('https://api.cieloecommerce.cielo.com.br/')
        ->and($lerUri($producao, 'queryClient'))->toBe('https://apiquery.cieloecommerce.cielo.com.br/');
})->group('cielo');

it('autentica por headers MerchantId e MerchantKey', function () {
    $charge = (new CieloGateway('minha-loja', 'minha-chave'))->charge();

    $property = new ReflectionProperty($charge, 'client');
    $headers  = $property->getValue($charge)->getConfig('headers');

    expect($headers['MerchantId'])->toBe('minha-loja')
        ->and($headers['MerchantKey'])->toBe('minha-chave')
        ->and($headers)->not->toHaveKey('Authorization');
})->group('cielo');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new CieloGateway('id', 'key', true, cieloClient([], $history));

    expect($history)->toBeEmpty();
})->group('cielo');
