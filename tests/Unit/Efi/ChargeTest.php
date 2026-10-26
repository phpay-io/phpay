<?php

use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\ValidationException;

/**
 * um boleto montado com o atalho de item único.
 *
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @return Charge
 */
function efiCharge(array $responses, array &$history = []): Charge
{
    return new Charge(
        ['access_token' => 'tok', 'token_type' => 'Bearer'],
        [
            'value'       => 10000,
            'description' => 'Pedido 1234',
            'expire_at'   => date('Y-m-d', strtotime('+10 days')),
        ],
        true,
        mockClient($responses, $history),
    );
}

it('manda multa de 2% e juros de 0,033% ao dia quando ninguém configura', function () {
    /*
     * é o que este gateway sempre mandou, e o default fica: tirar faria boleto
     * de integração que nunca configurou nada parar de carregar encargo que o
     * lojista pode estar contando — mudança de comportamento numa minor.
     *
     * as duas escalas são DIFERENTES, e é a própria Efí que documenta:
     * fine é percentual × 100 (200 = 2%) e interest é × 1000 (33 = 0,033%).
     */
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->create();

    expect(recordedBody($history)['payment']['banking_billet']['configurations'])
        ->toBe(['fine' => 200, 'interest' => 33]);
})->group('efi');

it('deixa escolher multa, juros e baixa automática', function () {
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setConfigurations(['fine' => 250, 'interest' => 100, 'days_to_write_off' => 40])
        ->create();

    expect(recordedBody($history)['payment']['banking_billet']['configurations'])
        ->toBe(['fine' => 250, 'interest' => 100, 'days_to_write_off' => 40]);
})->group('efi');

it('deixa emitir boleto sem multa nem juros', function () {
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setConfigurations(['fine' => 0, 'interest' => 0])
        ->create();

    expect(recordedBody($history)['payment']['banking_billet']['configurations'])
        ->toBe(['fine' => 0, 'interest' => 0]);
})->group('efi');

it('array vazio não diz "sem encargo", e a mensagem aponta a saída', function () {
    /*
     * antes, array vazio caía no default em silêncio. E omitir o objeto não é
     * caminho: a Efí não documenta se `configurations` pode faltar.
     */
    $charge = efiCharge([]);

    expect(fn () => $charge->setConfigurations([]))
        ->toThrow(ValidationException::class, 'informe fine e interest como 0');
})->group('efi');

it('recusa decimal em multa e juros, explicando as duas escalas', function (array $configurations) {
    /* 2,5% de multa é 250, não 2.5 — a escala já é percentual × 100 */
    $charge = efiCharge([]);

    expect(fn () => $charge->setConfigurations($configurations))
        ->toThrow(ValidationException::class, 'escalas são DIFERENTES');
})->with([
    'multa decimal'   => [['fine' => 2.5]],
    'juros decimal'   => [['interest' => 0.033]],
    'multa em string' => [['fine' => '200']],
    'multa negativa'  => [['fine' => -1]],
])->group('efi');

it('aceita juros na forma de objeto, com o tipo que a efí documenta', function () {
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setConfigurations(['fine' => 200, 'interest' => ['value' => 330, 'type' => 'monthly']])
        ->create();

    /*
     * a escala da forma mensal a Efí NÃO documenta, então nada aqui converte:
     * o valor vai como foi escrito.
     */
    expect(recordedBody($history)['payment']['banking_billet']['configurations']['interest'])
        ->toBe(['value' => 330, 'type' => 'monthly']);
})->group('efi');

it('recusa tipo de juros que a efí não aceita', function () {
    $charge = efiCharge([]);

    expect(fn () => $charge->setConfigurations(['interest' => ['value' => 330, 'type' => 'weekly']]))
        ->toThrow(ValidationException::class, '"daily" ou "monthly"');
})->group('efi');

it('recusa baixa automática fora de 0 a 120 dias', function (mixed $dias) {
    $charge = efiCharge([]);

    expect(fn () => $charge->setConfigurations(['days_to_write_off' => $dias]))
        ->toThrow(ValidationException::class, '0 a 120');
})->with(['acima' => [121], 'negativo' => [-1], 'decimal' => [40.5]])->group('efi');

