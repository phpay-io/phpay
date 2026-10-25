<?php

use GuzzleHttp\Handler\MockHandler;
use PHPay\Asaas\Resources\Charge\Charge as AsaasCharge;
use PHPay\Http\ClientFactory;
use PHPay\PHPay;
use PHPay\Support\Gateway;

/* estado estático: nenhum teste pode deixar o modo falso ligado para o próximo */
afterEach(function () {
    ClientFactory::stopFaking();
});

it('responde da fila em vez da rede, para quem resolveu por nome', function () {
    ClientFactory::fake([jsonResponse(['id' => 'pay_001', 'status' => 'PENDING'])]);

    $phpay = PHPay::make('asaas', ['token' => 'fake-token']);

    $cobranca = $phpay->charge()
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-12-01'])
        ->create();

    expect($cobranca['id'])->toBe('pay_001');
})->group('phpay');

it('grava o que a aplicação mandou, que é o que o teste não sabe de antemão', function () {
    ClientFactory::fake([jsonResponse(['id' => 'pay_001'])]);

    PHPay::make('asaas', ['token' => 'fake-token'])->charge()
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-12-01'])
        ->create();

    $gravado = ClientFactory::recorded();

    expect($gravado)->toHaveCount(1)
        ->and($gravado[0]['request']->getMethod())->toBe('POST')
        ->and((string) $gravado[0]['request']->getUri())->toEndWith('/payments')
        ->and(json_decode((string) $gravado[0]['request']->getBody(), true))
        ->toBe(['customer' => 'cus_1', 'billingType' => 'PIX', 'value' => 100, 'dueDate' => '2026-12-01']);
})->group('phpay');

it('falseia os dois clients da rede, inclusive o host de oauth', function () {
    /*
     * é o caso que descartou "aceitar Client no registry": a Rede pede token
     * num host e gasta noutro, e pede antes de **cada** chamada. Um client
     * injetado falsearia metade, e a outra metade iria para a rede na primeira
     * linha do teste.
     */
    ClientFactory::fake([
        jsonResponse(['access_token' => 'tok', 'token_type' => 'Bearer', 'expires_in' => 3600]),
        jsonResponse(['returnCode' => '00', 'tid' => 'tid_1']),
    ]);

    $transacao = PHPay::make('rede', ['affiliation' => '123', 'secret' => 'segredo'])
        ->charge()
        ->find('tid_1');

    $gravado = ClientFactory::recorded();

    expect($transacao['returnCode'])->toBe('00')
        ->and($gravado)->toHaveCount(2);

    /* a primeira foi ao host de OAuth, a segunda ao de transações */
    expect((string) $gravado[0]['request']->getUri())->toContain('oauth')
        ->and((string) $gravado[1]['request']->getUri())->not->toContain('oauth');
})->group('phpay');

it('falseia os dois hosts da cielo, escrita e consulta', function () {
    ClientFactory::fake([
        jsonResponse(['Payment' => ['PaymentId' => 'guid-1']], 201),
        jsonResponse(['Payment' => ['Status' => 2]]),
    ]);

    $cielo = PHPay::make('cielo', ['merchantId' => 'm', 'merchantKey' => 'k'])->charge();

    $cielo->setCustomer(['Name' => 'Mário'])->setPix(1000)->create();
    $cielo->find('guid-1');

    $gravado = ClientFactory::recorded();

    /* hosts diferentes: api para escrever, apiquery para consultar */
    expect($gravado)->toHaveCount(2)
        ->and((string) $gravado[0]['request']->getUri())->toContain('//api')
        ->and((string) $gravado[1]['request']->getUri())->toContain('apiquery');
})->group('phpay');

it('mantém o retry ligado, porque falsear a rede não é desligar a política', function () {
    /* leitura com 500 e depois 200: a política repete e o teste vê as duas */
    ClientFactory::fake([
        jsonResponse(['erro' => 'instável'], 500),
        jsonResponse(['id' => 'pay_001']),
    ]);

    $cobranca = PHPay::make('asaas', ['token' => 'fake-token'])->charge()->find('pay_001');

    expect($cobranca['id'])->toBe('pay_001');
})->group('phpay');

it('aceita um MockHandler montado por quem chama', function () {
    $handler = new MockHandler([jsonResponse(['id' => 'pay_001'])]);

    expect(ClientFactory::fake($handler))->toBe($handler);

    $cobranca = PHPay::make('asaas', ['token' => 'fake'])->charge()->find('pay_001');

    expect($cobranca['id'])->toBe('pay_001')
        ->and(ClientFactory::recorded())->toHaveCount(1);
})->group('phpay');

it('responde se está falseando, e para quando pedido', function () {
    expect(ClientFactory::faking())->toBeFalse();

    ClientFactory::fake([jsonResponse([])]);
    expect(ClientFactory::faking())->toBeTrue();

    ClientFactory::stopFaking();
    expect(ClientFactory::faking())->toBeFalse()
        ->and(ClientFactory::recorded())->toBe([]);
})->group('phpay');

it('cada fake começa com o histórico limpo', function () {
    ClientFactory::fake([jsonResponse(['id' => 1])]);
    PHPay::make('asaas', ['token' => 'fake'])->charge()->find('1');

    expect(ClientFactory::recorded())->toHaveCount(1);

    ClientFactory::fake([jsonResponse(['id' => 2])]);

    expect(ClientFactory::recorded())->toBe([]);
})->group('phpay');

it('não falseia client que a fábrica não montou', function () {
    /*
     * a suíte da própria biblioteca injeta client nos Resources, e isso tem de
     * continuar valendo: o modo falso só muda o que a fábrica produz daqui em
     * diante, e client injetado não passa por ela.
     */
    ClientFactory::fake([jsonResponse(['id' => 'do-fake'])]);

    $history  = [];
    $injetado = new AsaasCharge('token', true, mockClient([jsonResponse(['id' => 'do-mock'])], $history));

    expect($injetado->find('pay_001')['id'])->toBe('do-mock')
        ->and(ClientFactory::recorded())->toBe([]);
})->group('phpay');

it('alcança os dez gateways, porque todos passam pela fábrica', function () {
    foreach (Gateway::cases() as $gateway) {
        ClientFactory::fake([jsonResponse(['ok' => true])]);

        $construido = $gateway->make(array_fill_keys($gateway->credentials(), 'fake'));

        expect($construido->name())->not->toBeEmpty();
    }

    expect(ClientFactory::faking())->toBeTrue();
})->group('phpay');
