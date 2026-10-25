<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Inter\Requests\InterPixChargeRequest;
use PHPay\Inter\Resources\Charge\Charge;
use PHPay\Support\{Customer, Money};

it('cria cobrança imediata sem txid, deixando o inter gerar', function () {
    $history = [];

    $cobranca = interGateway([jsonResponse(['txid' => 'gerado-pelo-inter', 'status' => 'ATIVA'])], $history)
        ->charge()
        ->setKey('a82d0acf-6cad-42a1-b5d4-416541654564')
        ->setAmount(Money::reais('110,00'))
        ->create();

    expect($cobranca['txid'])->toBe('gerado-pelo-inter')
        ->and($history[1]['request']->getMethod())->toBe('POST')
        ->and((string) $history[1]['request']->getUri())->toEndWith('pix/v2/cob')
        ->and(recordedBody($history, 1))->toBe([
            'chave' => 'a82d0acf-6cad-42a1-b5d4-416541654564',
            'valor' => ['original' => '110.00'],
        ]);
})->group('inter');

it('cria cobrança imediata com txid próprio, por put', function () {
    $history = [];
    $txid    = 'pedido1234567890123456789012';

    interGateway([jsonResponse(['txid' => $txid])], $history)
        ->charge()
        ->setKey('chave@phpay.io')
        ->setAmount(Money::reais(10))
        ->setDescription('Pedido 1234')
        ->create($txid);

    expect($history[1]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[1]['request']->getUri())->toEndWith("pix/v2/cob/{$txid}")
        ->and(recordedBody($history, 1)['solicitacaoPagador'])->toBe('Pedido 1234')
        ->and(recordedBody($history, 1)['valor']['original'])->toBe('10.00');
})->group('inter');

it('o valor vai em reais com duas casas, que é o que a api pix quer', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->setKey('chave')
        ->setAmount(Money::centavos(11050))
        ->create();

    /* centavos entram, reais saem: é o Money que resolve a unidade */
    expect(recordedBody($history, 1)['valor']['original'])->toBe('110.50');
})->group('inter');

it('setDueDate transforma a cobrança em cobv e gera txid quando falta', function () {
    $history = [];

    interGateway([jsonResponse(['txid' => 'x'])], $history)
        ->charge()
        ->setKey('chave')
        ->setAmount(Money::reais(50))
        ->setCustomer(['nome' => 'Fulano de Teste', 'cpf' => '12345678901'])
        ->setDueDate('2026-12-01', 15)
        ->create();

    $corpo = recordedBody($history, 1);

    expect($history[1]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[1]['request']->getUri())->toContain('pix/v2/cobv/')
        ->and($corpo['calendario'])->toBe(['dataDeVencimento' => '2026-12-01', 'validadeAposVencimento' => 15]);

    /* o txid gerado respeita o padrão do BACEN */
    $gerado = basename((string) $history[1]['request']->getUri());

    expect(preg_match(InterPixChargeRequest::TXID, $gerado))->toBe(1);
})->group('inter');

it('expiração e vencimento são exclusivos: o último vence', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->setKey('chave')
        ->setAmount(Money::reais(10))
        ->setDueDate('2026-12-01')
        ->setExpiration(3600)
        ->create();

    /* virou imediata de novo, então foi por POST em cob */
    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/cob')
        ->and(recordedBody($history, 1)['calendario'])->toBe(['expiracao' => 3600]);
})->group('inter');

it('mapeia o Customer da biblioteca para o devedor do BACEN', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->setKey('chave')
        ->setAmount(Money::reais(10))
        ->setCustomer(Customer::make(name: 'Fulano de Teste', document: '123.456.789-01'))
        ->create();

    expect(recordedBody($history, 1)['devedor'])->toBe([
        'nome' => 'Fulano de Teste',
        'cpf'  => '12345678901',
    ]);
})->group('inter');

it('informação adicional vai como lista de nome e valor', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->setKey('chave')
        ->setAmount(Money::reais(10))
        ->setAdditionalInfo(['Pedido' => '1234', 'Vendedor' => 'Loja A'])
        ->create();

    expect(recordedBody($history, 1)['infoAdicionais'])->toBe([
        ['nome' => 'Pedido', 'valor' => '1234'],
        ['nome' => 'Vendedor', 'valor' => 'Loja A'],
    ]);
})->group('inter');

