<?php

use PHPay\Exceptions\ValidationException;
use PHPay\PagBank\Enums\IntervalUnitEnum;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;

/**
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @return GuzzleHttp\Client
 */
function assinaturasClient(array $responses, array &$history = []): GuzzleHttp\Client
{
    return mockClient($responses, $history, 'https://sandbox.api.assinaturas.pagseguro.com/');
}

it('cria um plano com valor em centavos', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse(['id' => 'PLAN_1'])], $history);

    (new Subscription('token', true, $client))->createPlan([
        'name'     => 'Plano PHPay',
        'amount'   => ['value' => 4990, 'currency' => 'BRL'],
        'interval' => ['unit' => IntervalUnitEnum::MONTHS->value, 'length' => 1],
    ]);

    expect((string) $history[0]['request']->getUri())->toEndWith('/plans')
        ->and(recordedBody($history)['amount']['value'])->toBe(4990);
})->group('pagbank');

it('cria a assinatura com plano e assinante existente', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse(['id' => 'SUBS_1'])], $history);

    (new Subscription('token', true, $client))
        ->setPlan('PLAN_1')
        ->setCustomerId('CUST_1')
        ->create();

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/subscriptions')
        ->and($body['plan'])->toBe(['id' => 'PLAN_1'])
        ->and($body['customer'])->toBe(['id' => 'CUST_1']);
})->group('pagbank');

it('cria o assinante junto com a assinatura', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse(['id' => 'SUBS_1'])], $history);

    (new Subscription('token', true, $client))
        ->setPlan('PLAN_1')
        ->setCustomer([
            'name'   => 'Mário Lucas',
            'email'  => 'fale@phpay.io',
            'tax_id' => '12345678901',
        ])
        ->create();

    /* uma única requisição: o pagbank aceita o assinante embutido */
    expect($history)->toHaveCount(1)
        ->and(recordedBody($history)['customer']['email'])->toBe('fale@phpay.io');
})->group('pagbank');

it('suspende, reativa e cancela pela rota de cada ação', function () {
    $history = [];
    $client  = assinaturasClient([
        jsonResponse(['id' => 1]), jsonResponse(['id' => 1]), jsonResponse(['id' => 1]),
    ], $history);

    $subscription = new Subscription('token', true, $client);
    $subscription->suspend('SUBS_1');
    $subscription->activate('SUBS_1');
    $subscription->cancel('SUBS_1');

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/SUBS_1/suspend')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/subscriptions/SUBS_1/activate')
        ->and((string) $history[2]['request']->getUri())->toEndWith('/subscriptions/SUBS_1/cancel');
})->group('pagbank');

it('valida plano e assinatura antes de chamar a API', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse([])], $history);

    $subscription = new Subscription('token', true, $client);

    expect(fn () => $subscription->create())
        ->toThrow(ValidationException::class, 'precisa de um plano');

    expect(fn () => (new Subscription('token', true, $client))->setPlan('PLAN_1')->create())
        ->toThrow(ValidationException::class, 'precisa de um assinante');

    expect(fn () => $subscription->createPlan([
        'name'     => 'Plano',
        'amount'   => ['value' => 49.90],
        'interval' => ['unit' => 'MONTHS', 'length' => 1],
    ]))->toThrow(ValidationException::class, 'CENTAVOS');

    expect(fn () => $subscription->createPlan([
        'name'     => 'Plano',
        'amount'   => ['value' => 4990],
        'interval' => ['unit' => 'WEEKS', 'length' => 1],
    ]))->toThrow(ValidationException::class, 'DAYS, MONTHS, YEARS');

    expect($history)->toBeEmpty();
})->group('pagbank');

it('cria e lista assinantes no host de assinaturas', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse(['id' => 'CUST_1']), jsonResponse(['data' => []])], $history);

    (new Customer('token', [
        'name'   => 'Mário Lucas',
        'email'  => 'fale@phpay.io',
        'tax_id' => '12345678901',
    ], true, $client))->create();

    (new Customer('token', [], true, $client))->setFilter(['offset' => 0, 'limit' => 10])->getAll();

    expect((string) $history[0]['request']->getUri())->toEndWith('/customers')
        ->and((string) $history[1]['request']->getUri())->toContain('limit=10');
})->group('pagbank');

it('exige e-mail e documento válidos do assinante', function () {
    $history = [];
    $client  = assinaturasClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('token', ['name' => 'X', 'email' => 'nao-e-email', 'tax_id' => '12345678901'], true, $client))->create())
        ->toThrow(ValidationException::class, 'e-mail válido');

    expect(fn () => (new Customer('token', ['name' => 'X', 'email' => 'a@b.com', 'tax_id' => '123'], true, $client))->create())
        ->toThrow(ValidationException::class, 'tax_id');

    expect($history)->toBeEmpty();
})->group('pagbank');
