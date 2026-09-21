<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Efi\Resources\Webhook\Webhook;
use PHPay\Exceptions\ValidationException;
use PHPay\PHPay;

/**
 * webhook resource on a mocked client.
 *
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @param array<mixed> $webhook
 * @return Webhook
 */
function pixWebhook(array $responses, array &$history = [], array $webhook = []): Webhook
{
    return new Webhook(['access_token' => 'tok', 'token_type' => 'Bearer'], $webhook, null, true, efiPixClient($responses, $history));
}

it('configura o webhook pela chave Pix, via facade', function () {
    $history = [];

    PHPay::gateway(efiPixGateway([jsonResponse(['webhookUrl' => 'https://loja.com/webhook'])], $history))
        ->webhook(['chave' => 'loja@phpay.io', 'webhookUrl' => 'https://loja.com/webhook'])
        ->create();

    $request = $history[1]['request'];

    expect($request->getMethod())->toBe('PUT')
        ->and((string) $request->getUri())->toBe('https://pix-h.api.efipay.com.br/v2/webhook/loja%40phpay.io')
        ->and(recordedBody($history, 1))->toBe(['webhookUrl' => 'https://loja.com/webhook'])
        ->and($request->hasHeader('x-skip-mtls-checking'))->toBeFalse();
})->group('efi');

it('pede para a Efí pular o mTLS no servidor quando solicitado', function () {
    $history = [];

    pixWebhook([jsonResponse([])], $history)
        ->skipMtlsChecking()
        ->create(['chave' => 'chave', 'webhookUrl' => 'https://loja.com/webhook?hmac=xyz']);

    expect($history[0]['request']->getHeaderLine('x-skip-mtls-checking'))->toBe('true');
})->group('efi');

it('recusa webhook sem chave ou fora de https, sem chamar a API', function (array $webhook, string $message) {
    $history = [];

    expect(fn () => pixWebhook([], $history, $webhook)->create())
        ->toThrow(ValidationException::class, $message);

    expect($history)->toBeEmpty();
})->with([
    'sem chave' => [['webhookUrl' => 'https://loja.com/webhook'], 'por chave Pix'],
    'url ruim'  => [['chave' => 'chave', 'webhookUrl' => 'não é url'], 'URL válida'],
    'sem https' => [['chave' => 'chave', 'webhookUrl' => 'http://loja.com/webhook'], 'https'],
])->group('efi');

it('consulta, lista e remove pela chave', function () {
    $history = [];
    $webhook = pixWebhook([
        jsonResponse(['webhookUrl' => 'https://loja.com/webhook']),
        jsonResponse(['webhooks' => []]),
        new Response(204),
    ], $history);

    $webhook->find('+5511999998888');
    $webhook->getAll();

    expect($webhook->destroy('+5511999998888'))->toBeTrue()
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/webhook/%2B5511999998888')
        ->and($history[1]['request']->getUri()->getQuery())->toContain('inicio=')
        ->and($history[2]['request']->getMethod())->toBe('DELETE');
})->group('efi');