it('valida o payload antes de qualquer chamada', function (callable $montar, string $mensagem) {
    $history = [];
    $charge  = interGateway([], $history)->charge();

    expect(fn () => $montar($charge))->toThrow(ValidationException::class, $mensagem);

    /* só o token saiu; a cobrança não chegou à rede */
    expect($history)->toHaveCount(1);
})->with([
    'sem valor' => [
        fn (Charge $c) => $c->setKey('chave')->create(),
        'setAmount',
    ],
    'sem chave' => [
        fn (Charge $c) => $c->setAmount(Money::reais(10))->create(),
        'chave Pix',
    ],
    'cobv sem devedor' => [
        fn (Charge $c) => $c->setKey('chave')->setAmount(Money::reais(10))->setDueDate('2026-12-01')->create(),
        'exige devedor',
    ],
    'txid fora do padrão' => [
        fn (Charge $c) => $c->setKey('chave')->setAmount(Money::reais(10))->create('curto'),
        '26 a 35',
    ],
    'devedor sem documento' => [
        fn (Charge $c) => $c->setCustomer(['nome' => 'Fulano']),
        'cpf ou de cnpj',
    ],
    'devedor com cpf e cnpj' => [
        fn (Charge $c) => $c->setCustomer(['nome' => 'F', 'cpf' => '12345678901', 'cnpj' => '12345678901234']),
        'nunca os dois',
    ],
])->group('inter');

it('consulta cobrança imediata e com vencimento em caminhos distintos', function () {
    $history = [];
    $gateway = interGateway([jsonResponse(['status' => 'ATIVA']), jsonResponse(['status' => 'ATIVA'])], $history);

    $gateway->charge()->find('abc');
    $gateway->charge()->findDue('def');

    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/cob/abc')
        ->and((string) $history[2]['request']->getUri())->toEndWith('pix/v2/cobv/def');
})->group('inter');

it('a listagem manda o período que a api exige, com 30 dias por padrão', function () {
    $history = [];

    interGateway([jsonResponse(['cobs' => []])], $history)->charge()->getAll();

    $query = [];
    parse_str($history[1]['request']->getUri()->getQuery(), $query);

    expect($query)->toHaveKeys(['inicio', 'fim'])
        ->and($query['inicio'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
})->group('inter');

it('filtro informado sobrescreve o período padrão', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->setQueryParams(['inicio' => '2026-01-01T00:00:00Z', 'fim' => '2026-01-31T00:00:00Z'])
        ->getAllDue();

    $query = [];
    parse_str($history[1]['request']->getUri()->getQuery(), $query);

    expect((string) $history[1]['request']->getUri())->toContain('pix/v2/cobv')
        ->and($query['inicio'])->toBe('2026-01-01T00:00:00Z');
})->group('inter');

it('cancelar é patch com o status de remoção do BACEN', function () {
    $history = [];
    $gateway = interGateway([jsonResponse([]), jsonResponse([])], $history);

    $gateway->charge()->cancel('abc');
    $gateway->charge()->cancelDue('def');

    expect($history[1]['request']->getMethod())->toBe('PATCH')
        ->and(recordedBody($history, 1))->toBe(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'])
        ->and((string) $history[2]['request']->getUri())->toEndWith('pix/v2/cobv/def')
        ->and(recordedBody($history, 2))->toBe(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR']);
})->group('inter');

it('revisar manda o payload como veio', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->update('abc', ['valor' => ['original' => '99.90']]);

    expect($history[1]['request']->getMethod())->toBe('PATCH')
        ->and(recordedBody($history, 1))->toBe(['valor' => ['original' => '99.90']]);
})->group('inter');

it('a devolução é endereçada pelo endToEndId e manda o valor', function () {
    $history = [];

    interGateway([jsonResponse(['status' => 'EM_PROCESSAMENTO'])], $history)
        ->charge()
        ->refund('E87654321202009091221dfghi123456', Money::reais('10,00'), 'devolucao1');

    expect($history[1]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[1]['request']->getUri())
        ->toEndWith('pix/v2/pix/E87654321202009091221dfghi123456/devolucao/devolucao1')
        ->and(recordedBody($history, 1))->toBe(['valor' => '10.00']);
})->group('inter');

it('a devolução gera id quando não recebe um', function () {
    $history = [];

    interGateway([jsonResponse([])], $history)
        ->charge()
        ->refund('E876543212020090912', Money::reais(1));

    $id = basename((string) $history[1]['request']->getUri());

    expect(preg_match(InterPixChargeRequest::TXID, $id))->toBe(1);
})->group('inter');

it('consulta pix recebido e devolução', function () {
    $history = [];
    $gateway = interGateway([jsonResponse([]), jsonResponse([]), jsonResponse([])], $history);

    $gateway->charge()->findPix('E123');
    $gateway->charge()->findRefund('E123', 'dev1');
    $gateway->charge()->getAllPix();

    expect((string) $history[1]['request']->getUri())->toEndWith('pix/v2/pix/E123')
        ->and((string) $history[2]['request']->getUri())->toEndWith('pix/v2/pix/E123/devolucao/dev1')
        ->and((string) $history[3]['request']->getUri())->toContain('pix/v2/pix?');
})->group('inter');

it('não inventa qrCode, porque o inter não expõe esse endpoint', function () {
    /*
     * a Efí tem `GET /loc/{id}/qrcode` e o Resource dela tem qrCode(). O Inter
     * não expõe esse caminho — o copia-e-cola volta na própria cobrança, em
     * pixCopiaECola —, então aqui não existe método para isso.
     */
    expect(method_exists(Charge::class, 'qrCode'))->toBeFalse();
})->group('inter');
