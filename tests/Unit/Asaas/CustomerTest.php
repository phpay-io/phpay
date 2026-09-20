<?php

use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Exceptions\{ApiException, ValidationException};

it('cria um cliente enviando o payload no corpo da requisição', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'cus_001', 'name' => 'Mário Lucas'])], $history);

    $result = (new Customer('token', [
        'name'    => 'Mário Lucas',
        'cpfCnpj' => '12345678901',
    ], true, $client))->create();

    expect($result['id'])->toBe('cus_001')
        ->and($history)->toHaveCount(1)
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/customers')
        ->and(recordedBody($history))->toBe([
            'name'    => 'Mário Lucas',
            'cpfCnpj' => '12345678901',
        ]);
})->group('asaas');

it('envia os filtros como query string ao listar clientes', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => []])], $history);

    (new Customer('token', [], true, $client))
        ->setFilter(['cpfCnpj' => '12345678901', 'limit' => 10])
        ->getAll();

    expect((string) $history[0]['request']->getUri())
        ->toContain('cpfCnpj=12345678901')
        ->toContain('limit=10');
})->group('asaas');

it('retorna true ao remover um cliente', function () {
    $client = mockClient([new GuzzleHttp\Psr7\Response(200)]);

    expect((new Customer('token', [], true, $client))->destroy('cus_001'))->toBeTrue();
})->group('asaas');

it('lança ValidationException sem chamar a API quando falta cpfCnpj', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('token', ['name' => 'Mário'], true, $client))->create())
        ->toThrow(ValidationException::class);

    expect($history)->toBeEmpty();
})->group('asaas');

it('traduz um erro do gateway em ApiException com status e corpo', function () {
    $client = mockClient([
        jsonResponse([
            'errors' => [
                ['code' => 'invalid_cpfCnpj', 'description' => 'CPF ou CNPJ inválido'],
            ],
        ], 400),
    ]);

    $customer = new Customer('token', ['name' => 'Mário', 'cpfCnpj' => '000'], true, $client);

    try {
        $customer->create();
        $this->fail('ApiException não foi lançada');
    } catch (ApiException $exception) {
        expect($exception->getStatusCode())->toBe(400)
            ->and($exception->getGateway())->toBe('Asaas')
            ->and($exception->getMessage())->toContain('CPF ou CNPJ inválido')
            ->and($exception->isConnectionError())->toBeFalse()
            ->and($exception->getResponse())->toHaveKey('errors');
    }
})->group('asaas');
