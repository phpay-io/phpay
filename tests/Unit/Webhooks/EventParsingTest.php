<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\Asaas\AsaasGateway;
use PHPay\Cielo\CieloGateway;
use PHPay\Contracts\Capability;
use PHPay\Efi\Requests\EfiWebhookEventRequest;
use PHPay\Exceptions\NotImplementedException;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagBank\PagBankGateway;
use PHPay\PHPay;
use PHPay\Support\PaymentStatus;
use PHPay\Webhooks\EventType;
use PHPay\Woovi\WooviGateway;

it('lê a notificação do asaas inteira, sem precisar consultar de volta', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));

    $eventos = $gateway->webhookEvents([
        'event'   => 'PAYMENT_RECEIVED',
        'payment' => ['id' => 'pay_001', 'status' => 'RECEIVED', 'value' => 100.5],
    ]);

    expect($eventos)->toHaveCount(1);

    $evento = $eventos[0];

    expect($evento->type)->toBe(EventType::PAID)
        ->and($evento->rawType)->toBe('PAYMENT_RECEIVED')
        ->and($evento->resourceId)->toBe('pay_001')
        ->and($evento->status)->toBe(PaymentStatus::PAID)
        ->and($evento->requiresLookup())->toBeFalse()
        ->and($evento->get('payment.value'))->toBe(100.5);
})->group('asaas');

it('no asaas, confirmada e recebida são o mesmo evento de pagamento', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));

    $confirmada = $gateway->webhookEvents(['event' => 'PAYMENT_CONFIRMED', 'payment' => ['id' => 'p1', 'status' => 'CONFIRMED']]);
    $recebida   = $gateway->webhookEvents(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'p1', 'status' => 'RECEIVED']]);

    /* a diferença é liquidação, não se o pagador pagou */
    expect($confirmada[0]->type)->toBe(EventType::PAID)
        ->and($recebida[0]->type)->toBe(EventType::PAID)
        ->and($confirmada[0]->rawType)->not->toBe($recebida[0]->rawType);
})->group('asaas');

it('lê o estorno e a disputa do asaas como eventos diferentes', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));

    $estorno = $gateway->webhookEvents(['event' => 'PAYMENT_REFUNDED', 'payment' => ['id' => 'p1', 'status' => 'REFUNDED']]);
    $parcial = $gateway->webhookEvents(['event' => 'PAYMENT_PARTIALLY_REFUNDED', 'payment' => ['id' => 'p1']]);
    $disputa = $gateway->webhookEvents(['event' => 'PAYMENT_CHARGEBACK_REQUESTED', 'payment' => ['id' => 'p1']]);

    expect($estorno[0]->type)->toBe(EventType::REFUNDED)
        ->and($parcial[0]->type)->toBe(EventType::PARTIALLY_REFUNDED)
        ->and($disputa[0]->type)->toBe(EventType::DISPUTE_OPENED);
})->group('asaas');

it('endereça a cobrança da woovi pelo correlationID', function () {
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    $eventos = $gateway->webhookEvents([
        'event'  => 'OPENPIX:CHARGE_COMPLETED',
        'charge' => ['correlationID' => 'pedido-1', 'identifier' => '6f1a2b', 'status' => 'COMPLETED'],
    ]);

    /* o correlationID é o id do sistema de quem integra, e é o que find() aceita */
    expect($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->resourceId)->toBe('pedido-1')
        ->and($eventos[0]->status)->toBe(PaymentStatus::PAID);
})->group('woovi');

it('na woovi, pagador diferente do cliente ainda é pagamento', function () {
    $gateway = new WooviGateway('app-id', true, wooviClient([]));

    $eventos = $gateway->webhookEvents([
        'event'  => 'OPENPIX:CHARGE_COMPLETED_NOT_SAME_CUSTOMER_PAYER',
        'charge' => ['correlationID' => 'pedido-1', 'status' => 'COMPLETED'],
    ]);

    expect($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->rawType)->toBe('OPENPIX:CHARGE_COMPLETED_NOT_SAME_CUSTOMER_PAYER');
})->group('woovi');

it('lê os dois níveis de evento do pagar.me', function () {
    $gateway = new PagarMeGateway('sk_test_abc', pagarmeClient([]));

    $pedido   = $gateway->webhookEvents(['type' => 'order.paid', 'data' => ['id' => 'or_1', 'status' => 'paid']]);
    $cobranca = $gateway->webhookEvents(['type' => 'charge.paid', 'data' => ['id' => 'ch_1', 'status' => 'paid']]);

    expect($pedido[0]->type)->toBe(EventType::PAID)
        ->and($pedido[0]->resourceId)->toBe('or_1')
        ->and($cobranca[0]->type)->toBe(EventType::PAID)
        ->and($cobranca[0]->resourceId)->toBe('ch_1');
})->group('pagarme');

