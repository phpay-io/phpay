<?php

use PHPay\Contracts\{Capability, GatewayInterface};
use PHPay\Efi\EfiGateway;
use PHPay\Efi\Interface\EfiGatewayInterface;
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\{ApiException, NotImplementedException, ValidationException};
use PHPay\PHPay;
use PHPay\Support\Money;

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

it('reaproveita o token enquanto ele não expira', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['access_token' => 'tok_1', 'token_type' => 'Bearer', 'expires_in' => 600]),
    ], $history);

    $gateway = new EfiGateway('client-id', 'client-secret', true, $client);
    $gateway->getToken();
    $gateway->getToken();

    expect($history)->toHaveCount(1);
})->group('efi');

it('autoriza de novo quando o token expira', function () {
    $history = [];
    $client  = mockClient([
        /* 10s de vida é menos que a margem de 30s: já nasce vencido */
        jsonResponse(['access_token' => 'tok_1', 'token_type' => 'Bearer', 'expires_in' => 10]),
        jsonResponse(['access_token' => 'tok_2', 'token_type' => 'Bearer', 'expires_in' => 600]),
    ], $history);

    $gateway = new EfiGateway('client-id', 'client-secret', true, $client);

    expect($gateway->getToken()['access_token'])->toBe('tok_1')
        ->and($gateway->getToken()['access_token'])->toBe('tok_2')
        ->and($history)->toHaveCount(2);
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

it('declara as capacidades das duas APIs, menos clientes', function () {
    $gateway = new EfiGateway('id', 'secret', true, mockClient([]));

    expect(Capability::of($gateway))->toBe([
        Capability::CHARGES,
        Capability::WEBHOOKS,
        Capability::PIX_KEYS,
        Capability::SUBSCRIPTIONS,
        Capability::WEBHOOK_EVENTS,
        Capability::CHARGE_INTENTS,
    ]);
})->group('efi');

it('avisa pela facade que a efí não tem clientes', function () {
    $phpay = PHPay::gateway(new EfiGateway('id', 'secret', true, mockClient([])));

    expect($phpay->supports(Capability::CUSTOMERS))->toBeFalse()
        ->and(fn () => $phpay->customer())
        ->toThrow(NotImplementedException::class, 'Capacidades disponíveis: cobranças, webhooks, chaves Pix, assinaturas, leitura de webhook, cobrança portável.');
})->group('efi');

it('monta o payload de pessoa física na cobrança', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => ['charge_id' => 1]])], $history);

    (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => 10000,
        'description' => 'Teste de fatura',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901', 'email' => 'fale@phpay.io'])
        ->create();

    $body = recordedBody($history);

    expect($body['items'][0])->toBe(['name' => 'Teste de fatura', 'value' => 10000])
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
        'value'       => 10000,
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
        'value'       => 10000,
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

it('recusa no boleto valor que a efí leria como centavos', function (mixed $valor) {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    /* 100.00 sai no JSON como 100: a Efí emitiria um boleto de R$ 1,00 */
    expect(fn () => (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => $valor,
        'description' => 'Teste',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->create())
        ->toThrow(ValidationException::class, 'CENTAVOS');

    expect($history)->toBeEmpty();
})->with([
    'reais com centavos zerados' => [100.00],
    'reais com centavos'         => [49.90],
    'string numérica'            => ['10000'],
    'zero'                       => [0],
    'Money zerado'               => [Money::centavos(0)],
])->group('efi');

it('aceita Money no valor do boleto', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['data' => ['charge_id' => 1]])], $history);

    (new Charge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'value'       => Money::reais('100,50'),
        'description' => 'Teste de fatura',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, $client))
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->create();

    expect(recordedBody($history)['items'][0]['value'])->toBe(10050);
})->group('efi');

it('não leva o access_token para a exceção quando a autorização vem incompleta', function () {
    $gateway = new EfiGateway('id', 'secret', true, mockClient([
        jsonResponse(['access_token' => 'token-vivo', 'expires_in' => 3600]),
    ]));

    try {
        $gateway->getToken();
        $this->fail('ApiException não foi lançada');
    } catch (ApiException $exception) {
        expect($exception->getResponse())->toBe(['expires_in' => 3600])
            ->and(json_encode($exception->getResponse()))->not->toContain('token-vivo');
    }
})->group('efi');

it('implementa o contrato do gateway e a interface própria', function () {
    expect(EfiGateway::class)
        ->toImplement(GatewayInterface::class)
        ->and(EfiGateway::class)->toImplement(EfiGatewayInterface::class);
})->group('efi');
