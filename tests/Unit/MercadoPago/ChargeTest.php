<?php

use PHPay\Exceptions\ValidationException;
use PHPay\MercadoPago\Enums\PaymentMethodEnum;
use PHPay\MercadoPago\Resources\Charge\Charge;

it('cria uma cobrança pix com o payload e o header de idempotência', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 123, 'status' => 'pending'])], $history);

    (new Charge('TEST-token', $client))
        ->setCharge([
            'transaction_amount' => 100.50,
            'payment_method_id'  => PaymentMethodEnum::PIX->value,
            'description'        => 'Cobrança de teste',
        ])
        ->setPayer(['email' => 'fale@phpay.io'])
        ->create();

    $request = $history[0]['request'];

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toEndWith('/v1/payments')
        ->and($request->hasHeader('X-Idempotency-Key'))->toBeTrue()
        ->and($request->getHeaderLine('X-Idempotency-Key'))->not->toBeEmpty()
        ->and(recordedBody($history))->toBe([
            'transaction_amount' => 100.50,
            'payment_method_id'  => 'pix',
            'description'        => 'Cobrança de teste',
            'payer'              => ['email' => 'fale@phpay.io'],
        ]);
})->group('mercadopago');

it('respeita a chave de idempotência informada', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 123])], $history);

    (new Charge('TEST-token', $client))
        ->setCharge(['transaction_amount' => 10, 'payment_method_id' => 'pix'])
        ->setPayer(['email' => 'fale@phpay.io'])
        ->setIdempotencyKey('pedido-42')
        ->create();

    expect($history[0]['request']->getHeaderLine('X-Idempotency-Key'))->toBe('pedido-42');
})->group('mercadopago');

it('gera chaves de idempotência distintas por cobrança', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 2])], $history);

    $charge = (new Charge('TEST-token', $client))
        ->setCharge(['transaction_amount' => 10, 'payment_method_id' => 'pix'])
        ->setPayer(['email' => 'fale@phpay.io']);

    $charge->create();
    $charge->create();

    expect($history[0]['request']->getHeaderLine('X-Idempotency-Key'))
        ->not->toBe($history[1]['request']->getHeaderLine('X-Idempotency-Key'));
})->group('mercadopago');

it('busca cobranças pelo endpoint de search', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['results' => []])], $history);

    (new Charge('TEST-token', $client))
        ->setQueryParams(['status' => 'approved', 'limit' => 5])
        ->getAll();

    expect((string) $history[0]['request']->getUri())
        ->toContain('/v1/payments/search')
        ->toContain('status=approved')
        ->toContain('limit=5');
})->group('mercadopago');

it('extrai o código pix copia-e-cola da cobrança', function () {
    $client = mpClient([jsonResponse([
        'id'                   => 123,
        'point_of_interaction' => [
            'transaction_data' => ['qr_code' => '00020126580014br.gov.bcb.pix'],
        ],
    ])]);

    expect((new Charge('TEST-token', $client))->getPixCode('123'))
        ->toBe('00020126580014br.gov.bcb.pix');
})->group('mercadopago');

it('devolve null quando a cobrança não tem código pix', function () {
    $client = mpClient([jsonResponse(['id' => 123, 'status' => 'approved'])]);

    expect((new Charge('TEST-token', $client))->getPixCode('123'))->toBeNull();
})->group('mercadopago');

it('cancela a cobrança mudando o status', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 123, 'status' => 'cancelled'])], $history);

    (new Charge('TEST-token', $client))->cancel('123');

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and(recordedBody($history))->toBe(['status' => 'cancelled']);
})->group('mercadopago');

it('estorna total ou parcialmente', function () {
    $history = [];
    $client  = mpClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 2])], $history);

    $charge = new Charge('TEST-token', $client);
    $charge->refund('123');
    $charge->refund('123', 25.50);

    expect((string) $history[0]['request']->getUri())->toEndWith('/v1/payments/123/refunds')
        ->and(recordedBody($history, 0))->toBe([])
        ->and(recordedBody($history, 1))->toBe(['amount' => 25.50]);
})->group('mercadopago');

it('valida a cobrança antes de chamar a API', function (array $charge, string $esperado) {
    $history = [];
    $client  = mpClient([jsonResponse([])], $history);

    expect(fn () => (new Charge('TEST-token', $client))->setCharge($charge)->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'sem valor' => [
        ['payment_method_id' => 'pix', 'payer' => ['email' => 'a@b.com']],
        'transaction_amount',
    ],
    'valor zerado' => [
        ['transaction_amount' => 0, 'payment_method_id' => 'pix', 'payer' => ['email' => 'a@b.com']],
        'transaction_amount',
    ],
    'sem forma de pagamento' => [
        ['transaction_amount' => 10, 'payer' => ['email' => 'a@b.com']],
        'payment_method_id',
    ],
    'sem payer' => [
        ['transaction_amount' => 10, 'payment_method_id' => 'pix'],
        'O campo payer é obrigatório',
    ],
    'e-mail inválido' => [
        ['transaction_amount' => 10, 'payment_method_id' => 'pix', 'payer' => ['email' => 'nao-e-email']],
        'payer.email',
    ],
])->group('mercadopago');
