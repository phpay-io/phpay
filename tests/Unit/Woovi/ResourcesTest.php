<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Woovi\Enums\PixKeyTypeEnum;
use PHPay\Woovi\Resources\Charge\Charge;
use PHPay\Woovi\Resources\Customer\Customer;
use PHPay\Woovi\Resources\Pix\Pix;
use PHPay\Woovi\Resources\Subscription\Subscription;
use PHPay\Woovi\Resources\Webhook\Webhook;

it('cria a cobrança com valor em centavos e correlationID', function () {
    $history = [];
    $client  = wooviClient([jsonResponse(['charge' => ['brCode' => '00020126...']])], $history);

    (new Charge('app-id', true, $client))
        ->setCorrelationId('pedido-1')
        ->setCustomer(['name' => 'Mário Lucas', 'email' => 'fale@phpay.io'])
        ->create(10050);

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/charge')
        ->and($body['value'])->toBe(10050)
        ->and($body['correlationID'])->toBe('pedido-1');
})->group('woovi');

it('gera correlationID quando não informado', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    (new Charge('app-id', true, $client))->create(500);

    expect(recordedBody($history)['correlationID'])->toStartWith('phpay_');
})->group('woovi');

it('extrai o copia-e-cola da cobrança', function () {
    $charge = new Charge('app-id', true, wooviClient([]));

    expect($charge->getPixCode(['charge' => ['brCode' => '00020126...']]))->toBe('00020126...')
        ->and($charge->getPixCode(['brCode' => 'direto']))->toBe('direto')
        ->and($charge->getPixCode(['charge' => []]))->toBeNull();
})->group('woovi');

it('endereça a cobrança pelo id do seu sistema', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([]), jsonResponse([])], $history);

    $charge = new Charge('app-id', true, $client);
    $charge->find('pedido-1');
    $charge->destroy('pedido-1');

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/charge/pedido-1')
        ->and($history[1]['request']->getMethod())->toBe('DELETE');
})->group('woovi');

it('registra chave pix aleatória sem informar a chave', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    (new Pix('app-id', true, $client))->createKey(PixKeyTypeEnum::RANDOM);

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/pix-keys')
        ->and(recordedBody($history))->toBe(['type' => 'EVP']);
})->group('woovi');

it('exige a chave nos tipos que não são aleatórios', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    expect(fn () => (new Pix('app-id', true, $client))->createKey(PixKeyTypeEnum::CPF))
        ->toThrow(ValidationException::class, 'exceto EVP');

    expect($history)->toBeEmpty();
})->group('woovi');

it('cria QR Code estático com e sem valor', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([]), jsonResponse([])], $history);

    $pix = new Pix('app-id', true, $client);
    $pix->staticQrCode('Caixa 1');
    $pix->staticQrCode('Caixa 2', 2500, 'caixa-2');

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/pixQrCode')
        ->and(recordedBody($history, 0))->toBe(['name' => 'Caixa 1'])
        ->and(recordedBody($history, 1))->toBe([
            'name' => 'Caixa 2', 'value' => 2500, 'correlationID' => 'caixa-2',
        ]);
})->group('woovi');

it('consulta uma chave pix antes de pagar', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    (new Pix('app-id', true, $client))->verifyKey('fale@phpay.io');

    expect((string) $history[0]['request']->getUri())
        ->toEndWith('/api/v1/pix-key-check/fale%40phpay.io');
})->group('woovi');

it('cadastra webhook por API, no prefixo de caminho próprio', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([]), jsonResponse([])], $history);

    (new Webhook('app-id', [], true, $client))->create([
        'name' => 'PHPay', 'url' => 'https://exemplo.test/webhook',
    ]);

    (new Webhook('app-id', [], true, $client))->getAll();

    $body = recordedBody($history);

    /* repare no api/openpix/v1, diferente do api/v1 dos outros recursos */
    expect((string) $history[0]['request']->getUri())->toEndWith('/api/openpix/v1/webhook')
        ->and($body['webhook']['isActive'])->toBeTrue()
        ->and($body['webhook']['url'])->toBe('https://exemplo.test/webhook')
        ->and((string) $history[1]['request']->getUri())->toContain('/api/openpix/v1/webhook');
})->group('woovi');

it('cria assinatura com dia de cobrança', function () {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    (new Subscription('app-id', true, $client))
        ->setCustomer(['name' => 'Mário Lucas', 'email' => 'fale@phpay.io'])
        ->setDayGenerateCharge(10)
        ->create(4990);

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/api/v1/subscriptions')
        ->and($body['value'])->toBe(4990)
        ->and($body['dayGenerateCharge'])->toBe(10);
})->group('woovi');

it('valida os payloads antes de chamar a API', function (callable $acao, string $esperado) {
    $history = [];
    $client  = wooviClient([jsonResponse([])], $history);

    expect(fn () => $acao($client))->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'cobrança com valor decimal' => [
        fn ($c) => (new Charge('app-id', true, $c))->setCharge(['value' => 100.50])->create(0),
        'CENTAVOS',
    ],
    'cliente sem identificador' => [
        fn ($c) => (new Customer('app-id', ['name' => 'Mário'], true, $c))->create(),
        'email, taxID ou phone',
    ],
    'webhook sem url' => [
        fn ($c) => (new Webhook('app-id', ['name' => 'X'], true, $c))->create(),
        'url é obrigatório',
    ],
    'assinatura sem cliente' => [
        fn ($c) => (new Subscription('app-id', true, $c))->create(1000),
        'precisa de um customer',
    ],
    'dia de cobrança fora do mês' => [
        fn ($c) => (new Subscription('app-id', true, $c))
            ->setCustomer(['name' => 'M', 'email' => 'a@b.com'])
            ->setDayGenerateCharge(45)
            ->create(1000),
        'entre 1 e 31',
    ],
])->group('woovi');
