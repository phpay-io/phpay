<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Exceptions\{ApiException, PHPayException};

it('marca falha de conexão com status zero', function () {
    $client = mockClient([
        new ConnectException('Connection refused', new Request('GET', 'customers')),
    ]);

    try {
        (new Customer('token', [], true, $client))->getAll();
        $this->fail('ApiException não foi lançada');
    } catch (ApiException $exception) {
        expect($exception->getStatusCode())->toBe(0)
            ->and($exception->isConnectionError())->toBeTrue()
            ->and($exception->getResponse())->toBeEmpty();
    }
})->group('phpay');

it('permite capturar qualquer falha pela interface PHPayException', function () {
    $client = mockClient([jsonResponse(['errors' => [['description' => 'boom']]], 500)]);

    $capturada = null;

    try {
        (new Customer('token', [], true, $client))->getAll();
    } catch (PHPayException $exception) {
        $capturada = $exception;
    }

    expect($capturada)->toBeInstanceOf(ApiException::class)
        ->and($capturada)->toBeInstanceOf(PHPayException::class);
})->group('phpay');

it('inclui método, endpoint e status na mensagem', function () {
    $client = mockClient([jsonResponse(['errors' => [['description' => 'boom']]], 404)]);

    try {
        (new Customer('token', [], true, $client))->find('cus_404');
        $this->fail('ApiException não foi lançada');
    } catch (ApiException $exception) {
        expect($exception->getMessage())
            ->toContain('Asaas')
            ->toContain('GET')
            ->toContain('customers/cus_404')
            ->toContain('404')
            ->toContain('boom');
    }
})->group('phpay');

it('resume o formato de erro da efí', function () {
    $exception = ApiException::fromThrowable(
        new GuzzleHttp\Exception\ClientException(
            'erro',
            new Request('POST', 'v1/authorize'),
            jsonResponse(['error' => 'unauthorized', 'error_description' => 'Credenciais inválidas'], 401)
        ),
        'Efí',
        'POST',
        'v1/authorize'
    );

    expect($exception->getMessage())->toContain('Credenciais inválidas')
        ->and($exception->getStatusCode())->toBe(401)
        ->and($exception->getGateway())->toBe('Efí');
})->group('efi');
