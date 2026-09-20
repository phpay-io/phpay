<?php

use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Exceptions\ValidationException;

it('reaproveita o cliente quando o array traz um id, sem criar outro', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001'])], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'BOLETO', 'value' => 100, 'dueDate' => '2026-01-10'])
        ->setCustomer(['id' => 'cus_existente', 'name' => 'Mário'])
        ->create();

    /* uma única requisição: a cobrança. nenhum cliente duplicado foi criado */
    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments')
        ->and(recordedBody($history)['customer'])->toBe('cus_existente');
})->group('asaas');

it('aceita um customerId já existente', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001'])], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'PIX', 'value' => 50, 'dueDate' => '2026-01-10'])
        ->setCustomerId('cus_abc')
        ->create();

    expect($history)->toHaveCount(1)
        ->and(recordedBody($history)['customer'])->toBe('cus_abc');
})->group('asaas');

it('cria o cliente antes da cobrança quando não há id', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['id' => 'cus_novo']),
        jsonResponse(['id' => 'pay_001']),
    ], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'BOLETO', 'value' => 100, 'dueDate' => '2026-01-10'])
        ->setCustomer(['name' => 'Mário', 'cpfCnpj' => '12345678901'])
        ->create();

    expect($history)->toHaveCount(2)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/customers')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments')
        ->and(recordedBody($history, 1)['customer'])->toBe('cus_novo');
})->group('asaas');

it('devolve null quando a cobrança não tem linha digitável', function () {
    $client = mockClient([jsonResponse([])]);

    expect((new Charge('token', true, $client))->getDigitableLine('pay_001'))->toBeNull();
})->group('asaas');

it('devolve a linha digitável quando presente', function () {
    $client = mockClient([jsonResponse(['identificationField' => '00190000090'])]);

    expect((new Charge('token', true, $client))->getDigitableLine('pay_001'))->toBe('00190000090');
})->group('asaas');

it('rejeita billingType inválido antes de chamar a API', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Charge('token', true, $client))
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'CHEQUE', 'value' => 10, 'dueDate' => '2026-01-10'])
        ->create())
        ->toThrow(ValidationException::class);

    expect($history)->toBeEmpty();
})->group('asaas');
