<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\{Request, Response};
use PHPay\Contracts\Capability;
use PHPay\Exceptions\{ApiException, NotImplementedException, ValidationException};
use PHPay\Http\ClientFactory;
use PHPay\PHPay;
use PHPay\Support\{Card, ChargeIntent, Customer, FallbackGateway, Gateway, Method, Money};

/*
| Cair para o próximo gateway é sobre dinheiro, não sobre resiliência. Se o
| erro não PROVA que a requisição não chegou, cair cria uma segunda cobrança —
| e entre dois PSPs não existe chave de idempotência que desfaça isso.
*/

afterEach(function () {
    ClientFactory::stopFaking();
});

function cadeia(?Closure $aoTentar = null): FallbackGateway
{
    return new FallbackGateway([
        Gateway::WOOVI->make(['appId' => 'a']),
        Gateway::MERCADO_PAGO->make(['accessToken' => 'TEST-1']),
    ], $aoTentar);
}

function intentPix(): ChargeIntent
{
    return ChargeIntent::pix(
        Money::reais('149,90'),
        Customer::make(name: 'Maria Oliveira', document: '123.456.789-09', email: 'maria@exemplo.com'),
    );
}

/**
 * a falha de transporte que o Guzzle 7 levanta, com o errno que diz se saiu.
 *
 * @param int $errno
 * @param float $connectTime
 * @return ConnectException
 */
function transporte(int $errno, float $connectTime = 0.0): ConnectException
{
    return new ConnectException(
        'cURL error ' . $errno,
        new Request('POST', 'payments'),
        null,
        ['errno' => $errno, 'connect_time' => $connectTime]
    );
}

