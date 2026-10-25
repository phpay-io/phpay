<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Http\Certificate;
use PHPay\Inter\Resources\Authorization\Authorization;
use PHPay\Inter\Resources\Charge\Charge;

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
function interClientConfig(object $resource, string $option): mixed
{
    $property = new ReflectionProperty($resource, 'client');

    return $property->getValue($resource)->getConfig($option);
}

/**
 * um certificado de teste. O conteúdo não é lido: quem lê é o cURL, na hora
 * da requisição, e nenhuma sai daqui.
 *
 * @return Certificate
 */
function interCertificate(): Certificate
{
    $crt = sys_get_temp_dir() . '/phpay-inter-' . bin2hex(random_bytes(5)) . '.crt';
    $key = sys_get_temp_dir() . '/phpay-inter-' . bin2hex(random_bytes(5)) . '.key';

    file_put_contents($crt, 'certificado');
    file_put_contents($key, 'chave');

    register_shutdown_function(static function () use ($crt, $key): void {
        @unlink($crt);
        @unlink($key);
    });

    return Certificate::pair($crt, $key);
}

it('a requisição do token é form-urlencoded e sem Authorization', function () {
    /*
     * o Inter manda client_id e client_secret no corpo, não num header Basic
     * como a Efí — então este client não leva Authorization nenhum.
     */
    $authorization = new Authorization('client-id', 'client-secret', 'cob.read', interCertificate());

    $headers = interClientConfig($authorization, 'headers');

    expect($headers['content-type'])->toBe('application/x-www-form-urlencoded')
        ->and($headers)->not->toHaveKey('Authorization');
})->group('inter');

it('o token também vai por mTLS, como o BACEN exige', function () {
    $certificate = interCertificate();

    expect(interClientConfig(new Authorization('id', 'secret', 'cob.read', $certificate), 'cert'))
        ->toBe($certificate->path())
        ->and(interClientConfig(new Authorization('id', 'secret', 'cob.read', $certificate), 'ssl_key'))
        ->toBe($certificate->keyPath());
})->group('inter');

it('o client de cobrança vai de bearer com o token trocado', function () {
    $charge = new Charge(
        ['access_token' => 'fake-access-token', 'token_type' => 'Bearer'],
        interCertificate(),
    );

    expect(interClientConfig($charge, 'headers')['Authorization'])->toBe('Bearer fake-access-token')
        ->and(interClientConfig($charge, 'headers')['content-type'])->toBe('application/json');
})->group('inter');

it('troca o domínio por ambiente, não o caminho', function () {
    $certificate = interCertificate();

    expect(interClientConfig(new Authorization('id', 'secret', 's', $certificate, true), 'base_uri'))
        ->toEqual('https://cdpj-sandbox.partners.uatinter.co/')
        ->and(interClientConfig(new Authorization('id', 'secret', 's', $certificate, false), 'base_uri'))
        ->toEqual('https://cdpj.partners.bancointer.com.br/');
})->group('inter');

it('herda o prazo e o retry da fábrica', function () {
    $authorization = new Authorization('id', 'secret', 'cob.read', interCertificate());

    expect(interClientConfig($authorization, 'timeout'))->toBe(60)
        ->and(interClientConfig($authorization, 'connect_timeout'))->toBe(10)
        ->and(interClientConfig($authorization, 'handler'))->not->toBeNull();
})->group('inter');

it('manda x-conta-corrente só quando há conta', function () {
    $certificate = interCertificate();
    $token       = ['access_token' => 'tok', 'token_type' => 'Bearer'];

    $comConta = new Charge($token, $certificate, true, '123456');
    $semConta = new Charge($token, $certificate, true);

    expect(interClientConfig($comConta, 'headers')['x-conta-corrente'])->toBe('123456')
        ->and(interClientConfig($semConta, 'headers'))->not->toHaveKey('x-conta-corrente')
        ->and(interClientConfig(new Charge($token, $certificate, true, '  '), 'headers'))
        ->not->toHaveKey('x-conta-corrente');
})->group('inter');

it('sem certificado explica o mTLS e aponta o par de arquivos', function () {
    $token = ['access_token' => 'tok', 'token_type' => 'Bearer'];

    expect(fn () => new Charge($token))
        ->toThrow(ValidationException::class, 'Certificate::pair');
})->group('inter');

it('token sem access_token string é recusado antes de qualquer chamada', function () {
    expect(fn () => new Charge(['access_token' => null, 'token_type' => 'Bearer'], interCertificate()))
        ->toThrow(ValidationException::class, 'Token inválido');
})->group('inter');
