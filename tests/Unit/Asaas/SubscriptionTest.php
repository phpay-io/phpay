<?php

use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Exceptions\ValidationException;

it('cria a assinatura com o cliente informado por id', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'sub_001'])], $history);

    (new Subscription('token', true, $client))
        ->setCustomerId('cus_abc')
        ->create([
            'billingType' => 'BOLETO',
            'value'       => 100,
            'nextDueDate' => '2026-01-10',
            'cycle'       => 'MONTHLY',
        ]);

    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions')
        ->and(recordedBody($history)['customer'])->toBe('cus_abc')
        ->and(recordedBody($history)['cycle'])->toBe('MONTHLY');
})->group('asaas');

it('reaproveita o cliente quando o array traz um id', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'sub_001'])], $history);

    (new Subscription('token', true, $client))
        ->setCustomer(['id' => 'cus_abc'])
        ->create(['billingType' => 'PIX', 'value' => 10, 'nextDueDate' => '2026-01-10']);

    expect($history)->toHaveCount(1)
        ->and(recordedBody($history)['customer'])->toBe('cus_abc');
})->group('asaas');

it('exige um cliente antes de criar a assinatura', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Subscription('token', true, $client))
        ->create(['billingType' => 'BOLETO', 'value' => 100, 'nextDueDate' => '2026-01-10']))
        ->toThrow(ValidationException::class, 'O campo customer é obrigatório');

    expect($history)->toBeEmpty();
})->group('asaas');
