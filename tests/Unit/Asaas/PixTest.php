<?php

use PHPay\Asaas\Resources\Pix\Pix;

it('cria uma chave pix do tipo EVP', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'key_001', 'key' => 'abc'])], $history);

    (new Pix('token', true, $client))->createKey();

    expect((string) $history[0]['request']->getUri())->toEndWith('/pix/addressKeys')
        ->and(recordedBody($history))->toBe(['type' => 'EVP']);
})->group('asaas');

it('aplica paginação padrão ao listar chaves', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => []])], $history);

    (new Pix('token', true, $client))->getAll();

    expect((string) $history[0]['request']->getUri())
        ->toContain('offset=0')
        ->toContain('limit=100');
})->group('asaas');

it('respeita os query params informados', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => []])], $history);

    (new Pix('token', true, $client))->setQueryParams(['limit' => 5])->getAll();

    expect((string) $history[0]['request']->getUri())
        ->toContain('limit=5')
        ->not->toContain('offset=0');
})->group('asaas');
