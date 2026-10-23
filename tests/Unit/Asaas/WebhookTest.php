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

it('lê, atualiza e remove o webhook pelos endpoints de webhooks', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['data' => [['id' => 'wh_1']]]),
        jsonResponse(['id' => 'wh_1', 'name' => 'PHPay']),
        jsonResponse(['id' => 'wh_1', 'enabled' => false]),
        jsonResponse(['deleted' => true]),
    ], $history);

    $webhook = new Webhook('token', [], true, $client);

    $webhook->getAll();
    $webhook->find('wh_1');
    $webhook->update('wh_1', ['enabled' => false]);

    expect($webhook->destroy('wh_1'))->toBeTrue();

    expect((string) $history[0]['request']->getUri())->toEndWith('/webhooks')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/webhooks/wh_1')
        ->and($history[2]['request']->getMethod())->toBe('PUT')
        ->and(recordedBody($history, 2))->toBe(['enabled' => false])
        ->and($history[3]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[3]['request']->getUri())->toEndWith('/webhooks/wh_1');
})->group('asaas');
