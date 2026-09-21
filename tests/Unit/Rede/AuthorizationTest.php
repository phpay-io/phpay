<?php

use PHPay\Exceptions\ApiException;
use PHPay\Rede\Resources\Authorization\Authorization;

it('negocia o token uma vez e reaproveita enquanto vale', function () {
    $history = [];
    $client  = redeOauthClient([jsonResponse(['access_token' => 'tok_1', 'expires_in' => 3600])], $history);

    $auth = new Authorization('pv', 'segredo', 'oauth2/token', $client);

    expect($auth->token())->toBe('tok_1')
        ->and($auth->token())->toBe('tok_1')
        ->and($auth->token())->toBe('tok_1')
        ->and($history)->toHaveCount(1);
})->group('rede');

it('autentica a negociação com basic auth de PV e token', function () {
    $history = [];
    $client  = redeOauthClient([jsonResponse(['access_token' => 'tok_1', 'expires_in' => 3600])], $history);

    (new Authorization('minha-pv', 'meu-segredo', 'oauth2/token', $client))->token();

    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toEndWith('/oauth2/token')
        ->and($request->getHeaderLine('Authorization'))
        ->toBe('Basic ' . base64_encode('minha-pv:meu-segredo'))
        ->and((string) $request->getBody())->toBe('grant_type=client_credentials');
})->group('rede');

it('renegocia quando o token expira', function () {
    $history = [];
    $client  = redeOauthClient([
        /* expires_in menor que a margem de 30s: nasce já vencido */
        jsonResponse(['access_token' => 'tok_1', 'expires_in' => 10]),
        jsonResponse(['access_token' => 'tok_2', 'expires_in' => 3600]),
    ], $history);

    $auth = new Authorization('pv', 'segredo', 'oauth2/token', $client);

    expect($auth->token())->toBe('tok_1')
        ->and($auth->hasValidToken())->toBeFalse()
        ->and($auth->token())->toBe('tok_2')
        ->and($auth->hasValidToken())->toBeTrue()
        ->and($history)->toHaveCount(2);
})->group('rede');

it('permite descartar o token em mãos', function () {
    $history = [];
    $client  = redeOauthClient([
        jsonResponse(['access_token' => 'tok_1', 'expires_in' => 3600]),
        jsonResponse(['access_token' => 'tok_2', 'expires_in' => 3600]),
    ], $history);

    $auth = new Authorization('pv', 'segredo', 'oauth2/token', $client);

    $auth->token();
    $auth->forget();

    expect($auth->hasValidToken())->toBeFalse()
        ->and($auth->token())->toBe('tok_2')
        ->and($history)->toHaveCount(2);
})->group('rede');

it('falha com ApiException quando a negociação não devolve access_token', function () {
    $client = redeOauthClient([jsonResponse(['error' => 'invalid_client'])]);

    expect(fn () => (new Authorization('pv', 'segredo', 'oauth2/token', $client))->token())
        ->toThrow(ApiException::class, 'access_token');
})->group('rede');
