<?php

use PHPay\Webhooks\Headers;

it('acha o header em qualquer das grafias que um framework entrega', function (array $headers) {
    expect(Headers::find($headers, 'x-signature'))->toBe('ts=1,v1=abc');
})->with([
    'psr-7 em minúsculas' => [['x-signature' => 'ts=1,v1=abc']],
    'framework em caixa'  => [['X-Signature' => 'ts=1,v1=abc']],
    'lista do psr-7'      => [['x-signature' => ['ts=1,v1=abc']]],
    '$_SERVER'            => [['HTTP_X_SIGNATURE' => 'ts=1,v1=abc']],
    'misturado'           => [['Http_X_Signature' => 'ts=1,v1=abc']],
])->group('support');

it('devolve null quando o header não veio', function () {
    expect(Headers::find(['x-request-id' => 'abc'], 'x-signature'))->toBeNull()
        ->and(Headers::find([], 'x-signature'))->toBeNull();
})->group('support');

it('não confunde header de nome parecido', function () {
    expect(Headers::find(['x-signature-v2' => 'nope'], 'x-signature'))->toBeNull();
})->group('support');

it('aceita valor numérico, que alguns servidores entregam sem string', function () {
    expect(Headers::find(['x-timestamp' => 1700000000], 'x-timestamp'))->toBe('1700000000');
})->group('support');
