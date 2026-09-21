<?php

use PHPay\Efi\Resources\PixCharge\PixCharge;
use PHPay\Exceptions\ValidationException;
use PHPay\Support\{Customer, Money};

/**
 * Pix charge resource on a mocked client.
 *
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @return PixCharge
 */
function pixCharge(array $responses, array &$history = []): PixCharge
{
    return new PixCharge(['access_token' => 'tok', 'token_type' => 'Bearer'], null, true, efiPixClient($responses, $history));
}

it('cria cobrança imediata com o valor em reais como string', function () {
    $history = [];

    pixCharge([jsonResponse(['txid' => 'abc', 'loc' => ['id' => 7]])], $history)
        ->setAmount(Money::reais('123,45'))
        ->setKey('chave@phpay.io')
        ->setCustomer(new Customer('Mário Lucas', '12345678909'))
        ->setDescription('Pedido 1234')
        ->setExpiration(1800)
        ->setAdditionalInfo(['Pedido' => '1234', 'Loja' => 'Centro'])
        ->create();

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toBe('https://pix-h.api.efipay.com.br/v2/cob')
        ->and(recordedBody($history))->toBe([
            'valor'              => ['original' => '123.45'],
            'chave'              => 'chave@phpay.io',
            'devedor'            => ['nome' => 'Mário Lucas', 'cpf' => '12345678909'],
            'solicitacaoPagador' => 'Pedido 1234',
            'calendario'         => ['expiracao' => 1800],
            'infoAdicionais'     => [
                ['nome' => 'Pedido', 'valor' => '1234'],
                ['nome' => 'Loja', 'valor' => 'Centro'],
            ],
        ]);
})->group('efi');

it('usa PUT com o txid informado', function () {
    $history = [];
    $txid    = str_repeat('a1', 16);

    pixCharge([jsonResponse(['txid' => $txid])], $history)
        ->setAmount(Money::centavos(1))
        ->setKey('chave')
        ->create($txid);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith("v2/cob/{$txid}")
        ->and(recordedBody($history)['valor'])->toBe(['original' => '0.01']);
})->group('efi');

it('recusa txid fora do padrão do BACEN sem chamar a API', function () {
    $history = [];

    expect(fn () => pixCharge([], $history)->setAmount(Money::reais(10))->setKey('chave')->create('curto'))
        ->toThrow(ValidationException::class, '26 a 35 caracteres');

    expect($history)->toBeEmpty();
})->group('efi');

it('só aceita Money, porque a API Pix quer reais e a de Cobranças quer centavos', function () {
    expect(fn () => pixCharge([])->setAmount(1000))->toThrow(TypeError::class);
})->group('efi');

it('exige valor e chave', function () {
    expect(fn () => pixCharge([])->setKey('chave')->create())
        ->toThrow(ValidationException::class, 'setAmount')
        ->and(fn () => pixCharge([])->setAmount(Money::reais(10))->create())
        ->toThrow(ValidationException::class, 'setKey');
})->group('efi');

it('cria cobrança com vencimento, gerando o txid que a cobv exige', function () {
    $history = [];

    pixCharge([jsonResponse(['txid' => 'x'])], $history)
        ->setAmount(Money::reais(250))
        ->setKey('chave')
        ->setCustomer(new Customer('Sixtec LTDA', '12345678000199'))
        ->setDueDate('2026-12-31', 15)
        ->create();

    $uri = (string) $history[0]['request']->getUri();

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and($uri)->toMatch('#/v2/cobv/[a-f0-9]{32}$#')
        ->and(recordedBody($history)['calendario'])->toBe([
            'dataDeVencimento'       => '2026-12-31',
            'validadeAposVencimento' => 15,
        ])
        ->and(recordedBody($history)['devedor'])->toBe(['nome' => 'Sixtec LTDA', 'cnpj' => '12345678000199']);
})->group('efi');

it('exige devedor e data válida na cobrança com vencimento', function () {
    expect(fn () => pixCharge([])->setAmount(Money::reais(10))->setKey('chave')->setDueDate('2026-12-31')->create())
        ->toThrow(ValidationException::class, 'exige devedor')
        ->and(fn () => pixCharge([])
            ->setAmount(Money::reais(10))
            ->setKey('chave')
            ->setCustomer(['nome' => 'Mário', 'cpf' => '12345678909'])
            ->setDueDate('2026-02-30')
            ->create())
        ->toThrow(ValidationException::class, 'data válida');
})->group('efi');

