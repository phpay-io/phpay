<?php

use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\{Customer as CustomerData, Money};

it('reaproveita o cliente quando o array traz um id, sem criar outro', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001'])], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'BOLETO', 'value' => 100, 'dueDate' => '2026-01-10'])
        ->setCustomer(['id' => 'cus_existente', 'name' => 'Mário'])
        ->create();

    /* uma única requisição: a cobrança. nenhum cliente duplicado foi criado */
    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments')
        ->and(recordedBody($history)['customer'])->toBe('cus_existente');
})->group('asaas');

it('aceita um customerId já existente', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001'])], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'PIX', 'value' => 50, 'dueDate' => '2026-01-10'])
        ->setCustomerId('cus_abc')
        ->create();

    expect($history)->toHaveCount(1)
        ->and(recordedBody($history)['customer'])->toBe('cus_abc');
})->group('asaas');

it('cria o cliente antes da cobrança quando não há id', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['id' => 'cus_novo']),
        jsonResponse(['id' => 'pay_001']),
    ], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'BOLETO', 'value' => 100, 'dueDate' => '2026-01-10'])
        ->setCustomer(['name' => 'Mário', 'cpfCnpj' => '12345678901'])
        ->create();

    expect($history)->toHaveCount(2)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/customers')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments')
        ->and(recordedBody($history, 1)['customer'])->toBe('cus_novo');
})->group('asaas');

it('devolve null quando a cobrança não tem linha digitável', function () {
    $client = mockClient([jsonResponse([])]);

    expect((new Charge('token', true, $client))->getDigitableLine('pay_001'))->toBeNull();
})->group('asaas');

it('devolve a linha digitável quando presente', function () {
    $client = mockClient([jsonResponse(['identificationField' => '00190000090'])]);

    expect((new Charge('token', true, $client))->getDigitableLine('pay_001'))->toBe('00190000090');
})->group('asaas');

it('rejeita billingType inválido antes de chamar a API', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Charge('token', true, $client))
        ->setCharge(['customer' => 'cus_1', 'billingType' => 'CHEQUE', 'value' => 10, 'dueDate' => '2026-01-10'])
        ->create())
        ->toThrow(ValidationException::class);

    expect($history)->toBeEmpty();
})->group('asaas');

it('estorna por rota própria, que não é a da exclusão', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['status' => 'REFUNDED']), jsonResponse(['status' => 'REFUNDED'])], $history);

    $charge = new Charge('token', true, $client);
    $charge->refund('pay_001');
    $charge->refund('pay_001', 25.50);

    expect($history[0]['request']->getMethod())->toBe('POST')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001/refund')
        ->and(recordedBody($history, 0))->toBe([])
        ->and(recordedBody($history, 1))->toBe(['value' => 25.5]);
})->group('asaas');

it('estorna em reais, mesmo recebendo Money em centavos', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    (new Charge('token', true, $client))->refund('pay_001', Money::centavos(2550));

    /* 2550 centavos viram 25.50, não 2550: o Asaas cobra e devolve em reais */
    expect(recordedBody($history, 0))->toBe(['value' => 25.5]);
})->group('asaas');

it('consulta, lista e filtra pelo endpoint de payments', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['id' => 'pay_001']),
        jsonResponse(['data' => []]),
        jsonResponse(['data' => []]),
    ], $history);

    $charge = new Charge('token', true, $client);

    $charge->find('pay_001');
    $charge->getAll();
    $charge->setQueryParams(['limit' => 2, 'status' => 'RECEIVED'])->getAll();

    expect((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments')
        ->and((string) $history[2]['request']->getUri())->toContain('limit=2')
        ->and((string) $history[2]['request']->getUri())->toContain('status=RECEIVED');
})->group('asaas');

it('atualiza por PUT, como o resto do asaas', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001', 'value' => 150.5])], $history);

    (new Charge('token', true, $client))->update('pay_001', ['value' => 150.5]);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001')
        ->and(recordedBody($history, 0))->toBe(['value' => 150.5]);
})->group('asaas');

it('exclui devolvendo bool, e restaura por rota própria', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['deleted' => true]), jsonResponse(['id' => 'pay_001'])], $history);

    $charge = new Charge('token', true, $client);

    expect($charge->destroy('pay_001'))->toBeTrue();
    $charge->restore('pay_001');

    expect($history[0]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001')
        ->and($history[1]['request']->getMethod())->toBe('POST')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments/pay_001/restore');
})->group('asaas');

it('consulta status e QR Code pix em endpoints separados', function () {
    $history = [];
    $client  = mockClient([
        jsonResponse(['status' => 'RECEIVED']),
        jsonResponse(['payload' => '00020126...', 'encodedImage' => 'base64']),
    ], $history);

    $charge = new Charge('token', true, $client);

    expect($charge->getStatus('pay_001')['status'])->toBe('RECEIVED')
        ->and($charge->getQrCodePix('pay_001')['payload'])->toBe('00020126...');

    expect((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001/status')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments/pay_001/pixQrCode');
})->group('asaas');

it('confirma e desfaz o recebimento em dinheiro', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['status' => 'RECEIVED_IN_CASH']), jsonResponse(['status' => 'PENDING'])], $history);

    $charge = new Charge('token', true, $client);

    $charge->confirmReceipt('pay_001', ['paymentDate' => '2026-01-10', 'value' => 100.5, 'notifyCustomer' => false]);
    $charge->undoConfirmReceipt('pay_001');

    expect((string) $history[0]['request']->getUri())->toEndWith('/payments/pay_001/receiveInCash')
        ->and(recordedBody($history, 0))->toBe([
            'paymentDate'    => '2026-01-10',
            'value'          => 100.5,
            'notifyCustomer' => false,
        ])
        ->and((string) $history[1]['request']->getUri())->toEndWith('/payments/pay_001/undoReceivedInCash')
        ->and(recordedBody($history, 1))->toBe([]);
})->group('asaas');

it('reaproveita o cliente quando o VO já carrega id, sem criar outro', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'pay_001'])], $history);

    (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'PIX', 'value' => 50, 'dueDate' => '2026-01-10'])
        ->setCustomer(CustomerData::make('Mário Lucas', '12345678901')->withId('cus_existente'))
        ->create();

    /* uma requisição só: a criação do cliente não aconteceu */
    expect($history)->toHaveCount(1)
        ->and(recordedBody($history, 0)['customer'])->toBe('cus_existente');
})->group('asaas');

it('falha quando a criação do cliente não devolve id', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['errors' => [['description' => 'documento inválido']]])], $history);

    expect(fn () => (new Charge('token', true, $client))
        ->setCharge(['billingType' => 'PIX', 'value' => 50, 'dueDate' => '2026-01-10'])
        ->setCustomer(['name' => 'Mário', 'cpfCnpj' => '12345678901'])
        ->create())
        ->toThrow(ApiException::class, 'a criação do cliente não retornou um id');
})->group('asaas');
