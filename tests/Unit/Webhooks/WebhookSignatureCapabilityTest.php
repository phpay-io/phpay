<?php

use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\{Capability, SupportsWebhookSignature};
use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\NotImplementedException;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PHPay;
use PHPay\Rede\RedeGateway;

it('não declara a capacidade onde não há assinatura para verificar', function (string $nome, callable $build) {
    $phpay = PHPay::gateway($build());

    expect($phpay->supports(Capability::WEBHOOK_SIGNATURE))->toBeFalse();

    expect(fn () => $phpay->webhookSignature())
        ->toThrow(NotImplementedException::class, $nome . ' não suporta assinatura de webhook');
})->with([
    /* a notificação Pix chega por mTLS, não por assinatura no corpo */
    'efi' => ['Efí', fn () => new EfiGateway('id', 'secret', true, mockClient([]))],
    /* Cielo e Rede mandam consultar a transação de volta na API */
    'cielo' => ['Cielo', fn () => new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([]))],
    'rede'  => ['Rede', fn () => new RedeGateway('pv', 'token')],
    /* a v5 da Pagar.me não documenta esquema de assinatura */
    'pagarme' => ['Pagar.me', fn () => new PagarMeGateway('sk_test_token')],
])->group('phpay');

it('a capacidade nova aponta para a interface que a declara', function () {
    expect(Capability::WEBHOOK_SIGNATURE->contract())
        ->toBe(SupportsWebhookSignature::class)
        ->and(Capability::WEBHOOK_SIGNATURE->label())->toBe('assinatura de webhook');
})->group('phpay');
