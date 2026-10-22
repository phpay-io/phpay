<?php

use PHPay\Exceptions\ValidationException;
use PHPay\MercadoPago\MercadoPagoGateway;

/**
 * assinatura que o Mercado Pago mandaria para esses valores.
 *
 * @param string $manifest
 * @param string $secret
 * @param string $ts
 * @return string
 */
function mpSignature(string $manifest, string $secret, string $ts): string
{
    return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', $manifest, $secret);
}

it('aceita a notificação quando o hash bate com o manifesto', function () {
    $ts       = '1700000000';
    $secret   = 'segredo-da-aplicacao';
    $manifest = 'id:1234567890;request-id:req-abc;ts:' . $ts . ';';
    $gateway  = new MercadoPagoGateway('TEST-token', mpClient([]));

    expect($gateway->webhookSignature()
        ->setSecret($secret)
        ->setDataId('1234567890')
        ->setHeaders([
            'x-signature'  => mpSignature($manifest, $secret, $ts),
            'x-request-id' => 'req-abc',
        ])
        ->verify())->toBeTrue();
})->group('mercadopago');

it('omite do manifesto o segmento que a notificação não traz', function () {
    $ts       = '1700000000';
    $secret   = 'segredo-da-aplicacao';
    $manifest = 'ts:' . $ts . ';';
    $gateway  = new MercadoPagoGateway('TEST-token', mpClient([]));

    /* sem data.id na query e sem x-request-id: o manifesto é só o ts */
    expect($gateway->webhookSignature()
        ->setSecret($secret)
        ->setHeaders(['x-signature' => mpSignature($manifest, $secret, $ts)])
        ->verify())->toBeTrue();
})->group('mercadopago');

it('recusa quando o segredo é outro', function () {
    $ts       = '1700000000';
    $manifest = 'id:1;ts:' . $ts . ';';
    $gateway  = new MercadoPagoGateway('TEST-token', mpClient([]));

    expect($gateway->webhookSignature()
        ->setSecret('segredo-errado')
        ->setDataId('1')
        ->setHeaders(['x-signature' => mpSignature($manifest, 'segredo-certo', $ts)])
        ->verify())->toBeFalse();
})->group('mercadopago');

it('recusa quando o atacante troca o data.id mantendo o hash', function () {
    $ts       = '1700000000';
    $secret   = 'segredo-da-aplicacao';
    $manifest = 'id:1;ts:' . $ts . ';';
    $gateway  = new MercadoPagoGateway('TEST-token', mpClient([]));

    expect($gateway->webhookSignature()
        ->setSecret($secret)
        ->setDataId('2')
        ->setHeaders(['x-signature' => mpSignature($manifest, $secret, $ts)])
        ->verify())->toBeFalse();
})->group('mercadopago');

it('reprova header fora do formato ts=,v1=', function (string $header) {
    $gateway = new MercadoPagoGateway('TEST-token', mpClient([]));

    expect(fn () => $gateway->webhookSignature()
        ->setSecret('segredo')
        ->setHeaders(['x-signature' => $header])
        ->verify())
        ->toThrow(ValidationException::class, 'x-signature');
})->with([
    'sem v1'          => ['ts=1700000000'],
    'sem ts'          => ['v1=abc'],
    'ts não numérico' => ['ts=agora,v1=abc'],
    'vazio'           => [''],
])->group('mercadopago');
