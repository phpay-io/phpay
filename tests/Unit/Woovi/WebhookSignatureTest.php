<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Woovi\WooviGateway;

/**
 * par de chaves RSA e a assinatura do corpo, como a Woovi mandaria.
 *
 * @param string $payload
 * @return array{0: string, 1: string} chave pública PEM e assinatura base64
 */
function wooviSigned(string $payload): array
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    $details = openssl_pkey_get_details($key);
    openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);

    return [$details['key'], base64_encode($signature)];
}

it('aceita a notificação assinada pela chave da woovi', function () {
    $corpo              = '{"event":"OPENPIX:CHARGE_COMPLETED"}';
    [$pem, $assinatura] = wooviSigned($corpo);
    $gateway            = new WooviGateway('app-id', true, wooviClient([]));

    expect($gateway->webhookSignature()
        ->setPublicKeys([$pem])
        ->setPayload($corpo)
        ->setHeaders(['x-webhook-signature' => $assinatura])
        ->verify())->toBeTrue();
})->group('woovi');

it('recusa quando o corpo não é o que foi assinado', function () {
    [$pem, $assinatura] = wooviSigned('{"event":"OPENPIX:CHARGE_EXPIRED"}');
    $gateway            = new WooviGateway('app-id', true, wooviClient([]));

    expect($gateway->webhookSignature()
        ->setPublicKeys([$pem])
        ->setPayload('{"event":"OPENPIX:CHARGE_COMPLETED"}')
        ->setHeaders(['x-webhook-signature' => $assinatura])
        ->verify())->toBeFalse();
})->group('woovi');

it('busca a lista publicada de chaves quando nenhuma foi informada', function () {
    $corpo              = '{"event":"OPENPIX:CHARGE_COMPLETED"}';
    [$pem, $assinatura] = wooviSigned($corpo);
    $history            = [];

    $gateway = new WooviGateway('app-id', true, wooviClient([
        jsonResponse(['public_keys' => [['key_identifier' => 'abc', 'is_current' => true, 'key' => $pem]]]),
    ], $history));

    expect($gateway->webhookSignature()
        ->setPayload($corpo)
        ->setHeaders(['x-webhook-signature' => $assinatura])
        ->verify())->toBeTrue();

    expect((string) $history[0]['request']->getUri())
        ->toEndWith('api/v1/webhook/public-keys');
})->group('woovi');

it('aceita a chave anterior durante uma rotação', function () {
    $corpo                       = '{"event":"OPENPIX:CHARGE_COMPLETED"}';
    [$pemAntigo]                 = wooviSigned('outro corpo qualquer');
    [$pemAtual, $assinaturaNova] = wooviSigned($corpo);
    $gateway                     = new WooviGateway('app-id', true, wooviClient([
        jsonResponse(['public_keys' => [
            ['key' => $pemAntigo, 'is_current' => false],
            ['key' => $pemAtual, 'is_current' => true],
        ]]),
    ]));

    /* a lista traz as duas e a validação não pode parar na primeira */
    expect($gateway->webhookSignature()
        ->setPayload($corpo)
        ->setHeaders(['x-webhook-signature' => $assinaturaNova])
        ->verify())->toBeTrue();
})->group('woovi');

it('cai no esquema hmac depreciado quando não vem assinatura rsa', function () {
    $corpo   = '{"event":"OPENPIX:CHARGE_COMPLETED"}';
    $secret  = 'hmac-secret-do-webhook';
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    expect($gateway->webhookSignature()
        ->setSecret($secret)
        ->setPayload($corpo)
        ->setHeaders(['x-openpix-signature' => base64_encode(hash_hmac('sha1', $corpo, $secret, true))])
        ->verify())->toBeTrue();
})->group('woovi');

it('não verifica nada sem assinatura nem segredo', function () {
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    expect(fn () => $gateway->webhookSignature()
        ->setPayload('{"event":"x"}')
        ->setHeaders([])
        ->verify())
        ->toThrow(ValidationException::class, 'x-webhook-signature');
})->group('woovi');

it('exige o corpo cru antes de verificar', function () {
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    expect(fn () => $gateway->webhookSignature()->setHeaders([])->verify())
        ->toThrow(ValidationException::class, 'corpo cru');
})->group('woovi');
