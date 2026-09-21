<?php

use PHPay\Exceptions\ValidationException;
use PHPay\PagBank\Enums\PaymentMethodEnum;
use PHPay\PagBank\Resources\Charge\Charge;

/**
 * @return array<mixed>
 */
function pagbankCustomer(): array
{
    return [
        'name'   => 'Mário Lucas',
        'email'  => 'fale@phpay.io',
        'tax_id' => '12345678901',
    ];
}

it('pede o pix como qr_code do pedido, não como charge', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse(['id' => 'ORDE_1'])], $history);

    (new Charge('token', true, $client))
        ->setCustomer(pagbankCustomer())
        ->addItem('Assinatura PHPay', 10050)
        ->setQrCode(10050)
        ->create();

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/orders')
        ->and($body)->toHaveKey('qr_codes')
        ->and($body)->not->toHaveKey('charges')
        ->and($body['qr_codes'])->toHaveCount(1)
        ->and($body['qr_codes'][0]['amount']['value'])->toBe(10050);
})->group('pagbank');

it('aceita data de expiração no qr code', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse(['id' => 'ORDE_1'])], $history);

    (new Charge('token', true, $client))
        ->setCustomer(pagbankCustomer())
        ->addItem('Item', 100)
        ->setQrCode(100, '2026-12-31T23:59:59-03:00')
        ->create();

    expect(recordedBody($history)['qr_codes'][0]['expiration_date'])->toBe('2026-12-31T23:59:59-03:00');
})->group('pagbank');

it('extrai o copia-e-cola de qr_codes[0].text', function () {
    $client = pagbankClient([jsonResponse([
        'id'       => 'ORDE_1',
        'qr_codes' => [['id' => 'QRCO_1', 'text' => '00020101021226...']],
    ])]);

    expect((new Charge('token', true, $client))->getPixCode('ORDE_1'))->toBe('00020101021226...');
})->group('pagbank');

it('devolve null quando o pedido não tem qr code', function () {
    $client = pagbankClient([jsonResponse(['id' => 'ORDE_1', 'charges' => []])]);

    expect((new Charge('token', true, $client))->getPixCode('ORDE_1'))->toBeNull();
})->group('pagbank');

it('monta um pedido com cobrança de cartão', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse(['id' => 'ORDE_1'])], $history);

    (new Charge('token', true, $client))
        ->setCustomer(pagbankCustomer())
        ->addItem('Camiseta', 5990, 2)
        ->setCharges([[
            'reference_id'   => 'cobranca-1',
            'description'    => 'Camiseta',
            'amount'         => ['value' => 11980, 'currency' => 'BRL'],
            'payment_method' => [
                'type'         => PaymentMethodEnum::CREDIT_CARD->value,
                'installments' => 1,
                'capture'      => true,
            ],
        ]])
        ->setNotificationUrls(['https://exemplo.test/webhook/pagbank'])
        ->create();

    $body = recordedBody($history);

    expect($body['items'][0]['unit_amount'])->toBe(5990)
        ->and($body['items'][0]['quantity'])->toBe(2)
        ->and($body['charges'][0]['payment_method']['type'])->toBe('CREDIT_CARD')
        ->and($body['notification_urls'])->toBe(['https://exemplo.test/webhook/pagbank'])
        ->and($body)->toHaveKey('reference_id');
})->group('pagbank');

it('estorna total ou parcialmente em centavos', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 2])], $history);

    $charge = new Charge('token', true, $client);
    $charge->refund('CHAR_1');
    $charge->refund('CHAR_1', 2500);

    expect((string) $history[0]['request']->getUri())->toEndWith('/charges/CHAR_1/cancel')
        ->and(recordedBody($history, 0))->toBe([])
        ->and(recordedBody($history, 1))->toBe(['amount' => ['value' => 2500]]);
})->group('pagbank');

it('consulta a cobrança e o status no endpoint de charges', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse(['id' => 'CHAR_1', 'status' => 'PAID'])], $history);

    expect((new Charge('token', true, $client))->getStatus('CHAR_1'))->toBe('PAID')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/charges/CHAR_1');
})->group('pagbank');

it('valida o pedido antes de chamar a API', function (callable $montar, string $esperado) {
    $history = [];
    $client  = pagbankClient([jsonResponse([])], $history);

    expect(fn () => $montar(new Charge('token', true, $client))->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'sem cliente' => [
        fn (Charge $c) => $c->addItem('Item', 100)->setQrCode(100),
        'O campo customer é obrigatório',
    ],
    'cpf inválido' => [
        fn (Charge $c) => $c->setCustomer(['name' => 'X', 'email' => 'a@b.com', 'tax_id' => '123'])
            ->addItem('Item', 100)->setQrCode(100),
        'tax_id',
    ],
    'sem itens' => [
        fn (Charge $c) => $c->setCustomer(pagbankCustomer())->setQrCode(100),
        'ao menos um item',
    ],
    'sem forma de pagamento' => [
        fn (Charge $c) => $c->setCustomer(pagbankCustomer())->addItem('Item', 100),
        'charges (cartão ou boleto) ou de um qr_code',
    ],
])->group('pagbank');

it('recusa valor decimal, que o pagbank cobraria errado', function () {
    $history = [];
    $client  = pagbankClient([jsonResponse([])], $history);

    /* R$ 10,50 tem que ser 1050 — mandar 10.50 cobraria onze centavos */
    expect(fn () => (new Charge('token', true, $client))
        ->setCustomer(pagbankCustomer())
        ->setItems([['name' => 'Item', 'quantity' => 1, 'unit_amount' => 10.50]])
        ->setQrCode(1050)
        ->create())
        ->toThrow(ValidationException::class, 'CENTAVOS');

    expect($history)->toBeEmpty();
})->group('pagbank');

it('recusa mais de um qr code por pedido', function () {
    $client = pagbankClient([jsonResponse([])]);

    expect(fn () => (new Charge('token', true, $client))
        ->setOrder([
            'customer' => pagbankCustomer(),
            'items'    => [['name' => 'Item', 'quantity' => 1, 'unit_amount' => 100]],
            'qr_codes' => [['amount' => ['value' => 100]], ['amount' => ['value' => 200]]],
        ])
        ->create())
        ->toThrow(ValidationException::class, 'apenas um QR Code');
})->group('pagbank');
