<?php

use PHPay\AbacatePay\Resources\Customer\Customer;
use PHPay\Exceptions\ValidationException;

it('cria o cliente no endpoint de customer', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => ['id' => 'cust_1']])], $history);

    (new Customer('token', [
        'name'      => 'Mário Lucas',
        'email'     => 'fale@phpay.io',
        'cellphone' => '11999999999',
        'taxId'     => '12345678901',
    ], $client))->create();

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/customer/create')
        ->and(recordedBody($history, 0)['taxId'])->toBe('12345678901');
})->group('abacatepay');

it('valida o payload antes de chamar a API', function () {
    $history = [];
    $client  = abacateClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('token', ['name' => 'Mário'], $client))->create())
        ->toThrow(ValidationException::class);

    expect($history)->toBeEmpty();
})->group('abacatepay');

it('lista clientes com e sem filtro', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => []]), jsonResponse(['data' => []])], $history);

    $customer = new Customer('token', [], $client);

    $customer->getAll();
    $customer->setQueryParams(['limit' => 5])->getAll();

    expect((string) $history[0]['request']->getUri())->toEndWith('/customer/list')
        ->and((string) $history[1]['request']->getUri())->toContain('limit=5');
})->group('abacatepay');
