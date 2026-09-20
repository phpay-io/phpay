<?php

use PHPay\Exceptions\ValidationException;
use PHPay\MercadoPago\Resources\Subscription\Subscription;

it('cria uma assinatura sem plano associado', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 'sub_1', 'status' => 'pending'])], $history);

    (new Subscription('TEST-token', $client))
        ->setPayerEmail('fale@phpay.io')
        ->create([
            'reason'         => 'Assinatura PHPay',
            'back_url'       => 'https://exemplo.test/retorno',
            'auto_recurring' => [
                'frequency'          => 1,
                'frequency_type'     => 'months',
                'transaction_amount' => 100.00,
                'currency_id'        => 'BRL',
            ],
        ]);

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/preapproval')
        ->and($body['payer_email'])->toBe('fale@phpay.io')
        ->and($body['auto_recurring']['frequency_type'])->toBe('months');
})->group('mercadopago');

it('dispensa auto_recurring quando há plano associado', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 'sub_1'])], $history);

    (new Subscription('TEST-token', $client))
        ->setPayerEmail('fale@phpay.io')
        ->setPlan('plan_123')
        ->create(['back_url' => 'https://exemplo.test/retorno']);

    expect(recordedBody($history)['preapproval_plan_id'])->toBe('plan_123');
})->group('mercadopago');

it('pausa e cancela pelo status', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 1])], $history);

    $subscription = new Subscription('TEST-token', $client);
    $subscription->pause('sub_1');
    $subscription->cancel('sub_1');

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and(recordedBody($history, 0))->toBe(['status' => 'paused'])
        ->and(recordedBody($history, 1))->toBe(['status' => 'cancelled']);
})->group('mercadopago');

it('valida a assinatura antes de chamar a API', function (array $subscription, string $esperado) {
    $history = [];
    $client  = mpClient([jsonResponse([])], $history);

    expect(fn () => (new Subscription('TEST-token', $client))->create($subscription))
        ->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'sem e-mail' => [
        ['reason' => 'x', 'back_url' => 'https://exemplo.test'],
        'payer_email',
    ],
    'back_url inválida' => [
        ['payer_email' => 'a@b.com', 'reason' => 'x', 'back_url' => 'nao-e-url'],
        'back_url',
    ],
    'sem auto_recurring' => [
        ['payer_email' => 'a@b.com', 'reason' => 'x', 'back_url' => 'https://exemplo.test'],
        'auto_recurring',
    ],
    'frequency_type fora do enum' => [
        [
            'payer_email'    => 'a@b.com',
            'reason'         => 'x',
            'back_url'       => 'https://exemplo.test',
            'auto_recurring' => [
                'frequency'          => 1, 'frequency_type' => 'weeks',
                'transaction_amount' => 10, 'currency_id' => 'BRL',
            ],
        ],
        'frequency_type',
    ],
])->group('mercadopago');
