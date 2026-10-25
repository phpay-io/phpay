<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Asaas\AsaasGateway;
use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\Capability;
use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\NotImplementedException;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\PagBankGateway;
use PHPay\PHPay;
use PHPay\Support\Money;
use PHPay\Woovi\WooviGateway;

it('estorna pela facade, sem o chamador saber o nome do método daquele gateway', function () {
    $history = [];
    $phpay   = PHPay::gateway(
        new AsaasGateway('token', true, mockClient([jsonResponse(['status' => 'REFUNDED'])], $history))
    );

    $phpay->refund('pay_001', Money::reais(25.50));

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001/refund')
        ->and(recordedBody($history, 0))->toBe(['value' => 25.5]);
})->group('phpay');

it('chega ao gateway cujo estorno se chamava outra coisa', function () {
    $escrita = [];
    $phpay   = PHPay::gateway(new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([jsonResponse([])], $escrita)));

    /* na Cielo o método é cancel(); pela capacidade, quem chama diz refund() */
    $phpay->refund('pay_1', 2500);

    expect((string) $escrita[0]['request']->getUri())->toEndWith('/1/sales/pay_1/void?amount=2500');
})->group('phpay');

it('recusa o estorno sem sair para a rede onde o gateway não o documenta', function () {
    $history = [];
    $efi     = PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([], $history)));

    expect($efi->supports(Capability::REFUNDS))->toBeFalse()
        ->and(fn () => $efi->refund('qualquer-id'))
        ->toThrow(NotImplementedException::class, 'Efí não suporta');

    /* o guard é antes do HTTP: nada foi tentado */
    expect($history)->toBeEmpty();
})->group('phpay');

it('recusa o estorno na AbacatePay, cuja v1 não descreve reembolso de billing', function () {
    $history = [];
    $phpay   = PHPay::gateway(new AbacatePayGateway('token', abacateClient([], $history)));

    expect($phpay->supports(Capability::REFUNDS))->toBeFalse()
        ->and(fn () => $phpay->refund('bill_1'))
        ->toThrow(NotImplementedException::class, 'AbacatePay não suporta');

    expect($history)->toBeEmpty();
})->group('phpay');

it('declara estornos em sete dos dez gateways', function () {
    $declaram = [
        new AsaasGateway('token', true, mockClient([])),
        new WooviGateway('app-id', true, wooviClient([])),
        new MercadoPagoGateway('TEST-token', mpClient([])),
        new PagBankGateway('token', true, pagbankClient([])),
        new PagarMeGateway('sk_test_abc', pagarmeClient([])),
        new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([])),
        redeGateway(),
    ];

    foreach ($declaram as $gateway) {
        expect(Capability::REFUNDS->supportedBy($gateway))->toBeTrue();
    }

    /*
     * os dois que ficam fora, cada um pelo seu motivo: a devolução Pix da Efí
     * exige o valor e é endereçada pelo endToEndId, não pela cobrança, e a v1
     * da AbacatePay não descreve reembolso do billing que a lib usa.
     */
    expect(Capability::REFUNDS->supportedBy(new EfiGateway('id', 'secret', true, mockClient([]))))->toBeFalse()
        ->and(Capability::REFUNDS->supportedBy(new AbacatePayGateway('token', abacateClient([]))))->toBeFalse();
})->group('phpay');

it('mantém o estorno Pix da Efí alcançável no gateway concreto', function () {
    $gateway = new EfiGateway('id', 'secret', true, mockClient([]));

    /*
     * fora da capacidade, mas não removido: é o que a API Pix da Efí oferece,
     * com a assinatura que o BACEN impõe — valor obrigatório e endToEndId.
     */
    expect(method_exists($gateway, 'pixCharge'))->toBeTrue()
        ->and(method_exists($gateway, 'refund'))->toBeFalse();
})->group('phpay');
