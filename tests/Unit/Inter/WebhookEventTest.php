<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Inter\InterGateway;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\EventType;

/**
 * o exemplo "Sem Devolução" da referência do Inter, literal.
 *
 * @return array<mixed>
 */
function interPayload(): array
{
    return [[
        'endToEndId'       => 'E87654321202009091221dfghi123456',
        'chave'            => 'a82d0acf-6cad-42a1-b5d4-4165416545646845',
        'componentesValor' => ['original' => ['valor' => 100]],
        'txid'             => '971122d8f37211eaadc10242ac120002',
        'valor'            => '110.00',
        'horario'          => '2020-09-09T20:15:00.358Z',
        'infoPagador'      => '0123456789',
    ]];
}

it('lê a notificação do array na raiz, que é a forma do inter', function () {
    /*
     * **o teste que justifica a capacidade.** O padrão do BACEN e a Efí mandam
     * {"pix": [...]}; o Inter manda o array direto. Um parser escrito para o
     * envelope do padrão não acharia a chave `pix` e devolveria zero eventos
     * para todo pagamento de verdade.
     */
    $eventos = (new InterGateway('id', 'secret'))->webhookEvents(interPayload());

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->gateway)->toBe('Inter')
        ->and($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->status)->toBe(PaymentStatus::PAID)
        ->and($eventos[0]->resourceId)->toBe('971122d8f37211eaadc10242ac120002')
        ->and($eventos[0]->requiresLookup())->toBeFalse()
        ->and($eventos[0]->occurredAt?->format('Y-m-d H:i:s'))->toBe('2020-09-09 20:15:00');
})->group('inter');

it('aceita o corpo cru em string, que é o que php://input dá', function () {
    $eventos = (new InterGateway('id', 'secret'))
        ->webhookEvents((string) json_encode(interPayload()));

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->type)->toBe(EventType::PAID);
})->group('inter');

it('lê vários pagamentos numa notificação só', function () {
    /* é por isso que o retorno é lista: colapsar perderia pagamento em silêncio */
    $corpo = [interPayload()[0], ['txid' => 'segundo', 'valor' => '1.00', 'horario' => '2026-01-01T10:00:00Z']];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos)->toHaveCount(2)
        ->and($eventos[1]->resourceId)->toBe('segundo');
})->group('inter');

it('tolera o envelope pix do padrão, se um dia o inter alinhar', function () {
    /*
     * a própria "Evolução da API Pix" da referência reserva o direito de
     * acrescentar campos a qualquer momento. Custa duas linhas aceitar as duas
     * formas, e evita devolver zero eventos calado.
     */
    $eventos = (new InterGateway('id', 'secret'))->webhookEvents(['pix' => interPayload()]);

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->resourceId)->toBe('971122d8f37211eaadc10242ac120002');
})->group('inter');

it('lê devolucoes como objeto, que é como a referência documenta', function () {
    /*
     * no BACEN e na Efí `devolucoes` é array. O exemplo "Com Devolução" do
     * Inter traz um objeto. Pode ser erro de documentação deles, então as duas
     * formas passam — escolher uma e errar perderia a devolução.
     */
    $corpo                  = interPayload();
    $corpo[0]['txid']       = 'c3e0e7a4e7f1469a9f782d3d4999343c';
    $corpo[0]['devolucoes'] = [
        'id'      => '123ABC',
        'rtrId'   => 'D12345678202009091221abcdf098765',
        'valor'   => '10.00',
        'horario' => ['solicitacao' => '2020-09-09T20:15:00.358Z'],
        'status'  => 'DEVOLVIDO',
    ];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos)->toHaveCount(2)
        ->and($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[1]->type)->toBe(EventType::REFUNDED)
        ->and($eventos[1]->rawType)->toBe('DEVOLVIDO')
        ->and($eventos[1]->status)->toBe(PaymentStatus::REFUNDED)
        ->and($eventos[1]->resourceId)->toBe('123ABC')
        ->and($eventos[1]->occurredAt?->format('Y-m-d'))->toBe('2020-09-09');
})->group('inter');

