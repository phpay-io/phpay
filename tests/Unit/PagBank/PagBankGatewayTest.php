<?php

use PHPay\Contracts\Capability;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PagBank\PagBankGateway;
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;
use PHPay\PHPay;

it('declara apenas clientes, cobranças e assinaturas', function () {
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CUSTOMERS,
        Capability::CHARGES,
        Capability::SUBSCRIPTIONS,
    ]);
})->group('pagbank');

it('não declara webhooks nem chaves pix', function (Capability $capability) {
    $phpay = PHPay::gateway(new PagBankGateway('token', true, pagbankClient([])));

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => $capability === Capability::WEBHOOKS ? $phpay->webhook() : $phpay->pix())
        ->toThrow(NotImplementedException::class, 'PagBank não suporta');
})->with([Capability::WEBHOOKS, Capability::PIX_KEYS])->group('pagbank');

it('devolve a instância de cada recurso suportado', function () {
    $phpay = PHPay::gateway(new PagBankGateway('token', true, pagbankClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('pagbank');

it('aponta cada recurso para o host da sua api', function () {
    $baseUri = function (object $resource): string {
        $property = new ReflectionProperty($resource, 'client');

        return (string) $property->getValue($resource)->getConfig('base_uri');
    };

    $sandbox  = new PagBankGateway('token');
    $producao = new PagBankGateway('token', false);

    expect($baseUri($sandbox->charge()))->toBe('https://sandbox.api.pagseguro.com/')
        ->and($baseUri($sandbox->customer()))->toBe('https://sandbox.api.assinaturas.pagseguro.com/')
        ->and($baseUri($sandbox->subscription()))->toBe('https://sandbox.api.assinaturas.pagseguro.com/')
        ->and($baseUri($producao->charge()))->toBe('https://api.pagseguro.com/')
        ->and($baseUri($producao->subscription()))->toBe('https://api.assinaturas.pagseguro.com/');
})->group('pagbank');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new PagBankGateway('token', true, pagbankClient([], $history));

    expect($history)->toBeEmpty();
})->group('pagbank');
