<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Exceptions\ValidationException;
use PHPay\Http\ClientFactory;
use PHPay\PHPay;
use PHPay\Support\{ChargeIntent, Customer, Item, Method, Money};

/*
| Três dos dez não têm valor na cobrança: o PagBank e o Pagar.me somam `items`,
| a AbacatePay soma `products`. O mesmo intent precisa funcionar nos dois
| sentidos — escalar virando uma linha, e lista de linhas virando o total.
*/

afterEach(function () {
    ClientFactory::stopFaking();
});

function clienteCompleto(): Customer
{
    return Customer::make(
        name: 'Maria Oliveira',
        document: '123.456.789-09',
        email: 'maria@exemplo.com',
        phone: '(11) 94002-8922',
    );
}

/** @return array<int, Item> */
function itensDeTeste(): array
{
    return [
        new Item('Camiseta', Money::reais('54,90'), 2),
        new Item('Caneca', Money::reais('40,00')),
    ];
}

function urls(ChargeIntent $intent): ChargeIntent
{
    return $intent->withExtra([
        'returnUrl'     => 'https://loja.exemplo.com/volta',
        'completionUrl' => 'https://loja.exemplo.com/obrigado',
    ]);
}

it('o valor do intent é a soma das linhas', function () {
    $intent = ChargeIntent::comItens(Method::PIX, itensDeTeste());

    expect($intent->amount->toCentavos())->toBe(14980)
        ->and($intent->items())->toHaveCount(2);
})->group('support');

it('descreve a cobrança com o nome do primeiro item quando ninguém descreveu', function () {
    expect(ChargeIntent::comItens(Method::PIX, itensDeTeste())->descriptionOrDefault())->toBe('Camiseta')
        ->and(ChargeIntent::comItens(Method::PIX, itensDeTeste(), description: 'Pedido 42')->descriptionOrDefault())
        ->toBe('Pedido 42');
})->group('support');

it('transforma valor escalar numa linha só, como a efí já fazia', function () {
    $linhas = ChargeIntent::pix(Money::reais('149,90'), description: 'Plano anual')->itemsOrOne();

    expect($linhas)->toHaveCount(1)
        ->and($linhas[0]->name)->toBe('Plano anual')
        ->and($linhas[0]->quantity)->toBe(1)
        ->and($linhas[0]->unitAmount->toCentavos())->toBe(14990);
})->group('support');

it('manda as linhas para o pagbank, e o total no qr code', function () {
    ClientFactory::fake([new Response(200, [], '{"id":"ord_1"}')]);

    PHPay::make('pagbank', ['token' => 't'])
        ->createCharge(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto()));

    $corpo = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    expect($corpo['items'])->toHaveCount(2)
        ->and($corpo['items'][0]['unit_amount'])->toBe(5490)
        ->and($corpo['items'][0]['quantity'])->toBe(2)
        ->and($corpo['qr_codes'][0]['amount']['value'])->toBe(14980);
})->group('support');

it('manda as linhas para o pagar.me, com o pix em payments', function () {
    ClientFactory::fake([new Response(200, [], '{"id":"or_1"}')]);

    PHPay::make('pagarme', ['secretKey' => 'sk_test_1'])
        ->createCharge(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto()));

    $corpo = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    expect($corpo['items'])->toHaveCount(2)
        ->and($corpo['items'][0]['amount'])->toBe(5490)
        ->and($corpo['payments'][0]['payment_method'])->toBe('pix');
})->group('support');

it('manda os produtos para a abacatepay, com externalId derivado do nome', function () {
    ClientFactory::fake([new Response(200, [], '{"data":{"id":"bill_1"}}')]);

    PHPay::make('abacatepay', ['token' => 't'])->createCharge(
        urls(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto()))
    );

    $corpo = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    expect($corpo['products'])->toHaveCount(2)
        ->and($corpo['products'][0]['price'])->toBe(5490)
        ->and($corpo['frequency'])->toBe('ONE_TIME')
        ->and($corpo['methods'])->toBe(['PIX'])
        ->and($corpo['products'][0]['externalId'])->toBe(substr(sha1('Camiseta'), 0, 16));
})->group('support');

it('o mesmo produto não ganha um externalId novo a cada cobrança', function () {
    ClientFactory::fake([
        new Response(200, [], '{"data":{"id":"bill_1"}}'),
        new Response(200, [], '{"data":{"id":"bill_2"}}'),
    ]);

    $gateway = PHPay::make('abacatepay', ['token' => 't']);
    $intent  = urls(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto()));

    $gateway->createCharge($intent);
    $gateway->createCharge($intent);

    $um   = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);
    $dois = json_decode((string) ClientFactory::recorded()[1]['request']->getBody(), true);

    expect($um['products'][0]['externalId'])->toBe($dois['products'][0]['externalId']);
})->group('support');

it('respeita a referência do item quando quem integra deu uma', function () {
    ClientFactory::fake([new Response(200, [], '{"data":{"id":"bill_1"}}')]);

    PHPay::make('abacatepay', ['token' => 't'])->createCharge(
        urls(ChargeIntent::comItens(Method::PIX, [
            new Item('Camiseta', Money::reais('54,90'), 1, reference: 'sku-123'),
        ], clienteCompleto()))
    );

    $corpo = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    expect($corpo['products'][0]['externalId'])->toBe('sku-123');
})->group('support');

it('recusa a abacatepay sem as urls, que são da aplicação e não da cobrança', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('abacatepay', ['token' => 't'])
        ->createCharge(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto())))
        ->toThrow(ValidationException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('recusa produto abaixo de um real em vez de arredondar', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('abacatepay', ['token' => 't'])
        ->createCharge(urls(ChargeIntent::pix(Money::centavos(50), clienteCompleto()))))
        ->toThrow(ValidationException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('um intent por itens também serve nos gateways de valor escalar, somando', function () {
    ClientFactory::fake([new Response(200, [], '{"charge":{}}')]);

    PHPay::make('woovi', ['appId' => 'a'])
        ->createCharge(ChargeIntent::comItens(Method::PIX, itensDeTeste(), clienteCompleto()));

    $corpo = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    /* a Woovi não tem linhas: sai o total, e o detalhe se perde — é limite da API dela */
    expect($corpo['value'])->toBe(14980)
        ->and($corpo)->not->toHaveKey('items');
})->group('support');

it('declara só pix nos três que cobram por item', function (string $gateway, array $config) {
    expect(PHPay::make($gateway, $config)->supportedMethods())->toBe([Method::PIX]);
})->with([
    ['pagbank', ['token' => 't']],
    ['pagarme', ['secretKey' => 'sk_test_1']],
    ['abacatepay', ['token'  => 't']],
])->group('support');
