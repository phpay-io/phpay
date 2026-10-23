<?php

use PHPay\Exceptions\ValidationException;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\{Event, EventRequest, EventType};

it('distingue não ter status de ter status desconhecido', function () {
    $semStatus = new Event(gateway: 'Cielo', type: EventType::UPDATED, resourceId: 'pay_1');
    $comStatus = new Event(gateway: 'Asaas', type: EventType::PAID, status: PaymentStatus::PAID);

    /*
     * é a distinção que motiva o requiresLookup(): a Cielo manda três campos e
     * nenhum estado. Responder PaymentStatus::UNKNOWN ali leria como estado
     * que o gateway mandou.
     */
    expect($semStatus->requiresLookup())->toBeTrue()
        ->and($semStatus->hasStatus())->toBeFalse()
        ->and($semStatus->status)->toBeNull();

    expect($comStatus->requiresLookup())->toBeFalse()
        ->and($comStatus->hasStatus())->toBeTrue();
})->group('phpay');

it('separa o que aconteceu do que a cobrança é', function () {
    /* tipo e status respondem perguntas diferentes, e podem divergir */
    $evento = new Event(
        gateway: 'Asaas',
        type: EventType::REFUNDED,
        status: PaymentStatus::REFUNDED,
    );

    expect($evento->type->isReversal())->toBeTrue()
        ->and($evento->type->isPayment())->toBeFalse()
        ->and($evento->status?->isRefunded())->toBeTrue();

    expect(EventType::CHARGEBACK->isReversal())->toBeTrue()
        ->and(EventType::PARTIALLY_REFUNDED->isReversal())->toBeTrue()
        ->and(EventType::PAID->isReversal())->toBeFalse()
        ->and(EventType::PAID->isPayment())->toBeTrue();
})->group('phpay');

it('dá rótulo em português a todo tipo de evento', function () {
    foreach (EventType::cases() as $type) {
        expect($type->label())->not->toBeEmpty();
    }
})->group('phpay');

it('alcança campo específico do gateway por caminho pontuado', function () {
    $evento = new Event(
        gateway: 'Efí',
        type: EventType::PAID,
        payload: ['pix' => [['endToEndId' => 'E12345', 'valor' => '10.00']]],
    );

    expect($evento->get('pix.0.endToEndId'))->toBe('E12345')
        ->and($evento->get('pix.0.valor'))->toBe('10.00')
        ->and($evento->get('pix.1.endToEndId'))->toBeNull()
        ->and($evento->get('nao.existe', 'padrão'))->toBe('padrão');
})->group('phpay');

it('guarda o payload cru intacto', function () {
    $corpo  = ['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_1', 'campoNovo' => 'x']];
    $evento = new Event(gateway: 'Asaas', type: EventType::PAID, payload: $corpo);

    /* a tradução cobre o envelope; o resto continua alcançável */
    expect($evento->payload)->toBe($corpo)
        ->and($evento->get('payment.campoNovo'))->toBe('x');
})->group('phpay');

it('recusa corpo vazio, que não é notificação sem eventos', function () {
    expect(fn () => EventRequest::decode('Asaas', ''))
        ->toThrow(ValidationException::class, 'Corpo vazio não é notificação sem eventos');

    expect(fn () => EventRequest::decode('Asaas', '   '))
        ->toThrow(ValidationException::class);

    expect(fn () => EventRequest::decode('Asaas', []))
        ->toThrow(ValidationException::class);
})->group('phpay');

it('recusa corpo que não é json de objeto', function () {
    expect(fn () => EventRequest::decode('Asaas', 'isto não é json'))
        ->toThrow(ValidationException::class, 'não é um JSON');

    expect(fn () => EventRequest::decode('Asaas', '"só uma string"'))
        ->toThrow(ValidationException::class);
})->group('phpay');

it('aceita o corpo cru e o já decodificado', function () {
    $cru      = '{"event":"PAYMENT_RECEIVED"}';
    $decodado = ['event' => 'PAYMENT_RECEIVED'];

    expect(EventRequest::decode('Asaas', $cru))->toBe($decodado)
        ->and(EventRequest::decode('Asaas', $decodado))->toBe($decodado);
})->group('phpay');

it('lê id que vem como número, sem cast de mixed', function () {
    /* o Mercado Pago manda data.id numérico nos próprios exemplos */
    expect(EventRequest::id(123456))->toBe('123456')
        ->and(EventRequest::id('abc'))->toBe('abc')
        ->and(EventRequest::id(''))->toBeNull()
        ->and(EventRequest::id(null))->toBeNull()
        ->and(EventRequest::id(['nao' => 'escalar']))->toBeNull();
})->group('phpay');

it('descarta data que não dá para interpretar, em vez de derrubar o evento', function () {
    expect(EventRequest::date('2026-10-23T12:00:00Z'))->not->toBeNull()
        ->and(EventRequest::date('data que não existe'))->toBeNull()
        ->and(EventRequest::date(''))->toBeNull()
        ->and(EventRequest::date(null))->toBeNull()
        ->and(EventRequest::date(12345))->toBeNull();
})->group('phpay');