it('passa adiante campo de configurations que esta versão não modela', function () {
    /*
     * ao contrário do array de config do gateway, aqui chave desconhecida passa:
     * é payload da Efí, e recusar campo que funciona quebraria quem o manda.
     */
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setConfigurations(['fine' => 200, 'campo_novo_da_efi' => 'x'])
        ->create();

    expect(recordedBody($history)['payment']['banking_billet']['configurations'])
        ->toBe(['fine' => 200, 'campo_novo_da_efi' => 'x']);
})->group('efi');

it('emite boleto com mais de um item', function () {
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setItems([
            ['name' => 'Camiseta', 'value' => 4990, 'amount' => 2],
            ['name' => 'Frete', 'value' => 1500],
        ])
        ->create();

    expect(recordedBody($history)['items'])->toBe([
        ['name' => 'Camiseta', 'value' => 4990, 'amount' => 2],
        ['name' => 'Frete', 'value' => 1500],
    ]);
})->group('efi');

it('com itens explícitos, não exige description nem value da cobrança', function () {
    /*
     * o payload da Efí não tem valor no nível da cobrança — tem `items`. O
     * description e o value são o atalho de um item, então exigi-los junto com
     * itens seria pedir campo que não vai a lugar nenhum.
     */
    $history = [];

    $charge = new Charge(
        ['access_token' => 'tok', 'token_type' => 'Bearer'],
        ['expire_at' => date('Y-m-d', strtotime('+10 days'))],
        true,
        mockClient([jsonResponse(['data' => ['charge_id' => 1]])], $history),
    );

    $charge->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setItems([['name' => 'Camiseta', 'value' => 4990]])
        ->create();

    expect(recordedBody($history)['items'])->toBe([['name' => 'Camiseta', 'value' => 4990]]);
})->group('efi');

it('itens explícitos vencem o atalho de item único', function () {
    $history = [];

    efiCharge([jsonResponse(['data' => ['charge_id' => 1]])], $history)
        ->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901'])
        ->setItems([['name' => 'Outro', 'value' => 100]])
        ->create();

    expect(recordedBody($history)['items'])->toBe([['name' => 'Outro', 'value' => 100]]);
})->group('efi');

it('valida os itens antes de chamar', function (array $items, string $mensagem) {
    $charge = efiCharge([]);

    expect(fn () => $charge->setItems($items))
        ->toThrow(ValidationException::class, $mensagem);
})->with([
    'lista vazia'          => [[], 'lista não vazia'],
    'mapa em vez de lista' => [['a' => ['name' => 'x', 'value' => 1]], 'lista não vazia'],
    'item que não é array' => [['texto'], 'lista não vazia'],
    'sem nome'             => [[['value' => 100]], 'precisa de name'],
    'nome vazio'           => [[['name' => '  ', 'value' => 100]], 'precisa de name'],
    'valor decimal'        => [[['name' => 'x', 'value' => 49.90]], 'CENTAVOS'],
    'valor zero'           => [[['name' => 'x', 'value' => 0]], 'CENTAVOS'],
    'valor em string'      => [[['name' => 'x', 'value' => '100']], 'CENTAVOS'],
])->group('efi');

it('criar duas cobranças no mesmo Resource funciona', function () {
    /*
     * **regressão.** O getItems() dava unset() em description e value depois de
     * montar o item, então a segunda chamada falhava na validação — e a
     * mensagem culpava quem chamou por um campo que ele tinha preenchido.
     */
    $history = [];

    $charge = efiCharge([
        jsonResponse(['data' => ['charge_id' => 1]]),
        jsonResponse(['data' => ['charge_id' => 2]]),
    ], $history);

    $charge->setCustomer(['name' => 'Mário Lucas', 'cpf_cnpj' => '12345678901']);

    $primeira = $charge->create();
    $segunda  = $charge->create();

    expect($primeira['data']['charge_id'])->toBe(1)
        ->and($segunda['data']['charge_id'])->toBe(2)
        ->and(recordedBody($history, 0)['items'])->toBe(recordedBody($history, 1)['items'])
        ->and(recordedBody($history, 1)['items'][0])->toBe(['name' => 'Pedido 1234', 'value' => 10000]);
})->group('efi');

it('os setters devolvem a interface, para encadear', function () {
    $charge = efiCharge([]);

    expect($charge->setItems([['name' => 'x', 'value' => 100]]))->toBe($charge)
        ->and($charge->setConfigurations(['fine' => 0]))->toBe($charge);
})->group('efi');
