<?php

use GuzzleHttp\Client;
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Resources\PixAuthorization\PixAuthorization;
use PHPay\Efi\Resources\PixCharge\PixCharge;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Http\Certificate;

/**
 * the Guzzle client a resource built for itself.
 *
 * @param object $resource
 * @return Client
 */
function builtClient(object $resource): Client
{
    $property = new ReflectionProperty($resource, 'client');

    /** @var Client $client */
    $client = $property->getValue($resource);

    return $client;
}

beforeEach(function () {
    $this->certificatePath = sys_get_temp_dir() . '/phpay-efi-' . bin2hex(random_bytes(6)) . '.p12';
    file_put_contents($this->certificatePath, 'bytes do p12');
});

afterEach(function () {
    if (is_file($this->certificatePath)) {
        unlink($this->certificatePath);
    }
});

it('pede o token da API Pix em oauth/token, não no v1/authorize da API de Cobranças', function () {
    $history = [];
    $gateway = efiPixGateway([], $history);

    expect($gateway->getPixToken()['access_token'])->toBe('pix_tok')
        ->and((string) $history[0]['request']->getUri())->toBe('https://pix-h.api.efipay.com.br/oauth/token')
        ->and(recordedBody($history))->toBe(['grant_type' => 'client_credentials']);
})->group('efi');

it('mantém o token da API Pix separado do token da API de Cobranças', function () {
    $cobrancas = [];
    $pix       = [];

    $gateway = new EfiGateway(
        'id',
        'secret',
        true,
        mockClient([jsonResponse(['access_token' => 'cob_tok', 'token_type' => 'Bearer'])], $cobrancas),
        pixClient: efiPixClient([efiPixToken('pix_tok')], $pix),
    );

    expect($gateway->getToken()['access_token'])->toBe('cob_tok')
        ->and($gateway->getPixToken()['access_token'])->toBe('pix_tok')
        ->and($cobrancas)->toHaveCount(1)
        ->and($pix)->toHaveCount(1);
})->group('efi');

it('reaproveita o token da API Pix e renova quando expira', function () {
    $history = [];
    $gateway = new EfiGateway('id', 'secret', true, mockClient([]), pixClient: efiPixClient([
        efiPixToken('tok_1', 10),
        efiPixToken('tok_2', 3600),
    ], $history));

    expect($gateway->getPixToken()['access_token'])->toBe('tok_1')
        ->and($gateway->getPixToken()['access_token'])->toBe('tok_2')
        ->and($gateway->getPixToken()['access_token'])->toBe('tok_2')
        ->and($history)->toHaveCount(2);
})->group('efi');

it('falha com ApiException quando a autorização da API Pix não devolve access_token', function () {
    $gateway = new EfiGateway('id', 'secret', true, mockClient([]), pixClient: efiPixClient([
        jsonResponse(['error' => 'invalid_client']),
    ]));

    expect(fn () => $gateway->getPixToken())->toThrow(ApiException::class, 'API Pix');
})->group('efi');

it('exige o certificado quando a biblioteca monta o próprio client', function () {
    expect(fn () => (new EfiGateway('id', 'secret'))->pix())
        ->toThrow(ValidationException::class, 'exige o certificado');
})->group('efi');

it('recusa no construtor um caminho de certificado inválido, sem chamada de rede', function () {
    expect(fn () => new EfiGateway('id', 'secret', certificate: '/nao/existe/certificado.p12'))
        ->toThrow(ValidationException::class, 'arquivo não encontrado');
})->group('efi');

it('autoriza por mTLS e Basic no host Pix do ambiente', function (bool $sandbox, string $host) {
    $client = builtClient(new PixAuthorization('id', 'secret', new Certificate($this->certificatePath, 'senha'), $sandbox));

    expect((string) $client->getConfig('base_uri'))->toBe($host)
        ->and($client->getConfig('auth'))->toBe(['id', 'secret'])
        ->and($client->getConfig('cert'))->toBe([$this->certificatePath, 'senha']);
})->with([
    'sandbox'  => [true, 'https://pix-h.api.efipay.com.br/'],
    'produção' => [false, 'https://pix.api.efipay.com.br/'],
])->group('efi');

it('apresenta o certificado e o Bearer em toda requisição dos recursos Pix', function () {
    $client = builtClient(new PixCharge(
        ['access_token' => 'pix_tok', 'token_type' => 'Bearer'],
        new Certificate($this->certificatePath),
    ));

    expect($client->getConfig('cert'))->toBe($this->certificatePath)
        ->and($client->getConfig('headers')['Authorization'])->toBe('Bearer pix_tok');
})->group('efi');
