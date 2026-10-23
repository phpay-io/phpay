<?php

use PHPay\Efi\Resources\Authorization\Authorization;
use PHPay\Efi\Resources\Charge\Charge;

/**
 * o client que o trait monta, sem requisição nenhuma.
 *
 * o Resource construído sem client injetado roda o caminho de boot; a
 * ClientFactory só monta o objeto. A configuração é lida por reflexão.
 *
 * @param object $resource
 * @param string $option
 * @return mixed
 */
function efiClientConfig(object $resource, string $option): mixed
{
    $property = new ReflectionProperty($resource, 'client');

    return $property->getValue($resource)->getConfig($option);
}

it('autoriza com basic das credenciais, não com bearer', function () {
    $authorization = new Authorization('client-id', 'client-secret', true);

    $headers = efiClientConfig($authorization, 'headers');

    /*
     * a troca de credencial por token é Basic; só as chamadas seguintes vão
     * de Bearer. Mandar Bearer aqui seria pedir token com token.
     */
    expect($headers['Authorization'])->toBe('Basic ' . base64_encode('client-id:client-secret'))
        ->and($headers['content-type'])->toBe('application/json');
})->group('efi');

it('troca o host da api de cobranças por ambiente', function () {
    expect(efiClientConfig(new Authorization('id', 'secret', true), 'base_uri'))
        ->toEqual('https://cobrancas-h.api.efipay.com.br/')
        ->and(efiClientConfig(new Authorization('id', 'secret', false), 'base_uri'))
        ->toEqual('https://cobrancas.api.efipay.com.br/');
})->group('efi');

it('herda o prazo e o retry da fábrica no client de autorização', function () {
    $authorization = new Authorization('id', 'secret', true);

    expect(efiClientConfig($authorization, 'timeout'))->toBe(60)
        ->and(efiClientConfig($authorization, 'connect_timeout'))->toBe(10)
        ->and(efiClientConfig($authorization, 'handler'))->not->toBeNull();
})->group('efi');

it('o client de cobrança já vai de bearer, com o token trocado', function () {
    $charge = new Charge(['access_token' => 'fake-access-token', 'token_type' => 'Bearer'], [], true);

    /* mesmo host da autorização, esquema diferente: lá Basic, aqui Bearer */
    expect(efiClientConfig($charge, 'headers')['Authorization'])->toBe('Bearer fake-access-token')
        ->and(efiClientConfig($charge, 'base_uri'))->toEqual('https://cobrancas-h.api.efipay.com.br/');
})->group('efi');
