<?php

use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\AbacatePay\Resources\Coupon\Coupon;
use PHPay\AbacatePay\Resources\Customer\Customer;
use PHPay\Exceptions\ValidationException;

/**
 * @return array<mixed>
 */
function abacateCustomer(): array
{
    return [
        'name'      => 'Mário Lucas',
        'email'     => 'fale@phpay.io',
        'cellphone' => '(11) 4002-8922',
        'taxId'     => '12345678901',
    ];
}

/**
 * @param Charge $charge
 * @return Charge
 */
function abacateBillingValido(Charge $charge): Charge
{
    return $charge
        ->setCustomer(abacateCustomer())
        ->addProduct('prod-1', 'Assinatura PHPay', 2000)
        ->setUrls('https://exemplo.test/obrigado', 'https://exemplo.test/loja');
}

it('cria a cobrança com frequência e método preenchidos por padrão', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => ['id' => 'bill_1', 'url' => 'https://pay.test/x']])], $history);

    abacateBillingValido(new Charge('token', $client))->create();

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/v1/billing/create')
        ->and($body['frequency'])->toBe('ONE_TIME')
        ->and($body['methods'])->toBe(['PIX'])
        ->and($body['products'][0]['price'])->toBe(2000)
        ->and($body['products'][0]['externalId'])->toBe('prod-1');
})->group('abacatepay');

it('reaproveita o cliente quando o array traz um id', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => []])], $history);

    (new Charge('token', $client))
        ->setCustomer(['id' => 'cust_existente'])
        ->addProduct('prod-1', 'Item', 500)
        ->setUrls('https://exemplo.test/ok', 'https://exemplo.test/volta')
        ->create();

    $body = recordedBody($history);

    expect($history)->toHaveCount(1)
        ->and($body['customerId'])->toBe('cust_existente')
        ->and($body)->not->toHaveKey('customer');
})->group('abacatepay');

it('lê o link de pagamento e o devMode da resposta', function () {
    $charge = new Charge('token', abacateClient([]));

    $resposta = ['data' => ['id' => 'bill_1', 'url' => 'https://pay.test/abc', 'devMode' => true]];

    expect($charge->getPaymentUrl($resposta))->toBe('https://pay.test/abc')
        ->and($charge->isDevMode($resposta))->toBeTrue();
})->group('abacatepay');

it('funciona mesmo sem o envelope data', function () {
    $charge = new Charge('token', abacateClient([]));

    expect($charge->getPaymentUrl(['url' => 'https://pay.test/abc']))->toBe('https://pay.test/abc')
        ->and($charge->isDevMode(['id' => 'bill_1']))->toBeNull();
})->group('abacatepay');

it('acumula produtos e aceita descrição opcional', function () {
    $history = [];
    $client  = abacateClient([jsonResponse(['data' => []])], $history);

    (new Charge('token', $client))
        ->setCustomerId('cust_1')
        ->addProduct('prod-1', 'Camiseta', 5990, 2)
        ->addProduct('prod-2', 'Caneca', 1990, 1, 'Caneca de cerâmica')
        ->setUrls('https://exemplo.test/ok', 'https://exemplo.test/volta')
        ->create();

    $produtos = recordedBody($history)['products'];

    expect($produtos)->toHaveCount(2)
        ->and($produtos[0])->not->toHaveKey('description')
        ->and($produtos[1]['description'])->toBe('Caneca de cerâmica')
        ->and($produtos[0]['quantity'])->toBe(2);
})->group('abacatepay');

it('lista cobranças, clientes e cupons nos endpoints certos', function () {
    $history = [];
    $client  = abacateClient([jsonResponse([]), jsonResponse([]), jsonResponse([])], $history);

    (new Charge('token', $client))->setQueryParams(['limit' => 5])->getAll();
    (new Customer('token', [], $client))->getAll();
    (new Coupon('token', $client))->getAll();

    expect((string) $history[0]['request']->getUri())->toContain('/billing/list')
        ->and((string) $history[0]['request']->getUri())->toContain('limit=5')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/customer/list')
        ->and((string) $history[2]['request']->getUri())->toEndWith('/coupon/list');
})->group('abacatepay');

it('recusa preço abaixo do mínimo de R$ 1,00', function () {
    $history = [];
    $client  = abacateClient([jsonResponse([])], $history);

    expect(fn () => (new Charge('token', $client))
        ->setCustomerId('cust_1')
        ->addProduct('prod-1', 'Item', 99)
        ->setUrls('https://exemplo.test/ok', 'https://exemplo.test/volta')
        ->create())
        ->toThrow(ValidationException::class, 'no mínimo 100');

    expect($history)->toBeEmpty();
})->group('abacatepay');

it('valida a cobrança antes de chamar a API', function (callable $montar, string $esperado) {
    $history = [];
    $client  = abacateClient([jsonResponse([])], $history);

    expect(fn () => $montar(new Charge('token', $client))->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'sem produtos' => [
        fn (Charge $c) => $c->setCustomerId('cust_1')->setUrls('https://a.test/ok', 'https://a.test/volta'),
        'ao menos um produto',
    ],
    'sem urls' => [
        fn (Charge $c) => $c->setCustomerId('cust_1')->addProduct('p1', 'Item', 500),
        'returnUrl',
    ],
    'sem cliente' => [
        fn (Charge $c) => $c->addProduct('p1', 'Item', 500)->setUrls('https://a.test/ok', 'https://a.test/volta'),
        'customerId ou de um customer completo',
    ],
    'preço decimal' => [
        fn (Charge $c) => $c->setCustomerId('cust_1')
            ->setProducts([['externalId' => 'p1', 'name' => 'Item', 'quantity' => 1, 'price' => 20.00]])
            ->setUrls('https://a.test/ok', 'https://a.test/volta'),
        'CENTAVOS',
    ],
    'frequência recorrente' => [
        fn (Charge $c) => $c->setBilling([
            'frequency' => 'MONTHLY', 'methods' => ['PIX'], 'customerId' => 'cust_1',
            'products'  => [['externalId' => 'p1', 'name' => 'i', 'quantity' => 1, 'price' => 500]],
            'returnUrl' => 'https://a.test/v', 'completionUrl' => 'https://a.test/o',
        ]),
        'não tem cobrança recorrente',
    ],
])->group('abacatepay');

it('exige celular e documento do cliente', function () {
    $history = [];
    $client  = abacateClient([jsonResponse([])], $history);

    expect(fn () => (new Customer('token', ['name' => 'X', 'email' => 'a@b.com', 'taxId' => '123'], $client))->create())
        ->toThrow(ValidationException::class, 'cellphone');

    expect(fn () => (new Customer('token', ['name' => 'X', 'email' => 'a@b.com', 'cellphone' => '11'], $client))->create())
        ->toThrow(ValidationException::class, 'taxId');

    expect($history)->toBeEmpty();
})->group('abacatepay');
