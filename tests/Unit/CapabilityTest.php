<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Contracts\{Capability, GatewayInterface};
use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PHPay;

it('mantém o contrato base restrito à identidade', function () {
    $metodos = array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        (new ReflectionClass(GatewayInterface::class))->getMethods()
    );

    expect($metodos)->toBe(['name']);
})->group('phpay');

it('liga cada capacidade à interface que a declara', function (Capability $capability) {
    expect(interface_exists($capability->contract()))->toBeTrue()
        ->and($capability->label())->not->toBeEmpty();
})->with(Capability::cases())->group('phpay');

it('reconhece todas as capacidades do asaas', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));

    expect(Capability::of($gateway))->toBe(Capability::cases());
})->group('asaas');

it('responde supports() e capabilities() pela facade', function () {
    $asaas = PHPay::gateway(new AsaasGateway('token', true, mockClient([])));
    $efi   = PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([])));

    expect($asaas->supports(Capability::PIX_KEYS))->toBeTrue()
        ->and($asaas->capabilities())->toHaveCount(5)
        ->and($efi->supports(Capability::PIX_KEYS))->toBeTrue()
        ->and($efi->supports(Capability::CUSTOMERS))->toBeFalse()
        ->and($efi->capabilities())->toBe([
            Capability::CHARGES,
            Capability::WEBHOOKS,
            Capability::PIX_KEYS,
            Capability::SUBSCRIPTIONS,
        ]);
})->group('phpay');

it('expõe o nome do gateway através da facade', function () {
    expect(PHPay::gateway(new AsaasGateway('token', true, mockClient([])))->name())->toBe('Asaas')
        ->and(PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([])))->name())->toBe('Efí');
})->group('phpay');

it('nomeia o gateway e as capacidades disponíveis ao recusar um recurso', function () {
    $phpay = PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([])));

    try {
        $phpay->customer();
        $this->fail('NotImplementedException não foi lançada');
    } catch (NotImplementedException $exception) {
        expect($exception->getMessage())
            ->toContain('Efí não suporta clientes')
            ->toContain('cobranças, webhooks, chaves Pix, assinaturas');
    }
})->group('phpay');
