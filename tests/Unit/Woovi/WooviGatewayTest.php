<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Contracts\Capability;
use PHPay\PHPay;
use PHPay\Woovi\Resources\Charge\Charge;
use PHPay\Woovi\Resources\Customer\Customer;
use PHPay\Woovi\Resources\Pix\Pix;
use PHPay\Woovi\Resources\Subscription\Subscription;
use PHPay\Woovi\Resources\Webhook\Webhook;
use PHPay\Woovi\WooviGateway;

it('declara as cinco capacidades', function () {
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    expect(Capability::of($gateway))->toBe(Capability::cases());
})->group('woovi');

it('é o segundo gateway completo, junto com o asaas', function () {
    $woovi = new WooviGateway('app-id', true, wooviClient([]));
    $asaas = new AsaasGateway('token', true, mockClient([]));

    /* duas empresas independentes preenchendo o mesmo contrato */
    expect(Capability::of($woovi))->toBe(Capability::of($asaas))
        ->and(Capability::of($woovi))->toHaveCount(5);
})->group('woovi');

it('devolve a instância de cada um dos cinco recursos', function () {
    $phpay = PHPay::gateway(new WooviGateway('app-id', true, wooviClient([])));

    expect($phpay->customer([]))->toBeInstanceOf(Customer::class)
        ->and($phpay->charge())->toBeInstanceOf(Charge::class)
        ->and($phpay->webhook())->toBeInstanceOf(Webhook::class)
        ->and($phpay->pix())->toBeInstanceOf(Pix::class)
        ->and($phpay->subscription())->toBeInstanceOf(Subscription::class);
})->group('woovi');

it('manda o AppID cru no Authorization, sem esquema', function () {
    $charge = (new WooviGateway('meu-app-id'))->charge();

    $property = new ReflectionProperty($charge, 'client');
    $headers  = $property->getValue($charge)->getConfig('headers');

    expect($headers['Authorization'])->toBe('meu-app-id')
        ->and($headers['Authorization'])->not->toStartWith('Bearer')
        ->and($headers['Authorization'])->not->toStartWith('Basic');
})->group('woovi');

it('usa domínio próprio no sandbox', function () {
    $lerUri = function (object $recurso): string {
        $property = new ReflectionProperty($recurso, 'client');

        return (string) $property->getValue($recurso)->getConfig('base_uri');
    };

    expect($lerUri((new WooviGateway('id'))->charge()))->toBe('https://api.woovi-sandbox.com/')
        ->and($lerUri((new WooviGateway('id', false))->charge()))->toBe('https://api.openpix.com.br/');
})->group('woovi');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];

    new WooviGateway('app-id', true, wooviClient([], $history));

    expect($history)->toBeEmpty();
})->group('woovi');
