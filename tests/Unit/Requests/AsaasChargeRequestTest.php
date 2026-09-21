<?php

use PHPay\Asaas\Requests\{AsaasChargeRequest, AsaasCustomerRequest};
use PHPay\Asaas\Resources\Subscription\Requests\StoreSubscriptionAsaasRequest;
use PHPay\Exceptions\ValidationException;

/*
| Regressão: a validação usava `!isset($x) && !is_string($x)`. Com `&&`, um campo
| presente com o tipo errado passava batido — e um campo ausente ainda emitia
| "Undefined array key" antes de lançar.
*/

it('aceita uma cobrança completa e válida', function () {
    expect(fn () => AsaasChargeRequest::validate([
        'customer'    => 'cus_001',
        'billingType' => 'BOLETO',
        'value'       => 100.50,
        'dueDate'     => '2026-01-10',
    ]))->not->toThrow(ValidationException::class);
})->group('asaas');

it('rejeita campo presente com o tipo errado', function (array $charge, string $esperado) {
    expect(fn () => AsaasChargeRequest::validate($charge))
        ->toThrow(ValidationException::class, $esperado);
})->with([
    'customer numérico' => [
        ['customer' => 12345, 'billingType' => 'BOLETO', 'value' => 100, 'dueDate' => '2026-01-10'],
        'O campo customer é obrigatório',
    ],
    'value não numérico' => [
        ['customer' => 'cus_1', 'billingType' => 'BOLETO', 'value' => 'abc', 'dueDate' => '2026-01-10'],
        'O campo value é obrigatório',
    ],
    'value zerado' => [
        ['customer' => 'cus_1', 'billingType' => 'BOLETO', 'value' => 0, 'dueDate' => '2026-01-10'],
        'O campo value é obrigatório',
    ],
    'dueDate inteiro' => [
        ['customer' => 'cus_1', 'billingType' => 'BOLETO', 'value' => 100, 'dueDate' => 20260110],
        'O campo dueDate é obrigatório',
    ],
    'billingType fora do enum' => [
        ['customer' => 'cus_1', 'billingType' => 'CHEQUE', 'value' => 100, 'dueDate' => '2026-01-10'],
        'O campo billingType é obrigatório',
    ],
])->group('asaas');

it('rejeita campo ausente sem emitir warning de chave indefinida', function () {
    set_error_handler(function (int $severity, string $message): bool {
        throw new ErrorException($message, 0, $severity);
    });

    try {
        expect(fn () => AsaasChargeRequest::validate([]))
            ->toThrow(ValidationException::class);
    } finally {
        restore_error_handler();
    }
})->group('asaas');

it('valida o cliente do asaas', function () {
    expect(fn () => AsaasCustomerRequest::validate(['name' => 'Mário', 'cpfCnpj' => '12345678901']))
        ->not->toThrow(ValidationException::class);

    expect(fn () => AsaasCustomerRequest::validate(['name' => '  ', 'cpfCnpj' => '12345678901']))
        ->toThrow(ValidationException::class);

    expect(fn () => AsaasCustomerRequest::validate(['name' => 'Mário', 'cpfCnpj' => 12345678901]))
        ->toThrow(ValidationException::class);
})->group('asaas');

it('valida a assinatura do asaas', function () {
    expect(fn () => StoreSubscriptionAsaasRequest::validate([
        'customer'    => 'cus_001',
        'billingType' => 'BOLETO',
        'value'       => 100,
        'nextDueDate' => '2026-01-10',
        'cycle'       => 'MONTHLY',
    ]))->not->toThrow(ValidationException::class);

    expect(fn () => StoreSubscriptionAsaasRequest::validate([
        'customer'    => 'cus_001',
        'billingType' => 'BOLETO',
        'value'       => 100,
        'nextDueDate' => 20260110,
        'cycle'       => 'MONTHLY',
    ]))->toThrow(ValidationException::class);

    /* sem cycle o Asaas recusa — a validação barra antes */
    expect(fn () => StoreSubscriptionAsaasRequest::validate([
        'customer'    => 'cus_001',
        'billingType' => 'BOLETO',
        'value'       => 100,
        'nextDueDate' => '2026-01-10',
    ]))->toThrow(ValidationException::class, 'O campo cycle é obrigatório');
})->group('asaas');

it('prefixa toda mensagem de validação com o gateway', function () {
    expect(fn () => AsaasCustomerRequest::validate([]))
        ->toThrow(ValidationException::class, 'Asaas: ');
})->group('asaas');