it('no pagar.me, os dois nomes de chargeback lêem igual durante a migração', function () {
    $gateway = new PagarMeGateway('sk_test_abc', pagarmeClient([]));

    $antigo = $gateway->webhookEvents(['type' => 'charge.chargedback', 'data' => ['id' => 'ch_1']]);
    $novo   = $gateway->webhookEvents(['type' => 'chargeback.received', 'data' => ['id' => 'ch_1']]);

    expect($antigo[0]->type)->toBe(EventType::CHARGEBACK)
        ->and($novo[0]->type)->toBe(EventType::CHARGEBACK);
})->group('pagarme');

it('no pagbank, cada cobrança do pedido vira um evento', function () {
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    $eventos = $gateway->webhookEvents([
        'id'      => 'ORDE_1',
        'charges' => [
            ['id' => 'CHAR_1', 'status' => 'PAID'],
            ['id' => 'CHAR_2', 'status' => 'DECLINED'],
        ],
    ]);

    /*
     * colapsar num evento só esconderia a cobrança recusada ao lado da paga —
     * e o PagBank não manda campo de evento, então o tipo sai do status.
     */
    expect($eventos)->toHaveCount(2)
        ->and($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->resourceId)->toBe('CHAR_1')
        ->and($eventos[1]->type)->toBe(EventType::FAILED)
        ->and($eventos[1]->resourceId)->toBe('CHAR_2')
        ->and($eventos[1]->status)->toBe(PaymentStatus::FAILED);
})->group('pagbank');

it('no pagbank, pedido sem cobrança ainda devolve um evento', function () {
    $gateway = new PagBankGateway('token', true, pagbankClient([]));

    $eventos = $gateway->webhookEvents(['id' => 'ORDE_1']);

    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->type)->toBe(EventType::UPDATED)
        ->and($eventos[0]->resourceId)->toBe('ORDE_1')
        ->and($eventos[0]->requiresLookup())->toBeTrue();
})->group('pagbank');

it('na efí, uma notificação traz vários pix recebidos', function () {
    $eventos = EfiWebhookEventRequest::parse([
        'pix' => [
            ['endToEndId' => 'E1', 'txid' => 'tx1', 'valor' => '10.00', 'horario' => '2026-10-23T12:00:00Z'],
            ['endToEndId' => 'E2', 'txid' => 'tx2', 'valor' => '20.00', 'horario' => '2026-10-23T12:05:00Z'],
        ],
    ]);

    /* é o gateway que obrigou o parsing a devolver lista */
    expect($eventos)->toHaveCount(2)
        ->and($eventos[0]->type)->toBe(EventType::PAID)
        ->and($eventos[0]->resourceId)->toBe('tx1')
        ->and($eventos[0]->status)->toBe(PaymentStatus::PAID)
        ->and($eventos[0]->occurredAt)->not->toBeNull()
        ->and($eventos[1]->resourceId)->toBe('tx2')
        ->and($eventos[1]->get('pix.1.endToEndId'))->toBe('E2');
})->group('efi');

it('na efí, devolução em processamento não é devolução feita', function () {
    $feita = EfiWebhookEventRequest::parse([
        'devolucoes' => [['id' => 'd1', 'status' => 'DEVOLVIDO']],
    ]);

    $andando = EfiWebhookEventRequest::parse([
        'devolucoes' => [['id' => 'd2', 'status' => 'EM_PROCESSAMENTO']],
    ]);

    expect($feita[0]->type)->toBe(EventType::REFUNDED)
        ->and($feita[0]->status)->toBe(PaymentStatus::REFUNDED);

    /* o evento é de estorno, mas o dinheiro ainda não voltou */
    expect($andando[0]->type)->toBe(EventType::REFUNDED)
        ->and($andando[0]->status)->toBeNull()
        ->and($andando[0]->requiresLookup())->toBeTrue()
        ->and($andando[0]->rawType)->toBe('EM_PROCESSAMENTO');
})->group('efi');

it('na api de cobranças da efí, o corpo é um token para trocar', function () {
    $eventos = EfiWebhookEventRequest::parse(['notification' => 'token-de-notificacao']);

    /* não é o id da cobrança: precisa ser trocado pelo detalhe */
    expect($eventos)->toHaveCount(1)
        ->and($eventos[0]->resourceId)->toBe('token-de-notificacao')
        ->and($eventos[0]->requiresLookup())->toBeTrue();
})->group('efi');

