<?php

use PHPay\Exceptions\ApiException;
use PHPay\Rede\RedeEnvironment;
use PHPay\Rede\Resources\Authorization\Authorization;
use PHPay\Rede\Resources\Charge\Charge;

/**
 * o client que o trait monta, sem requisição nenhuma.
 *
 * construir o Resource sem injetar client faz o caminho de boot rodar — a
 * ClientFactory só monta o objeto, não fala com ninguém —, e a configuração
 * resultante é lida por reflexão.
 *
 * @param object $resource
 * @param string $option
 * @return mixed
 */
function redeClientConfig(object $resource, string $option): mixed
{
    $property = new ReflectionProperty($resource, 'client');

    return $property->getValue($resource)->getConfig($option);
}

/**
 * @param bool $sandbox
 * @return Charge
 */
function redeChargeSemClient(bool $sandbox): Charge
{
    return new Charge(
        new Authorization('affiliation', 'secret', 'oauth2/token', redeOauthClient([])),
        $sandbox
    );
}

it('aponta o client de transações para o host da api, por ambiente', function () {
    expect(redeClientConfig(redeChargeSemClient(true), 'base_uri'))
        ->toEqual(RedeEnvironment::api(true))
        ->and(redeClientConfig(redeChargeSemClient(false), 'base_uri'))
        ->toEqual(RedeEnvironment::api(false));

    /* o host de API é separado do de OAuth: são endereços diferentes */
    expect((string) RedeEnvironment::api(true))->not->toBe(RedeEnvironment::oauth(true));
})->group('rede');

it('monta o client de transações pedindo e aceitando json', function () {
    $headers = redeClientConfig(redeChargeSemClient(true), 'headers');

    expect($headers['content-type'])->toBe('application/json')
        ->and($headers['accept'])->toBe('application/json')
        ->and($headers['user-agent'])->toBe('PHPay');
})->group('rede');

it('herda o prazo e o retry da fábrica, em vez do padrão do guzzle', function () {
    $charge = redeChargeSemClient(true);

    /* o padrão do Guzzle é esperar para sempre; a fábrica põe teto nos dois */
    expect(redeClientConfig($charge, 'timeout'))->toBe(60)
        ->and(redeClientConfig($charge, 'connect_timeout'))->toBe(10)
        ->and(redeClientConfig($charge, 'handler'))->not->toBeNull();
})->group('rede');

it('prefixa a exceção com o nome do gateway', function () {
    $charge = new Charge(
        new Authorization('affiliation', 'secret', 'oauth2/token', redeOauthClient([])),
        true,
        redeClient([jsonResponse(['message' => 'recusado'], 400)])
    );

    expect(fn () => $charge->find('tid_1'))
        ->toThrow(ApiException::class, 'Rede');
})->group('rede');
