<?php

use PHPay\Exceptions\ValidationException;
use PHPay\PagBank\PagBankGateway;

it('aceita a notificação quando o digest bate com o corpo e o token', function () {
    $token   = 'token-da-conta';
    $corpo   = '{"id":"ORDE_1","charges":[{"status":"PAID"}]}';
    $digest  = hash('sha256', $token . '-' . $corpo);
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    expect($gateway->webhookSignature()
        ->setSecret($token)
        ->setPayload($corpo)
        ->setHeaders(['x-authenticity-token' => $digest])
        ->verify())->toBeTrue();
})->group('pagbank');

it('recusa quando o corpo foi alterado no caminho', function () {
    $token   = 'token-da-conta';
    $digest  = hash('sha256', $token . '-' . '{"status":"PENDING"}');
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    /* o atacante troca o status e repassa o digest do corpo original */
    expect($gateway->webhookSignature()
        ->setSecret($token)
        ->setPayload('{"status":"PAID"}')
        ->setHeaders(['x-authenticity-token' => $digest])
        ->verify())->toBeFalse();
})->group('pagbank');

it('aceita o digest em caixa alta', function () {
    $token   = 'token-da-conta';
    $corpo   = '{"id":"ORDE_1"}';
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    expect($gateway->webhookSignature()
        ->setSecret($token)
        ->setPayload($corpo)
        ->setHeaders(['x-authenticity-token' => strtoupper(hash('sha256', $token . '-' . $corpo))])
        ->verify())->toBeTrue();
})->group('pagbank');

it('exige o corpo cru antes de verificar', function () {
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    expect(fn () => $gateway->webhookSignature()->setSecret('token')->verify())
        ->toThrow(ValidationException::class, 'corpo cru');
})->group('pagbank');
