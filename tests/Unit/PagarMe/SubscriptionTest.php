<?php

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Enums\{IntervalEnum, PaymentMethodEnum};
use PHPay\PagarMe\Resources\Customer\Customer;
use PHPay\PagarMe\Resources\Subscription\Subscription;

it('cria um plano com preço em centavos', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'plan_1'])], $history);

    (new Subscription('sk_test_abc', $client))->createPlan([
        'name'           => 'Plano PHPay',
        'interval'       => IntervalEnum::MONTH->value,
        'interval_count' => 1,
        'items'          => [[
            'name'           => 'Mensalidade',
            'quantity'       => 1,
            'pricing_scheme' => ['price' => 4990],
        ]],
    ]);

    expect((string) $history[0]['request']->getUri())->toEndWith('/plans')
        ->and(recordedBody($history)['items'][0]['pricing_scheme']['price'])->toBe(4990);
})->group('pagarme');

it('cria a assinatura com plano e cliente existentes', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'sub_1'])], $history);

    (new Subscription('sk_test_abc', $client))
        ->setPlan('plan_1')
        ->setCustomerId('cus_1')
        ->create(['payment_method' => PaymentMethodEnum::CREDIT_CARD->value]);

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/subscriptions')
        ->and($body['plan_id'])->toBe('plan_1')
        ->and($body['customer_id'])->toBe('cus_1')
        ->and($body['payment_method'])->toBe('credit_card');
})->group('pagarme');

it('aceita assinatura sem plano quando a recorrência vem no payload', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'sub_1'])], $history);

    (new Subscription('sk_test_abc', $client))
        ->setCustomerId('cus_1')
        ->create([
            'payment_method' => PaymentMethodEnum::PIX->value,
            'interval'       => IntervalEnum::MONTH->value,
            'interval_count' => 1,
            'items'          => [[
                'name'           => 'Mensalidade',
                'quantity'       => 1,
                'pricing_scheme' => ['price' => 4990],
            ]],
        ]);

    expect(recordedBody($history))->not->toHaveKey('plan_id');
})->group('pagarme');

it('cancela assinatura e plano por DELETE', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 1])], $history);

    $subscription = new Subscription('sk_test_abc', $client);
    $subscription->cancel('sub_1');
    $subscription->destroyPlan('plan_1');

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_1')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/plans/plan_1');
})->group('pagarme');

it('valida plano e assinatura antes de chamar a API', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse([])], $history);

    $subscription = new Subscription('sk_test_abc', $client);

    expect(fn () => $subscription->create(['payment_method' => 'pix']))
        ->toThrow(ValidationException::class, 'plan_id ou de items próprios');

    expect(fn () => (new Subscription('sk_test_abc', $client))
        ->setPlan('plan_1')
        ->create())
        ->toThrow(ValidationException::class, 'customer_id ou de um customer completo');

    expect(fn () => (new Subscription('sk_test_abc', $client))
        ->setPlan('plan_1')
        ->setCustomerId('cus_1')
        ->create(['payment_method' => 'cheque']))
        ->toThrow(ValidationException::class, 'credit_card, debit_card, boleto, pix');

    expect(fn () => $subscription->createPlan([
        'name'           => 'Plano',
        'interval'       => 'quinzena',
        'interval_count' => 1,
        'items'          => [['pricing_scheme' => ['price' => 4990]]],
    ]))->toThrow(ValidationException::class, 'day, week, month, year');

    expect(fn () => $subscription->createPlan([
        'name'           => 'Plano',
        'interval'       => 'month',
        'interval_count' => 1,
        'items'          => [['pricing_scheme' => ['price' => 49.90]]],
    ]))->toThrow(ValidationException::class, 'CENTAVOS');

    expect($history)->toBeEmpty();
})->group('pagarme');

it('faz o crud completo de clientes, com cartões salvos', function () {
    $history = [];
    $client  = pagarmeClient([
        jsonResponse(['id' => 'cus_1']), jsonResponse(['id' => 'cus_1']),
        jsonResponse(['data' => []]), jsonResponse(['data'  => []]),
    ], $history);

    (new Customer('sk_test_abc', [
        'name'     => 'Mário Lucas',
        'email'    => 'fale@phpay.io',
        'document' => '12345678901',
    ], $client))->create();

    $customer = new Customer('sk_test_abc', ['name' => 'Novo Nome'], $client);
    $customer->update('cus_1');
    $customer->setFilter(['size' => 10])->getAll();
    $customer->cards('cus_1');

    expect((string) $history[0]['request']->getUri())->toEndWith('/customers')
        ->and($history[1]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[2]['request']->getUri())->toContain('size=10')
        ->and((string) $history[3]['request']->getUri())->toEndWith('/customers/cus_1/cards');
})->group('pagarme');
