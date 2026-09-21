<?php

use PHPay\Exceptions\ValidationException;
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PagarMe\Resources\WebhookDelivery\WebhookDelivery;

/**
 * @return array<mixed>
 */
function pagarmeCustomer(): array
{
    return [
        'name'     => 'Mário Lucas',
        'email'    => 'fale@phpay.io',
        'document' => '12345678901',
        'type'     => 'individual',
    ];
}

it('manda o pix como forma de pagamento do pedido', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'or_1'])], $history);

    (new Charge('sk_test_abc', $client))
        ->setCustomer(pagarmeCustomer())
        ->addItem('Assinatura PHPay', 10050)
        ->setPix(1800)
        ->create();

    $body = recordedBody($history);

    expect((string) $history[0]['request']->getUri())->toEndWith('/orders')
        ->and($body['payments'][0]['payment_method'])->toBe('pix')
        ->and($body['payments'][0]['pix']['expires_in'])->toBe(1800)
        ->and($body['items'][0]['amount'])->toBe(10050);
})->group('pagarme');

it('extrai o copia-e-cola de charges[0].last_transaction.qr_code', function () {
    $client = pagarmeClient([jsonResponse([
        'id'      => 'or_1',
        'charges' => [[
            'id'               => 'ch_1',
            'last_transaction' => ['qr_code' => '00020126580014br.gov.bcb.pix'],
        ]],
    ])]);

    expect((new Charge('sk_test_abc', $client))->getPixCode('or_1'))
        ->toBe('00020126580014br.gov.bcb.pix');
})->group('pagarme');

it('devolve null quando o pedido não tem qr code', function () {
    $client = pagarmeClient([jsonResponse(['id' => 'or_1', 'charges' => [['id' => 'ch_1']]])]);

    expect((new Charge('sk_test_abc', $client))->getPixCode('or_1'))->toBeNull();
})->group('pagarme');

it('reaproveita o cliente quando o array traz um id', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'or_1'])], $history);

    (new Charge('sk_test_abc', $client))
        ->setCustomer(['id' => 'cus_existente'])
        ->addItem('Item', 100)
        ->setPix()
        ->create();

    $body = recordedBody($history);

    /* uma só requisição, e o customer completo dá lugar ao id */
    expect($history)->toHaveCount(1)
        ->and($body['customer_id'])->toBe('cus_existente')
        ->and($body)->not->toHaveKey('customer');
})->group('pagarme');

it('monta boleto com vencimento', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'or_1'])], $history);

    (new Charge('sk_test_abc', $client))
        ->setCustomerId('cus_1')
        ->addItem('Item', 5000)
        ->setBoleto('2026-12-31', ['Não receber após o vencimento'])
        ->create();

    $payment = recordedBody($history)['payments'][0];

    expect($payment['payment_method'])->toBe('boleto')
        ->and($payment['boleto']['due_at'])->toBe('2026-12-31')
        ->and($payment['boleto']['instructions'])->toBe(['Não receber após o vencimento']);
})->group('pagarme');

it('cancela a cobrança por DELETE, com valor opcional', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 1]), jsonResponse(['id' => 2])], $history);

    $charge = new Charge('sk_test_abc', $client);
    $charge->cancel('ch_1');
    $charge->cancel('ch_1', 2500);

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/charges/ch_1')
        ->and(recordedBody($history, 0))->toBe([])
        ->and(recordedBody($history, 1))->toBe(['amount' => 2500]);
})->group('pagarme');

it('captura uma autorização', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse(['id' => 'ch_1'])], $history);

    (new Charge('sk_test_abc', $client))->capture('ch_1', 1000);

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/charges/ch_1/capture')
        ->and(recordedBody($history))->toBe(['amount' => 1000]);
})->group('pagarme');

it('valida o pedido antes de chamar a API', function (callable $montar, string $esperado) {
    $history = [];
    $client  = pagarmeClient([jsonResponse([])], $history);

    expect(fn () => $montar(new Charge('sk_test_abc', $client))->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($history)->toBeEmpty();
})->with([
    'sem itens' => [
        fn (Charge $c) => $c->setCustomerId('cus_1')->setPix(),
        'ao menos um item',
    ],
    'sem cliente' => [
        fn (Charge $c) => $c->addItem('Item', 100)->setPix(),
        'customer_id ou de um customer completo',
    ],
    'sem forma de pagamento' => [
        fn (Charge $c) => $c->setCustomerId('cus_1')->addItem('Item', 100),
        'ao menos uma forma de pagamento',
    ],
    'forma de pagamento fora do enum' => [
        fn (Charge $c) => $c->setCustomerId('cus_1')->addItem('Item', 100)
            ->setPayments([['payment_method' => 'cheque']]),
        'credit_card, debit_card, boleto, pix',
    ],
    'documento inválido' => [
        fn (Charge $c) => $c->setCustomer(['name' => 'X', 'email' => 'a@b.com', 'document' => '123'])
            ->addItem('Item', 100)->setPix(),
        'document',
    ],
])->group('pagarme');

it('recusa valor decimal no item', function () {
    $history = [];
    $client  = pagarmeClient([jsonResponse([])], $history);

    expect(fn () => (new Charge('sk_test_abc', $client))
        ->setCustomerId('cus_1')
        ->setItems([['description' => 'Item', 'quantity' => 1, 'amount' => 10.50]])
        ->setPix()
        ->create())
        ->toThrow(ValidationException::class, 'CENTAVOS');

    expect($history)->toBeEmpty();
})->group('pagarme');

it('lê e reenvia entregas de webhook', function () {
    $history = [];
    $client  = pagarmeClient([
        jsonResponse(['data' => []]), jsonResponse(['id' => 'hook_1']), jsonResponse(['id' => 'hook_1']),
    ], $history);

    $deliveries = new WebhookDelivery('sk_test_abc', $client);
    $deliveries->setFilter(['size' => 10])->getAll();
    $deliveries->find('hook_1');
    $deliveries->resend('hook_1');

    expect((string) $history[0]['request']->getUri())->toContain('/hooks')
        ->and((string) $history[0]['request']->getUri())->toContain('size=10')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/hooks/hook_1')
        ->and($history[2]['request']->getMethod())->toBe('POST')
        ->and((string) $history[2]['request']->getUri())->toEndWith('/hooks/hook_1/resend');
})->group('pagarme');