it('cai quando ficou provado que a requisição não saiu', function (int $errno) {
    /*
     * o retry vem ANTES do fallback, e as camadas são diferentes: um erro que
     * provadamente não saiu é repetido no MESMO gateway três vezes, e só
     * depois de esgotado é que o próximo da lista entra. Por isso três falhas
     * na fila, não uma.
     */
    ClientFactory::fake([
        transporte($errno),
        transporte($errno),
        transporte($errno),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    $cobranca = PHPay::gateway(cadeia())->createCharge(intentPix());

    expect($cobranca)->toHaveKey('id')
        ->and((string) ClientFactory::recorded()[1]['request']->getUri())->toContain('mercadopago');
})->with([5, 6, 7, 35])->group('phpay');

it('NÃO cai num 5xx, porque o gateway pode ter criado a cobrança', function () {
    ClientFactory::fake([
        new Response(500, [], '{"errors":[{"description":"erro interno"}]}'),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    expect(fn () => PHPay::gateway(cadeia())->createCharge(intentPix()))
        ->toThrow(ApiException::class);

    /* uma tentativa só: a segunda resposta nem foi consumida */
    expect(ClientFactory::recorded())->toHaveCount(1);
})->group('phpay');

it('NÃO cai num timeout com a conexão já aberta', function () {
    ClientFactory::fake([transporte(28, 0.4), new Response(200, [], '{"id":"pay_1"}')]);

    expect(fn () => PHPay::gateway(cadeia())->createCharge(intentPix()))
        ->toThrow(ApiException::class);
})->group('phpay');

it('cai num timeout que nem chegou a conectar, depois do retry', function () {
    ClientFactory::fake([
        transporte(28, 0.0),
        transporte(28, 0.0),
        transporte(28, 0.0),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    expect(PHPay::gateway(cadeia())->createCharge(intentPix()))->toHaveKey('id');
})->group('phpay');

it('cai numa credencial recusada, que não processou nada', function (int $status) {
    ClientFactory::fake([
        new Response($status, [], '{"errors":[{"description":"credencial inválida"}]}'),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    expect(PHPay::gateway(cadeia())->createCharge(intentPix()))->toHaveKey('id')
        ->and(ClientFactory::recorded())->toHaveCount(2);
})->with([401, 403])->group('phpay');

it('NÃO cai num 4xx de payload, que erraria igual no próximo', function () {
    ClientFactory::fake([
        new Response(422, [], '{"errors":[{"description":"payload inválido"}]}'),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    expect(fn () => PHPay::gateway(cadeia())->createCharge(intentPix()))
        ->toThrow(ApiException::class)
        ->and(ClientFactory::recorded())->toHaveCount(1);
})->group('phpay');

it('cai quando a regra do gateway recusa antes de qualquer HTTP', function () {
    /*
     * a Woovi recusa vencimento (ela expira por duração) e o Asaas o exige.
     * Com uma cobrança COM vencimento, a Woovi recusa sem mandar nada, e o
     * Asaas atende — uma requisição só, porque a primeira nunca saiu.
     */
    ClientFactory::fake([
        new Response(200, [], '{"id":"cus_1"}'),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    $cadeia = new FallbackGateway([
        Gateway::WOOVI->make(['appId' => 'a']),
        Gateway::ASAAS->make(['token' => 't']),
    ]);

    $cobranca = $cadeia->createCharge(ChargeIntent::pix(
        Money::reais('149,90'),
        Customer::make(name: 'Maria Oliveira', document: '123.456.789-09', email: 'maria@exemplo.com'),
        dueDate: '2099-11-10',
    ));

    expect($cobranca['id'])->toBe('pay_1')
        ->and((string) ClientFactory::recorded()[0]['request']->getUri())->toContain('asaas');
})->group('phpay');

it('recusa antes de tentar quando nenhum da lista faz aquele meio', function () {
    ClientFactory::fake([]);

    $cadeia = new FallbackGateway([
        Gateway::WOOVI->make(['appId' => 'a']),
        Gateway::INTER->make(['clientId' => 'i', 'clientSecret' => 's']),
    ]);

    expect(fn () => $cadeia->createCharge(
        ChargeIntent::credito(
            Money::reais('10,00'),
            Customer::make(name: 'Maria'),
            Card::make('4111111111111111', 'MARIA', '07', '2030', '123'),
        )
    ))
        ->toThrow(ValidationException::class, 'Nenhum gateway desta lista cria cobrança por cartão de crédito')
        ->and(ClientFactory::recorded())->toBe([]);
})->group('phpay');

it('declara a união dos meios, porque escolhe por cobrança', function () {
    $cadeia = new FallbackGateway([
        Gateway::ASAAS->make(['token' => 't']),
        Gateway::REDE->make(['affiliation' => 'pv', 'secret' => 's']),
    ]);

    expect($cadeia->supportedMethods())
        ->toBe([Method::PIX, Method::BOLETO, Method::CREDIT_CARD]);
})->group('phpay');

it('declara só as capacidades de cobrança, nunca as dos gateways de dentro', function () {
    $phpay = PHPay::gateway(cadeia());

    expect($phpay->supports(Capability::CHARGE_INTENTS))->toBeTrue()
        ->and($phpay->supports(Capability::CHARGES))->toBeTrue()
        ->and($phpay->supports(Capability::CUSTOMERS))->toBeFalse()
        ->and($phpay->supports(Capability::REFUNDS))->toBeFalse()
        ->and(fn () => $phpay->refund('qualquer'))->toThrow(NotImplementedException::class);
})->group('phpay');

it('conta o que tentou e quem atendeu', function () {
    $tentativas = [];

    ClientFactory::fake([
        transporte(7),
        transporte(7),
        transporte(7),
        new Response(200, [], '{"id":"pay_1"}'),
    ]);

    PHPay::gateway(cadeia(function (string $gateway, ?Throwable $falha) use (&$tentativas) {
        $tentativas[] = $gateway . ': ' . ($falha === null ? 'ok' : 'falhou');
    }))->createCharge(intentPix());

    expect($tentativas)->toBe(['Woovi: falhou', 'Mercado Pago: ok']);
})->group('phpay');

it('recusa lista vazia e gateway que não declara a capacidade', function () {
    expect(fn () => new FallbackGateway([]))
        ->toThrow(ValidationException::class, FallbackGateway::messages()->empty)
        ->and(fn () => new FallbackGateway([new stdClass()]))
        ->toThrow(ValidationException::class, FallbackGateway::messages()->contract);
})->group('phpay');

it('nomeia a cadeia inteira nas mensagens', function () {
    expect(cadeia()->name())->toBe('Fallback (Woovi → Mercado Pago)');
})->group('phpay');
