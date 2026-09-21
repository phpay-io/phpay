<?php

use PHPay\Asaas\Resources\Webhook\Enum\WebhookEventsEnum;
use PHPay\Asaas\Resources\Webhook\Webhook;

it('usa o payload passado no construtor', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'whk_001'])], $history);

    (new Webhook('token', ['name' => 'construtor', 'url' => 'https://exemplo.test'], true, $client))->create();

    expect(recordedBody($history)['name'])->toBe('construtor');
})->group('asaas');

it('respeita o payload passado direto no create', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'whk_001'])], $history);

    (new Webhook('token', ['name' => 'construtor'], true, $client))
        ->create(['name' => 'argumento', 'url' => 'https://exemplo.test']);

    expect(recordedBody($history)['name'])->toBe('argumento');
})->group('asaas');

it('expõe os eventos de webhook do asaas', function () {
    expect(WebhookEventsEnum::tryFrom('PAYMENT_RECEIVED'))
        ->toBe(WebhookEventsEnum::PAYMENT_RECEIVED)
        ->and(WebhookEventsEnum::tryFrom('EVENTO_INEXISTENTE'))->toBeNull();
})->group('asaas');
