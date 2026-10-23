<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Woovi\Resources\Customer\Customer;

it('cria o cliente no endpoint de customer', function () {
    $history = [];
    $client  = wooviClient([jsonResponse(['customer' => ['correlationID' => 'cli-1']])], $history);

    (new Customer('app-id', [
        'name'  => 'Mário Lucas',
        'email' => 'fale@phpay.io',
        'taxID' => '12345678901',
    ], true, $client))->create();

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/api/v1/customer')
        ->and(recordedBody($history, 0)['name'])->toBe('Mário Lucas')
        ->and(recordedBody($history, 0)['taxID'])->toBe('12345678901');
})->group('woovi');

it('valida o payload antes de chamar a API', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('app-id', ['name' => 'Mário'], true, $client))->create())
        ->toThrow(ValidationException::class);

    expect($history)->toBeEmpty();
})->group('woovi');

it('endereça o cliente pelo correlationID ou pelo id do gateway', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([]), jsonResponse([])], $history);

    $customer = new Customer('app-id', [], true, $client);

    $customer->find('cli-1');
    $customer->find('6f1a2b3c4d5e');

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/customer/cli-1')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/api/v1/customer/6f1a2b3c4d5e');
})->group('woovi');

it('lista com e sem filtro de query', function () {
    $history = [];
    $client  = wooviClient([jsonResponse(['customers' => []]), jsonResponse(['customers' => []])], $history);

    $customer = new Customer('app-id', [], true, $client);

    $customer->getAll();
    $customer->setQueryParams(['limit' => 10, 'skip' => 20])->getAll();

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/customer')
        ->and((string) $history[1]['request']->getUri())->toContain('limit=10')
        ->and((string) $history[1]['request']->getUri())->toContain('skip=20');
})->group('woovi');