it('volta a ser imediata quando a expiração vem depois do vencimento', function () {
    $history = [];

    pixCharge([jsonResponse([])], $history)
        ->setAmount(Money::reais(10))
        ->setKey('chave')
        ->setDueDate('2026-12-31')
        ->setExpiration(600)
        ->create();

    expect((string) $history[0]['request']->getUri())->toEndWith('v2/cob')
        ->and(recordedBody($history)['calendario'])->toBe(['expiracao' => 600]);
})->group('efi');

it('recusa devedor com cpf e cnpj ao mesmo tempo', function () {
    expect(fn () => pixCharge([])->setCustomer(['nome' => 'X', 'cpf' => '12345678909', 'cnpj' => '12345678000199']))
        ->toThrow(ValidationException::class, 'nunca os dois');
})->group('efi');

it('cancela pelo status do padrão BACEN, na cob e na cobv', function () {
    $history = [];
    $charge  = pixCharge([jsonResponse([]), jsonResponse([])], $history);

    $charge->cancel('txid-cob');
    $charge->cancelDue('txid-cobv');

    expect($history[0]['request']->getMethod())->toBe('PATCH')
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/cob/txid-cob')
        ->and(recordedBody($history, 0))->toBe(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'])
        ->and((string) $history[1]['request']->getUri())->toEndWith('v2/cobv/txid-cobv')
        ->and(recordedBody($history, 1))->toBe(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR']);
})->group('efi');

it('lista com o período obrigatório dos últimos 30 dias por padrão', function () {
    $history = [];
    $charge  = pixCharge([jsonResponse(['cobs' => []]), jsonResponse(['cobs' => []])], $history);

    $charge->getAll();
    $charge->setQueryParams(['inicio' => '2026-01-01T00:00:00Z', 'fim' => '2026-01-31T23:59:59Z', 'status' => 'ATIVA'])
        ->getAllDue();

    parse_str($history[0]['request']->getUri()->getQuery(), $padrao);
    parse_str($history[1]['request']->getUri()->getQuery(), $filtrado);

    expect($padrao)->toHaveKeys(['inicio', 'fim'])
        ->and($padrao['inicio'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/')
        ->and((string) $history[1]['request']->getUri()->getPath())->toBe('/v2/cobv')
        ->and($filtrado)->toBe(['inicio' => '2026-01-01T00:00:00Z', 'fim' => '2026-01-31T23:59:59Z', 'status' => 'ATIVA']);
})->group('efi');

it('busca o QR Code pelo id do location', function () {
    $history = [];

    $qr = pixCharge([jsonResponse(['qrcode' => '000201...', 'imagemQrcode' => 'data:image/png;base64,...'])], $history)
        ->qrCode(7);

    expect($qr['qrcode'])->toBe('000201...')
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/loc/7/qrcode');
})->group('efi');

it('devolve um Pix recebido com o valor em reais', function () {
    $history = [];
    $charge  = pixCharge([jsonResponse(['status' => 'EM_PROCESSAMENTO']), jsonResponse([])], $history);

    $charge->refund('E12345678202609211200abcdefghijk', Money::reais(10), 'D1');
    $charge->refund('E12345678202609211200abcdefghijk', Money::centavos(50));

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith('v2/pix/E12345678202609211200abcdefghijk/devolucao/D1')
        ->and(recordedBody($history, 0))->toBe(['valor' => '10.00'])
        ->and((string) $history[1]['request']->getUri())->toMatch('#/devolucao/[a-f0-9]{32}$#')
        ->and(recordedBody($history, 1))->toBe(['valor' => '0.50']);
})->group('efi');

it('chega à cobrança Pix pelo gateway, com o token da API Pix', function () {
    $history = [];

    efiPixGateway([jsonResponse(['txid' => 'abc'])], $history)
        ->pixCharge()
        ->setAmount(Money::reais(1))
        ->setKey('chave')
        ->create();

    expect((string) $history[0]['request']->getUri())->toEndWith('oauth/token')
        ->and((string) $history[1]['request']->getUri())->toEndWith('v2/cob');
})->group('efi');
