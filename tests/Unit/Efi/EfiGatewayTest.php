<?php

use PHPay\Contracts\Capability;
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\{ApiException, NotImplementedException, ValidationException};
use PHPay\PHPay;

it('não faz chamada de rede ao instanciar o gateway', function () {
    $history = [];
    $client  = mockClient([], $history);

    new EfiGateway('client-id', 'client-secret', true, $client);

    expect($history)->toBeEmpty();
})->group('efi');

it('autoriza apenas na primeira vez que o token é usado', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['access_token' => 'tok_123', 'token_type' => 'Bearer']),
    ], $history);

    $gateway = new EfiGateway('client-id', 'client-secret', true, $client);

    expect($gateway->getToken()['access_token'])->toBe('tok_123')
        ->and($gateway->getToken()['access_token'])->toBe('tok_123')
        ->and($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toEndWith('v1/authorize');
})->group('efi');

it('falha com ApiException quando a autorização não devolve access_token', function () {
    $client = mockClient([jsonResponse(['error' => 'invalid_client'])]);

    expect(fn () => (new EfiGateway('id', 'secret', true, $client))->getToken())
        ->toThrow(ApiException::class, 'access_token');
})->group('efi');

it('devolve o recurso de cobrança', function () {
    $client = mockClient([
        jsonResponse(['access_token' => 'tok_123', 'token_type' => 'Bearer']),
    ]);

    expect((new EfiGateway('id', 'secret', true, $client))->charge())
        ->toBeInstanceOf(Charge::class);
})->group('efi');

it('declara apenas a capacidade de cobranças', function () {
    $gateway = new EfiGateway('id', 'secret', true, mockClient([]));

    expect(Capability::of($gateway))->toBe([Capability::CHARGES]);
})->group('efi');

it('avisa pela facade quais capacidades a efí oferece', function (Capability $capability) {
    $phpay = PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([])));

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => match ($capability) {
        Capability::CUSTOMERS     => $phpay->customer(),
        Capability::WEBHOOKS      => $phpay->webhook(),
        Capability::PIX_KEYS      => $phpay->pix(),
        Capability::SUBSCRIPTIONS => $phpay->subscription(),
        default                   => null,
    })->toThrow(NotImplementedException::class, 'Capacidades disponíveis: cobranças.');
})->with([
    Capability::CUSTOMERS,
    Capability::WEBHOOKS,
    Capability::PIX_KEYS,
    Capability::SUBSCRIPTIONS,
])->group('efi');

it('monta o payload de pessoa física na cobrança', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => ['charge_id' => 1]])], $history);

    (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => 100.00,
        'description' => 'Teste de fatura',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901', 'email' => 'fale@phpay.io'])
        ->create();

    $body = recordedBody($history);

    expect($body['items'][0])->toBe(['name' => 'Teste de fatura', 'value' => 100])
        ->and($body['payment']['banking_billet']['customer'])->toBe([
            'name'  => 'Mário Lucas',
            'cpf'   => '12345678901',
            'email' => 'fale@phpay.io',
        ]);
})->group('efi');

it('monta o payload de pessoa jurídica na cobrança', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => ['charge_id' => 1]])], $history);

    (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => 100.00,
        'description' => 'Teste de fatura',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))
        ->setCustomer(['name' => 'Sixtec LTDA', 'cpf_cnpj' => '12345678000199'])
        ->create();

    expect(recordedBody($history)['payment']['banking_billet']['customer'])->toBe([
        'juridical_person' => [
            'corporate_name' => 'Sixtec LTDA',
            'cnpj'           => '12345678000199',
        ],
    ]);
})->group('efi');

it('exige um cliente antes de criar a cobrança', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => 100.00,
        'description' => 'Teste',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))->create())
        ->toThrow(ValidationException::class, 'Um cliente é obrigatório');

    expect($history)->toBeEmpty();
})->group('efi');

it('recusa cpf ou cnpj com tamanho inválido', function () {
    $charge = new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [], true, mockClient([]));

    expect(fn () => $charge->setCustomer(['name' => 'Mário', 'cpf_cnpj' => '123']))
        ->toThrow(ValidationException::class, '11 dígitos');
})->group('efi');

it('consulta o status da cobrança no endpoint da efí', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => ['status' => 'unpaid']])], $history);

    (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [], true, $client))
        ->getStatus('1234');

    expect((string) $history[0]['request']->getUri())
        ->toEndWith('v1/charge/1234')
        ->not->toContain('payments');
})->group('efi');