it('lê devolucoes como array também', function () {
    $corpo                  = interPayload();
    $corpo[0]['devolucoes'] = [
        ['id' => 'dev1', 'valor' => '1.00', 'status' => 'DEVOLVIDO'],
        ['id' => 'dev2', 'valor' => '2.00', 'status' => 'EM_PROCESSAMENTO'],
    ];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos)->toHaveCount(3)
        ->and($eventos[1]->resourceId)->toBe('dev1')
        ->and($eventos[2]->resourceId)->toBe('dev2');
})->group('inter');

it('devolução em processamento não diz que o dinheiro voltou', function () {
    $corpo                  = interPayload();
    $corpo[0]['devolucoes'] = ['id' => 'dev1', 'valor' => '1.00', 'status' => 'EM_PROCESSAMENTO'];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    /* sem estado: requiresLookup() manda consultar em vez de liberar duas vezes */
    expect($eventos[1]->type)->toBe(EventType::REFUNDED)
        ->and($eventos[1]->rawType)->toBe('EM_PROCESSAMENTO')
        ->and($eventos[1]->status)->toBeNull()
        ->and($eventos[1]->requiresLookup())->toBeTrue();
})->group('inter');

it('devolução não realizada também fica sem estado', function () {
    $corpo                  = interPayload();
    $corpo[0]['devolucoes'] = ['id' => 'dev1', 'status' => 'NAO_REALIZADO'];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos[1]->status)->toBeNull();
})->group('inter');

it('o estorno não tenta adivinhar se foi parcial', function () {
    /*
     * distinguir exigiria comparar o valor devolvido com o da cobrança, e os
     * dois campos de valor do exemplo do Inter discordam em tipo e em ordem de
     * grandeza (componentesValor.original.valor: 100 contra valor: "110.00").
     * Aritmética sobre isso seria chute vestido de fato.
     */
    $corpo                  = interPayload();
    $corpo[0]['devolucoes'] = ['id' => 'dev1', 'valor' => '0.01', 'status' => 'DEVOLVIDO'];

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos[1]->type)->toBe(EventType::REFUNDED)
        ->and($eventos[1]->type)->not->toBe(EventType::PARTIALLY_REFUNDED);
})->group('inter');

it('cai no endToEndId quando não vem txid', function () {
    $corpo = interPayload();
    unset($corpo[0]['txid']);

    $eventos = (new InterGateway('id', 'secret'))->webhookEvents($corpo);

    expect($eventos[0]->resourceId)->toBe('E87654321202009091221dfghi123456');
})->group('inter');

it('o payload cru fica inteiro no evento', function () {
    $eventos = (new InterGateway('id', 'secret'))->webhookEvents(interPayload());

    /* campo que o envelope não lê continua alcançável */
    expect($eventos[0]->get('0.infoPagador'))->toBe('0123456789')
        ->and($eventos[0]->get('0.componentesValor.original.valor'))->toBe(100)
        ->and($eventos[0]->get('0.chave'))->toBe('a82d0acf-6cad-42a1-b5d4-4165416545646845');
})->group('inter');

it('corpo irreconhecível degrada em um evento desconhecido', function () {
    /*
     * o Inter reenvia uma notificação só quatro vezes. Devolver lista vazia
     * leria como "nada aconteceu"; um UNKNOWN com o payload deixa a integração
     * ver que algo chegou.
     */
    $eventos = (new InterGateway('id', 'secret'))->webhookEvents(['formato' => 'inesperado']);

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->type)->toBe(EventType::UNKNOWN)
        ->and($eventos[0]->payload)->toBe(['formato' => 'inesperado']);
})->group('inter');

it('entrada que não é objeto é ignorada, não derruba a notificação', function () {
    $eventos = (new InterGateway('id', 'secret'))->webhookEvents(['lixo', interPayload()[0]]);

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->type)->toBe(EventType::PAID);
})->group('inter');

it('corpo vazio é erro de integração, não notificação sem eventos', function () {
    $gateway = new InterGateway('id', 'secret');

    expect(fn () => $gateway->webhookEvents(''))
        ->toThrow(ValidationException::class, 'Corpo vazio')
        ->and(fn () => $gateway->webhookEvents([]))
        ->toThrow(ValidationException::class, 'Corpo vazio')
        ->and(fn () => $gateway->webhookEvents('não é json'))
        ->toThrow(ValidationException::class, 'JSON');
})->group('inter');
