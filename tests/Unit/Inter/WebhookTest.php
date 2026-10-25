<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Inter\Resources\Webhook\Webhook;

it('cadastra o webhook por chave pix, com put', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook(['chave' => 'a82d0acf-6cad-42a1-b5d4-416541654564', 'webhookUrl' => 'https://app.exemplo.com/webhook/'])
        ->create();

    expect($history[1]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[1]['request']->getUri())
        ->toEndWith('pix/v2/webhook/a82d0acf-6cad-42a1-b5d4-416541654564')
        ->and(recordedBody($history, 1))->toBe(['webhookUrl' => 'https://app.exemplo.com/webhook/']);
})->group('inter');

it('a url é mandada como veio, sem o /pix que o inter acrescenta', function () {
    /*
     * o callback é declarado como POST {$request.body#/webhookUrl/pix}: o Inter
     * entrega em <webhookUrl>/pix. Reescrever a URL de quem chamou seria pior
     * que documentar, então nada aqui mexe nela.
     */
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook(['chave' => 'chave', 'webhookUrl' => 'https://app.exemplo.com/webhook/'])
        ->create();

    expect(recordedBody($history, 1)['webhookUrl'])->toBe('https://app.exemplo.com/webhook/')
        ->and(recordedBody($history, 1)['webhookUrl'])->not->toEndWith('/pix');
})->group('inter');

it('a chave vai escapada no caminho', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook(['chave' => 'fale@phpay.io', 'webhookUrl' => 'https://app.exemplo.com/hook'])
        ->create();

    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/webhook/fale%40phpay.io');
})->group('inter');

it('o payload do create sobrescreve o que veio do gateway', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook(['chave' => 'antiga', 'webhookUrl' => 'https://a.exemplo.com/h'])
        ->create(['chave' => 'nova', 'webhookUrl' => 'https://b.exemplo.com/h']);

    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/webhook/nova')
        ->and(recordedBody($history, 1)['webhookUrl'])->toBe('https://b.exemplo.com/h');
})->group('inter');

it('valida antes de chamar', function (array $webhook, string $mensagem) {
    $history = [];
    /* fora da closure: arrow function captura por valor e o histórico não voltaria */
    $resource = interGateway([], $history)->webhook($webhook);

    expect(fn () => $resource->create())
        ->toThrow(ValidationException::class, $mensagem);

    /* só o token saiu */
    expect($history)->toHaveCount(1);
})->with([
    'sem chave'     => [['webhookUrl' => 'https://app.exemplo.com/h'], 'chave'],
    'sem url'       => [['chave' => 'x'], 'webhookUrl'],
    'url inválida'  => [['chave' => 'x', 'webhookUrl' => 'nao-e-url'], 'URL válida'],
    'url sem https' => [['chave' => 'x', 'webhookUrl' => 'http://app.exemplo.com/h'], 'https'],
])->group('inter');

it('consulta e exclui o webhook de uma chave', function () {
    $history = [];
    $gateway = interGateway([jsonResponse(['webhookUrl' => 'https://x']), jsonResponse([])], $history);

    $gateway->webhook()->find('chave');
    $removido = $gateway->webhook()->destroy('chave');

    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/webhook/chave')
        ->and($history[2]['request']->getMethod())->toBe('DELETE')
        ->and($removido)->toBeTrue();
})->group('inter');

it('não existe listagem de webhooks, porque o inter não expõe', function () {
    /*
     * a taxonomia de erros da referência menciona GET /webhook, mas aquela
     * seção é o texto do BACEN copiado inteiro e nomeia endpoints que este
     * gateway não serve. A lista de operações tem só chave, callbacks e reenvio.
     */
    expect(method_exists(Webhook::class, 'getAll'))->toBeFalse();
})->group('inter');

it('lê o log de entregas, com o período que a api exige', function () {
    $history = [];

    interGateway([jsonResponse(['data' => []])], $history)->webhook()->callbacks();

    $query = [];
    parse_str($history[1]['request']->getUri()->getQuery(), $query);

    expect((string) $history[1]['request']->getUri())->toContain('pix/v2/webhook/callbacks')
        ->and($query)->toHaveKeys(['dataHoraInicio', 'dataHoraFim']);
})->group('inter');

it('o filtro do log aceita txid e paginação', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook()
        ->setQueryParams(['txid' => 'abc', 'tamanhoPagina' => 50])
        ->callbacks();

    $query = [];
    parse_str($history[1]['request']->getUri()->getQuery(), $query);

    expect($query['txid'])->toBe('abc')
        ->and($query['tamanhoPagina'])->toBe('50');
})->group('inter');

it('reenvia callbacks por chave e lista de txid', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->webhook()
        ->retryCallbacks('chave@phpay.io', ['txid1', 'txid2']);

    expect($history[1]['request']->getMethod())->toBe('POST')
        ->and((string) $history[1]['request']->getUri())->toEndWith('pix/v2/webhook/callbacks/retry')
        ->and(recordedBody($history, 1))->toBe([
            'chavePix' => 'chave@phpay.io',
            'txIds'    => ['txid1', 'txid2'],
        ]);
})->group('inter');
