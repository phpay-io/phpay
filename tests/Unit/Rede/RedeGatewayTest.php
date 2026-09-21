<?php

use PHPay\Contracts\Capability;
use PHPay\Exceptions\{NotImplementedException, ValidationException};
use PHPay\PHPay;
use PHPay\Rede\Enums\{TransactionKindEnum, TransactionStatusEnum};
use PHPay\Rede\{RedeEnvironment, RedeGateway};
use PHPay\Rede\Resources\Charge\Charge;

/**
 * @param array<int, mixed> $api
 * @param array<int, mixed> $historicoApi
 * @return RedeGateway
 */
function redeGateway(array $api = [], array &$historicoApi = []): RedeGateway
{
    $oauth = [];

    return new RedeGateway(
        'pv',
        'segredo',
        true,
        redeClient($api, $historicoApi),
        redeOauthClient([jsonResponse(['access_token' => 'tok_1', 'expires_in' => 3600])], $oauth)
    );
}

it('declara apenas cobranças', function () {
    expect(Capability::of(redeGateway()))->toBe([Capability::CHARGES]);
})->group('rede');

it('recusa as outras quatro capacidades pela facade', function (Capability $capability) {
    $phpay = PHPay::gateway(redeGateway());

    expect($phpay->supports($capability))->toBeFalse();

    expect(fn () => match ($capability) {
        Capability::CUSTOMERS     => $phpay->customer(),
        Capability::WEBHOOKS      => $phpay->webhook(),
        Capability::SUBSCRIPTIONS => $phpay->subscription(),
        default                   => $phpay->pix(),
    })->toThrow(NotImplementedException::class, 'Rede não suporta');
})->with([
    Capability::CUSTOMERS,
    Capability::WEBHOOKS,
    Capability::SUBSCRIPTIONS,
    Capability::PIX_KEYS,
])->group('rede');

it('separa o host de autorização do host de api, com caminho de token por ambiente', function () {
    expect(RedeEnvironment::api(true))->toBe('https://sandbox-erede.useredecloud.com.br/v2/')
        ->and(RedeEnvironment::api(false))->toBe('https://api.userede.com.br/erede/v2/')
        ->and(RedeEnvironment::oauth(true))->toBe('https://rl7-sandbox-api.useredecloud.com.br/')
        ->and(RedeEnvironment::oauth(false))->toBe('https://api.userede.com.br/')
        ->and(RedeEnvironment::tokenPath(true))->toBe('oauth2/token')
        ->and(RedeEnvironment::tokenPath(false))->toBe('redelabs/oauth2/token');
})->group('rede');

it('não faz chamada de rede ao instanciar o gateway', function () {
    $api   = [];
    $oauth = [];

    new RedeGateway('pv', 'segredo', true, redeClient([], $api), redeOauthClient([], $oauth));

    expect($api)->toBeEmpty()->and($oauth)->toBeEmpty();
})->group('rede');

it('compartilha a mesma autorização entre os recursos', function () {
    $gateway = redeGateway();

    expect($gateway->authorization())->toBe($gateway->authorization());
})->group('rede');

it('cria a transação com bearer token e valor em centavos', function () {
    $api    = [];
    $phpay  = redeGateway([jsonResponse(['tid' => 'tid_1', 'returnCode' => '00'])], $api);
    $charge = $phpay->charge();

    $charge
        ->setReference('pedido-1')
        ->setCard('5448280000000007', 'MARIO LUCAS', '12', '2030', '123')
        ->setPayment(2099, TransactionKindEnum::CREDIT, 1, true)
        ->setSoftDescriptor('PHPAY')
        ->create();

    $request = $api[0]['request'];
    $body    = recordedBody($api);

    expect((string) $request->getUri())->toEndWith('/v2/transactions')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer tok_1')
        ->and($body['amount'])->toBe(2099)
        ->and($body['kind'])->toBe('credit')
        ->and($body['capture'])->toBeTrue()
        ->and($body['softDescriptor'])->toBe('PHPAY');
})->group('rede');

it('captura, estorna e consulta nos endpoints certos', function () {
    $api    = [];
    $charge = redeGateway([
        jsonResponse([]), jsonResponse([]), jsonResponse(['returnCode' => '00']),
    ], $api)->charge();

    $charge->capture('tid_1', 1000);
    $charge->refund('tid_1');
    $status = $charge->getStatus('tid_1');

    expect($api[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $api[0]['request']->getUri())->toEndWith('/transactions/tid_1')
        ->and(recordedBody($api, 0))->toBe(['amount' => 1000])
        ->and($api[1]['request']->getMethod())->toBe('POST')
        ->and((string) $api[1]['request']->getUri())->toEndWith('/transactions/tid_1/refunds')
        ->and($api[2]['request']->getMethod())->toBe('GET')
        ->and($status)->toBe('00')
        ->and(TransactionStatusEnum::approved('00'))->toBeTrue()
        ->and(TransactionStatusEnum::approved('51'))->toBeFalse();
})->group('rede');

it('busca pela referência do seu sistema', function () {
    $api    = [];
    $charge = redeGateway([jsonResponse([])], $api)->charge();

    $charge->findByReference('pedido-1');

    expect((string) $api[0]['request']->getUri())->toContain('reference=pedido-1');
})->group('rede');

it('valida a transação antes de chamar a API', function (callable $montar, string $esperado) {
    $api    = [];
    $charge = redeGateway([jsonResponse([])], $api)->charge();

    expect(fn () => $montar($charge)->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($api)->toBeEmpty();
})->with([
    'sem cartão' => [
        fn (Charge $c) => $c->setReference('p1')->setPayment(100),
        'dados do cartão são obrigatórios',
    ],
    'sem valor' => [
        fn (Charge $c) => $c->setReference('p1')->setCard('5448', 'M L', '12', '2030', '123'),
        'O campo amount é obrigatório',
    ],
    'valor decimal' => [
        fn (Charge $c) => $c->setTransaction([
            'reference' => 'p1', 'amount' => 20.99, 'kind' => 'credit', 'installments' => 1,
        ]),
        'CENTAVOS',
    ],
    'kind fora do enum' => [
        fn (Charge $c) => $c->setTransaction([
            'reference' => 'p1', 'amount' => 2099, 'kind' => 'voucher', 'installments' => 1,
        ]),
        'credit, debit',
    ],
])->group('rede');
