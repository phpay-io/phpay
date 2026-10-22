<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Exceptions\ValidationException;

it('aceita quando o secret da url bate', function () {
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect($gateway->webhookSignature()
        ->setSecret('meu-secret')
        ->setQuery(['webhookSecret' => 'meu-secret'])
        ->verify())->toBeTrue();
})->group('abacatepay');

it('recusa quando o secret da url não bate ou não veio', function (array $query) {
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect($gateway->webhookSignature()
        ->setSecret('meu-secret')
        ->setQuery($query)
        ->verify())->toBeFalse();
})->with([
    'outro secret' => [['webhookSecret' => 'outro']],
    'sem o param'  => [[]],
    'param vazio'  => [['webhookSecret' => '']],
])->group('abacatepay');

it('também confere a assinatura do corpo quando a chave é informada', function () {
    $corpo   = '{"event":"billing.paid"}';
    $chave   = 'chave-de-assinatura';
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect($gateway->webhookSignature()
        ->setSecret('meu-secret')
        ->setQuery(['webhookSecret' => 'meu-secret'])
        ->setPayload($corpo)
        ->setSignatureKey($chave)
        ->setHeaders([
            'x-webhook-signature' => base64_encode(hash_hmac('sha256', $corpo, $chave, true)),
        ])
        ->verify())->toBeTrue();
})->group('abacatepay');

it('recusa corpo adulterado mesmo com o secret da url certo', function () {
    $chave   = 'chave-de-assinatura';
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect($gateway->webhookSignature()
        ->setSecret('meu-secret')
        ->setQuery(['webhookSecret' => 'meu-secret'])
        ->setPayload('{"event":"billing.paid"}')
        ->setSignatureKey($chave)
        ->setHeaders([
            'x-webhook-signature' => base64_encode(hash_hmac('sha256', '{"event":"billing.pending"}', $chave, true)),
        ])
        ->verify())->toBeFalse();
})->group('abacatepay');

it('cobra o header de assinatura quando a chave foi informada', function () {
    $gateway = new AbacatePayGateway('token', abacateClient([]));

    expect(fn () => $gateway->webhookSignature()
        ->setSecret('meu-secret')
        ->setQuery(['webhookSecret' => 'meu-secret'])
        ->setPayload('{}')
        ->setSignatureKey('chave')
        ->setHeaders([])
        ->verify())
        ->toThrow(ValidationException::class, 'x-webhook-signature');
})->group('abacatepay');
