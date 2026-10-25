<?php

use PHPay\Exceptions\ApiException;
use PHPay\Inter\InterGateway;
use PHPay\Inter\Resources\Authorization\Authorization;

it('pede o token em form-urlencoded, com as credenciais no corpo', function () {
    $history = [];

    $token = (new Authorization(
        'client-id',
        'client-secret',
        'cob.read webhook.read',
        null,
        true,
        interClient([interToken('tok')], $history),
    ))->getToken();

    $corpo = [];
    parse_str((string) $history[0]['request']->getBody(), $corpo);

    expect($token['access_token'])->toBe('tok')
        ->and($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('oauth/v2/token')
        ->and($corpo)->toBe([
            'client_id'     => 'client-id',
            'client_secret' => 'client-secret',
            'grant_type'    => 'client_credentials',
            'scope'         => 'cob.read webhook.read',
        ]);
})->group('inter');

it('o escopo é obrigatório na requisição, então sempre vai', function () {
    $history = [];

    interGateway([], $history);
    interGateway([jsonResponse([])], $history)->charge()->find('a');

    $corpo = [];
    parse_str((string) $history[0]['request']->getBody(), $corpo);

    expect($corpo['scope'])->toBe(InterGateway::SCOPES);
})->group('inter');

it('resposta sem access_token levanta sem vazar o token no erro', function () {
    $gateway = new InterGateway(
        'id',
        'secret',
        true,
        client: interClient([jsonResponse([
            'access_token' => 'nao-deveria-aparecer',
            'expires_in'   => 3600,
        ])]),
    );

    try {
        $gateway->charge();
        $this->fail('deveria ter levantado');
    } catch (ApiException $e) {
        /* o token_type faltou, mas o access_token que veio não entra na exceção */
        expect($e->getMessage())->toContain('não retornou access_token')
            ->and($e->getResponse())->not->toHaveKey('access_token')
            ->and(json_encode($e->getResponse()))->not->toContain('nao-deveria-aparecer');
    }
})->group('inter');
