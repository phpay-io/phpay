<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Exceptions\{NotImplementedException, ValidationException};
use PHPay\Http\ClientFactory;
use PHPay\PHPay;
use PHPay\Support\{ChargeIntent, Customer, Method, Money};

/*
| O ponto do value object: UMA descrição de cobrança, quatro payloads. O que
| diverge é o corpo que cada gateway exige — e é a API deles, não escolha da
| biblioteca. O que não diverge é a chamada de quem integra.
*/

afterEach(function () {
    ClientFactory::stopFaking();
});

function intentDeTeste(?string $vencimento = null): ChargeIntent
{
    return ChargeIntent::pix(
        amount: Money::reais('149,90'),
        customer: Customer::make(
            name: 'Maria Oliveira',
            document: '123.456.789-09',
            email: 'maria@exemplo.com',
        ),
        dueDate: $vencimento,
    );
}

it('manda o mesmo valor na unidade de cada gateway', function () {
    ClientFactory::fake([new Response(200, [], '{"id":"cus_1"}'), new Response(200, [], '{"id":"pay_1"}')]);
    PHPay::make('asaas', ['token' => 't'])->createCharge(intentDeTeste('2099-11-10'));
    $asaas = json_decode((string) ClientFactory::recorded()[1]['request']->getBody(), true);
    ClientFactory::stopFaking();

    ClientFactory::fake([new Response(200, [], '{"charge":{}}')]);
    PHPay::make('woovi', ['appId' => 'a'])->createCharge(intentDeTeste());
    $woovi = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);
    ClientFactory::stopFaking();

    ClientFactory::fake([new Response(200, [], '{"id":1}')]);
    PHPay::make('mercadopago', ['accessToken' => 'TEST-1'])->createCharge(intentDeTeste());
    $mp = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    /* reais nos dois de reais, centavos no de centavos, do mesmo Money */
    expect($asaas['value'])->toBe(149.9)
        ->and($mp['transaction_amount'])->toBe(149.9)
        ->and($woovi['value'])->toBe(14990);
})->group('support');

it('traduz o mesmo cliente para o schema de cada um', function () {
    ClientFactory::fake([new Response(200, [], '{"charge":{}}')]);
    PHPay::make('woovi', ['appId' => 'a'])->createCharge(intentDeTeste());
    $woovi = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);
    ClientFactory::stopFaking();

    ClientFactory::fake([new Response(200, [], '{"id":1}')]);
    PHPay::make('mercadopago', ['accessToken' => 'TEST-1'])->createCharge(intentDeTeste());
    $mp = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);

    /* seis grafias do mesmo campo, e quem integra escreveu uma */
    expect($woovi['customer']['taxID'])->toBe('12345678909')
        ->and($mp['payer']['identification']['number'])->toBe('12345678909')
        ->and($mp['payer']['first_name'])->toBe('Maria');
})->group('support');

it('gera o correlationID da woovi quando ninguém deu referência', function () {
    ClientFactory::fake([new Response(200, [], '{"charge":{}}'), new Response(200, [], '{"charge":{}}')]);

    $woovi = PHPay::make('woovi', ['appId' => 'a']);
    $woovi->createCharge(intentDeTeste());
    $woovi->createCharge(intentDeTeste());

    $um   = json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true);
    $dois = json_decode((string) ClientFactory::recorded()[1]['request']->getBody(), true);

    expect($um['correlationID'])->not->toBeEmpty()
        ->and($um['correlationID'])->not->toBe($dois['correlationID']);
})->group('support');

it('respeita a referência que quem integra escolheu', function () {
    ClientFactory::fake([new Response(200, [], '{"charge":{}}')]);

    PHPay::make('woovi', ['appId' => 'a'])->createCharge(
        ChargeIntent::pix(Money::reais('10,00'), reference: 'pedido-42')
    );

    expect(json_decode((string) ClientFactory::recorded()[0]['request']->getBody(), true)['correlationID'])
        ->toBe('pedido-42');
})->group('support');

it('recusa vencimento onde o gateway expira por duração, em vez de inventar fuso', function (string $gateway, array $config) {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make($gateway, $config)->createCharge(intentDeTeste('2099-11-10')))
        ->toThrow(ValidationException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->with([
    ['woovi', ['appId' => 'a']],
    ['mercadopago', ['accessToken' => 'TEST-1']],
])->group('support');

it('exige vencimento no asaas, que não cria cobrança sem ele', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('asaas', ['token' => 't'])->createCharge(intentDeTeste()))
        ->toThrow(ValidationException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('recusa a capacidade em quem não declara, antes de qualquer HTTP', function () {
    ClientFactory::fake([]);

    expect(fn () => PHPay::make('rede', ['affiliation' => 'pv', 'secret' => 's'])->createCharge(intentDeTeste()))
        ->toThrow(NotImplementedException::class)
        ->and(ClientFactory::recorded())->toBe([]);
})->group('support');

it('declara só o que createCharge() aceita hoje', function (string $gateway, array $config) {
    expect(PHPay::make($gateway, $config)->supportedMethods())->toBe([Method::PIX]);
})->with([
    ['asaas', ['token' => 't']],
    ['woovi', ['appId'             => 'a']],
    ['mercadopago', ['accessToken' => 'TEST-1']],
])->group('support');
