<?php

use PHPay\Cielo\Enums\SaleStatusEnum;
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Exceptions\ValidationException;

/**
 * @param array<int, mixed> $escritas
 * @param array<int, mixed> $consultas
 * @param array<int, mixed> $historicoEscrita
 * @param array<int, mixed> $historicoConsulta
 * @return Charge
 */
function cieloCharge(
    array $escritas = [],
    array $consultas = [],
    array &$historicoEscrita = [],
    array &$historicoConsulta = []
): Charge {
    return new Charge(
        'merchant-id',
        'merchant-key',
        true,
        cieloClient($escritas, $historicoEscrita),
        cieloQueryClient($consultas, $historicoConsulta)
    );
}

it('cria a venda no host de escrita, com RequestId', function () {
    $escrita = [];
    $charge  = cieloCharge([jsonResponse(['Payment' => ['PaymentId' => 'pay_1']])], [], $escrita);

    $charge
        ->setOrderId('pedido-1')
        ->setCustomer(['Name' => 'Mário Lucas'])
        ->setPix(15700)
        ->create();

    $request = $escrita[0]['request'];

    expect((string) $request->getUri())->toBe('https://apisandbox.cieloecommerce.cielo.com.br/1/sales')
        ->and($request->hasHeader('RequestId'))->toBeTrue()
        ->and(recordedBody($escrita)['Payment'])->toBe(['Type' => 'Pix', 'Amount' => 15700]);
})->group('cielo');

it('consulta no host de query, não no de escrita', function () {
    $escrita  = [];
    $consulta = [];
    $charge   = cieloCharge([], [jsonResponse(['Payment' => ['Status' => 2]])], $escrita, $consulta);

    $charge->find('pay_1');

    expect($escrita)->toBeEmpty()
        ->and((string) $consulta[0]['request']->getUri())
        ->toBe('https://apiquerysandbox.cieloecommerce.cielo.com.br/1/sales/pay_1');
})->group('cielo');

it('busca pelas vendas de um pedido do seu sistema', function () {
    $escrita  = [];
    $consulta = [];
    $charge   = cieloCharge([], [jsonResponse([])], $escrita, $consulta);

    $charge->findByOrderId('pedido-1');

    expect((string) $consulta[0]['request']->getUri())->toContain('merchantOrderId=pedido-1');
})->group('cielo');

it('lê o status da venda', function () {
    $charge = cieloCharge([], [jsonResponse(['Payment' => ['Status' => SaleStatusEnum::PAYMENT_CONFIRMED->value]])]);

    expect($charge->getStatus('pay_1'))->toBe(2)
        ->and(SaleStatusEnum::from(2))->toBe(SaleStatusEnum::PAYMENT_CONFIRMED);
})->group('cielo');

it('extrai o copia-e-cola do Pix', function () {
    $charge = cieloCharge([], [jsonResponse(['Payment' => ['QrCodeString' => '00020126...']])]);

    expect($charge->getPixCode('pay_1'))->toBe('00020126...');
})->group('cielo');

it('devolve null quando a venda não tem código pix', function () {
    $charge = cieloCharge([], [jsonResponse(['Payment' => ['Status' => 1]])]);

    expect($charge->getPixCode('pay_1'))->toBeNull();
})->group('cielo');

it('captura e cancela com valor opcional na query string', function () {
    $escrita = [];
    $charge  = cieloCharge([jsonResponse([]), jsonResponse([]), jsonResponse([])], [], $escrita);

    $charge->capture('pay_1');
    $charge->capture('pay_1', 5000);
    $charge->cancel('pay_1', 2500);

    expect($escrita[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $escrita[0]['request']->getUri())->toEndWith('/1/sales/pay_1/capture')
        ->and((string) $escrita[1]['request']->getUri())->toEndWith('/1/sales/pay_1/capture?amount=5000')
        ->and((string) $escrita[2]['request']->getUri())->toEndWith('/1/sales/pay_1/void?amount=2500');
})->group('cielo');

it('monta venda com cartão de crédito sem capturar', function () {
    $escrita = [];
    $charge  = cieloCharge([jsonResponse([])], [], $escrita);

    $charge
        ->setCustomer(['Name' => 'Mário Lucas'])
        ->setCreditCard(15700, ['CardNumber' => '0000000000000001', 'Brand' => 'Visa'], 3)
        ->create();

    $payment = recordedBody($escrita)['Payment'];

    expect($payment['Type'])->toBe('CreditCard')
        ->and($payment['Installments'])->toBe(3)
        ->and($payment['Capture'])->toBeFalse();
})->group('cielo');

it('respeita o RequestId informado', function () {
    $escrita = [];
    $charge  = cieloCharge([jsonResponse([])], [], $escrita);

    $charge
        ->setCustomer(['Name' => 'Mário'])
        ->setPix(100)
        ->setRequestId('pedido-42')
        ->create();

    expect($escrita[0]['request']->getHeaderLine('RequestId'))->toBe('pedido-42');
})->group('cielo');

it('valida a venda antes de chamar a API', function (callable $montar, string $esperado) {
    $escrita = [];
    $charge  = cieloCharge([jsonResponse([])], [], $escrita);

    expect(fn () => $montar($charge)->create())
        ->toThrow(ValidationException::class, $esperado);

    expect($escrita)->toBeEmpty();
})->with([
    'sem cliente' => [
        fn (Charge $c) => $c->setPix(100),
        'O campo Customer é obrigatório',
    ],
    'sem forma de pagamento' => [
        fn (Charge $c) => $c->setCustomer(['Name' => 'Mário']),
        'O campo Payment é obrigatório',
    ],
    'valor decimal' => [
        fn (Charge $c) => $c->setCustomer(['Name' => 'Mário'])
            ->setSale(['Customer' => ['Name' => 'Mário'], 'Payment' => ['Type' => 'Pix', 'Amount' => 157.00]]),
        'CENTAVOS',
    ],
    'tipo de pagamento fora do enum' => [
        fn (Charge $c) => $c->setSale([
            'Customer' => ['Name' => 'Mário'],
            'Payment'  => ['Type' => 'Cheque', 'Amount' => 100],
        ]),
        'CreditCard, DebitCard, Pix, Boleto',
    ],
])->group('cielo');