it('no mercado pago, a notificação só diz qual recurso mudou', function () {
    $gateway = new MercadoPagoGateway('TEST-token', mpClient([]));

    $eventos = $gateway->webhookEvents([
        'type'         => 'payment',
        'action'       => 'payment.updated',
        'data'         => ['id' => 123456789],
        'date_created' => '2026-10-23T12:00:00Z',
    ]);

    /* sem status e sem valor: o id é o que charge()->find() aceita */
    expect($eventos[0]->type)->toBe(EventType::UPDATED)
        ->and($eventos[0]->resourceId)->toBe('123456789')
        ->and($eventos[0]->status)->toBeNull()
        ->and($eventos[0]->requiresLookup())->toBeTrue()
        ->and($eventos[0]->occurredAt)->not->toBeNull();
})->group('mercadopago');

it('no mercado pago, criação e atualização se distinguem pelo sufixo', function () {
    $gateway = new MercadoPagoGateway('TEST-token', mpClient([]));

    $criada     = $gateway->webhookEvents(['action' => 'payment.created', 'data' => ['id' => '1']]);
    $atualizada = $gateway->webhookEvents(['action' => 'payment.updated', 'data' => ['id' => '1']]);

    expect($criada[0]->type)->toBe(EventType::CREATED)
        ->and($atualizada[0]->type)->toBe(EventType::UPDATED);
})->group('mercadopago');

it('na cielo, três campos e nenhum estado', function () {
    $gateway = new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([]));

    $eventos = $gateway->webhookEvents(['PaymentId' => 'guid-da-venda', 'ChangeType' => 1]);

    /* a própria doc da Cielo manda consultar a transação para saber o status */
    expect($eventos[0]->type)->toBe(EventType::UPDATED)
        ->and($eventos[0]->resourceId)->toBe('guid-da-venda')
        ->and($eventos[0]->status)->toBeNull()
        ->and($eventos[0]->requiresLookup())->toBeTrue();
})->group('cielo');

it('na cielo, só os ChangeType inequívocos ganham tipo próprio', function () {
    $gateway = new CieloGateway('merchant-id', 'merchant-key', true, cieloClient([]));

    $tipo = fn (int $changeType): EventType => $gateway
        ->webhookEvents(['PaymentId' => 'guid', 'ChangeType' => $changeType])[0]->type;

    expect($tipo(2))->toBe(EventType::CREATED)
        ->and($tipo(7))->toBe(EventType::CHARGEBACK)
        ->and($tipo(8))->toBe(EventType::DISPUTE_OPENED);

    /* 25 é "cancelada OU estornada parcialmente": escolher erraria metade das vezes */
    expect($tipo(25))->toBe(EventType::UPDATED)
        ->and($tipo(6))->toBe(EventType::UPDATED)
        ->and($tipo(99))->toBe(EventType::UNKNOWN);
})->group('cielo');

it('lê evento que esta versão não conhece como desconhecido, sem levantar', function () {
    $asaas   = new AsaasGateway('token', true, mockClient([]));
    $pagarme = new PagarMeGateway('sk_test_abc', pagarmeClient([]));

    $doAsaas   = $asaas->webhookEvents(['event' => 'PAYMENT_COISA_NOVA', 'payment' => ['id' => 'p1']]);
    $doPagarMe = $pagarme->webhookEvents(['type' => 'charge.coisa_nova', 'data' => ['id' => 'ch_1']]);

    /* o nome cru fica, para quem precisar tratar o caso novo */
    expect($doAsaas[0]->type)->toBe(EventType::UNKNOWN)
        ->and($doAsaas[0]->rawType)->toBe('PAYMENT_COISA_NOVA')
        ->and($doAsaas[0]->resourceId)->toBe('p1')
        ->and($doPagarMe[0]->type)->toBe(EventType::UNKNOWN)
        ->and($doPagarMe[0]->rawType)->toBe('charge.coisa_nova');
})->group('phpay');

it('recusa a leitura onde o formato não foi confirmado', function () {
    $rede       = PHPay::gateway(redeGateway());
    $abacatepay = PHPay::gateway(new AbacatePayGateway('token', abacateClient([])));

    foreach ([$rede, $abacatepay] as $phpay) {
        expect($phpay->supports(Capability::WEBHOOK_EVENTS))->toBeFalse()
            ->and(fn () => $phpay->webhookEvents(['qualquer' => 'coisa']))
            ->toThrow(NotImplementedException::class);
    }
})->group('phpay');

it('estorno e leitura de webhook convivem na mesma notificação', function () {
    $gateway = new AsaasGateway('token', true, mockClient([]));

    $eventos = $gateway->webhookEvents([
        'event'   => 'PAYMENT_REFUNDED',
        'payment' => ['id' => 'pay_001', 'status' => 'REFUNDED'],
    ]);

    /* o #132 deu o refund(), o #133 o status, e aqui os três se encontram */
    expect($eventos[0]->type->isReversal())->toBeTrue()
        ->and($eventos[0]->status?->isRefunded())->toBeTrue()
        ->and($eventos[0]->status?->isFinal())->toBeTrue();
})->group('asaas');
