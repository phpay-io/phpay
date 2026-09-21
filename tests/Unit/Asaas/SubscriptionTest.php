<?php

use GuzzleHttp\Psr7\Response;
use PHPay\Asaas\Enums\SubscriptionCycleEnum;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\Money;

it('cria a assinatura com o cliente informado por id', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'sub_001'])], $history);

    (new Subscription('token', true, $client))
        ->setCustomerId('cus_abc')
        ->create([
            'billingType' => 'BOLETO',
            'value'       => 100,
            'nextDueDate' => '2026-01-10',
            'cycle'       => 'MONTHLY',
        ]);

    expect($history)->toHaveCount(1)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions')
        ->and(recordedBody($history)['customer'])->toBe('cus_abc')
        ->and(recordedBody($history)['cycle'])->toBe('MONTHLY');
})->group('asaas');

it('reaproveita o cliente quando o array traz um id', function () {
    $history = [];
    $client  = mockClient([jsonResponse(['id' => 'sub_001'])], $history);

    (new Subscription('token', true, $client))
        ->setCustomer(['id' => 'cus_abc'])
        ->create(['billingType' => 'PIX', 'value' => 10, 'nextDueDate' => '2026-01-10', 'cycle' => 'MONTHLY']);

    expect($history)->toHaveCount(1)
        ->and(recordedBody($history)['customer'])->toBe('cus_abc');
})->group('asaas');

it('exige um cliente antes de criar a assinatura', function () {
    $history = [];
    $client  = mockClient([jsonResponse([])], $history);

    expect(fn () => (new Subscription('token', true, $client))
        ->create(['billingType' => 'BOLETO', 'value' => 100, 'nextDueDate' => '2026-01-10', 'cycle' => 'MONTHLY']))
        ->toThrow(ValidationException::class, 'O campo customer é obrigatório');

    expect($history)->toBeEmpty();
})->group('asaas');

/**
 * Asaas subscription resource on a mocked client.
 *
 * @param array<int, mixed> $responses
 * @param array<int, mixed> $history
 * @return Subscription
 */
function asaasSubscription(array $responses, array &$history = []): Subscription
{
    return new Subscription('token', true, mockClient($responses, $history));
}

/**
 * invoice settings with every tax the Asaas API requires.
 *
 * @return array<mixed>
 */
function invoiceSettings(): array
{
    return [
        'municipalServiceName' => 'Desenvolvimento de software',
        'effectiveDatePeriod'  => 'ON_PAYMENT_CONFIRMATION',
        'taxes'                => [
            'retainIss' => false,
            'iss'       => 2,
            'pis'       => 0.65,
            'cofins'    => 3,
            'csll'      => 0,
            'inss'      => 0,
            'ir'        => 0,
        ],
    ];
}

it('monta a assinatura pelos setters, com o valor em Money', function () {
    $history = [];

    asaasSubscription([jsonResponse(['id' => 'sub_001'])], $history)
        ->setCustomerId('cus_abc')
        ->setAmount(Money::reais('49,90'))
        ->setCycle(SubscriptionCycleEnum::QUARTERLY)
        ->setSubscription(['billingType' => 'PIX', 'nextDueDate' => '2026-10-10'])
        ->create();

    expect(recordedBody($history))->toBe([
        'value'       => 49.9,
        'cycle'       => 'QUARTERLY',
        'billingType' => 'PIX',
        'nextDueDate' => '2026-10-10',
        'customer'    => 'cus_abc',
    ]);
})->group('asaas');

it('deixa o array do create() sobrescrever o que os setters montaram', function () {
    $history = [];

    asaasSubscription([jsonResponse(['id' => 'sub_001'])], $history)
        ->setCustomerId('cus_abc')
        ->setAmount(Money::reais(10))
        ->setCycle(SubscriptionCycleEnum::MONTHLY)
        ->create(['billingType' => 'BOLETO', 'nextDueDate' => '2026-10-10', 'value' => 20]);

    expect(recordedBody($history)['value'])->toBe(20);
})->group('asaas');

it('exige o ciclo, que o Asaas recusa ausente', function () {
    $history = [];

    expect(fn () => asaasSubscription([], $history)
        ->setCustomerId('cus_abc')
        ->create(['billingType' => 'PIX', 'value' => 10, 'nextDueDate' => '2026-10-10']))
        ->toThrow(ValidationException::class, 'setCycle()');

    expect($history)->toBeEmpty();
})->group('asaas');

it('lista com filtros e busca por id', function () {
    $history      = [];
    $subscription = asaasSubscription([jsonResponse(['data' => []]), jsonResponse(['id' => 'sub_001'])], $history);

    $subscription->setQueryParams(['customer' => 'cus_abc', 'status' => 'ACTIVE'])->getAll();
    $subscription->find('sub_001');

    expect((string) $history[0]['request']->getUri())->toEndWith('/subscriptions?customer=cus_abc&status=ACTIVE')
        ->and((string) $history[1]['request']->getUri())->toEndWith('/subscriptions/sub_001');
})->group('asaas');

it('atualiza com PUT, repassando updatePendingPayments', function () {
    $history = [];

    asaasSubscription([jsonResponse(['id' => 'sub_001'])], $history)
        ->update('sub_001', ['description' => 'Plano anual', 'updatePendingPayments' => true]);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001')
        ->and(recordedBody($history))->toBe(['description' => 'Plano anual', 'updatePendingPayments' => true]);
})->group('asaas');

it('pausa sem remover cobranças e reativa com novo vencimento', function () {
    $history      = [];
    $subscription = asaasSubscription([jsonResponse([]), jsonResponse([])], $history);

    $subscription->deactivate('sub_001');
    $subscription->reactivate('sub_001', '2026-11-10');

    expect(recordedBody($history, 0))->toBe(['status' => 'INACTIVE'])
        ->and(recordedBody($history, 1))->toBe(['status' => 'ACTIVE', 'nextDueDate' => '2026-11-10']);
})->group('asaas');

it('remove a assinatura', function () {
    $history = [];

    expect(asaasSubscription([jsonResponse(['deleted' => true, 'id' => 'sub_001'])], $history)->destroy('sub_001'))
        ->toBeTrue()
        ->and($history[0]['request']->getMethod())->toBe('DELETE')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001');
})->group('asaas');

it('troca o cartão sem cobrar, por token ou pelos dados do cartão', function () {
    $history      = [];
    $subscription = asaasSubscription([jsonResponse([]), jsonResponse([])], $history);

    $subscription->updateCreditCard('sub_001', ['creditCardToken' => 'tok_card', 'remoteIp' => '200.100.50.25']);
    $subscription->updateCreditCard('sub_001', [
        'creditCard' => [
            'holderName'  => 'MARIO LUCAS',
            'number'      => '5162306219378829',
            'expiryMonth' => '05',
            'expiryYear'  => '2030',
            'ccv'         => '318',
        ],
        'creditCardHolderInfo' => [
            'name'          => 'Mário Lucas',
            'email'         => 'fale@phpay.io',
            'cpfCnpj'       => '12345678909',
            'postalCode'    => '01310100',
            'addressNumber' => '100',
            'phone'         => '11940028922',
        ],
        'remoteIp' => '200.100.50.25',
    ]);

    expect($history[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001/creditCard')
        ->and(recordedBody($history, 1)['creditCard']['holderName'])->toBe('MARIO LUCAS');
})->group('asaas');

it('recusa troca de cartão incompleta sem chamar a API', function (array $card, string $message) {
    $history = [];

    expect(fn () => asaasSubscription([], $history)->updateCreditCard('sub_001', $card))
        ->toThrow(ValidationException::class, $message);

    expect($history)->toBeEmpty();
})->with([
    'sem ip'      => [['creditCardToken' => 'tok_card'], 'remoteIp'],
    'ip inválido' => [['creditCardToken' => 'tok_card', 'remoteIp' => 'localhost'], 'remoteIp'],
    'sem cartão'  => [['remoteIp' => '200.100.50.25'], 'creditCardToken'],
    'sem titular' => [[
        'creditCard' => ['holderName' => 'X', 'number' => '1', 'expiryMonth' => '1', 'expiryYear' => '2030', 'ccv' => '1'],
        'remoteIp'   => '200.100.50.25',
    ], 'creditCardHolderInfo'],
])->group('asaas');

it('lista as cobranças geradas pela assinatura', function () {
    $history = [];

    asaasSubscription([jsonResponse(['data' => [['id' => 'pay_001']]])], $history)
        ->getPayments('sub_001', ['status' => 'PENDING']);

    expect((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001/payments?status=PENDING');
})->group('asaas');

it('devolve o carnê como os bytes do PDF', function () {
    $history = [];
    $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";

    $book = asaasSubscription([new Response(200, ['content-type' => 'application/pdf'], $pdf)], $history)
        ->paymentBook('sub_001', 12, 2026);

    expect($book)->toBe($pdf)
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001/paymentBook?month=12&year=2026');
})->group('asaas');

it('pede o carnê sem mês e ano quando não informados', function () {
    $history = [];

    asaasSubscription([new Response(200, ['content-type' => 'application/pdf'], '%PDF')], $history)
        ->paymentBook('sub_001');

    expect($history[0]['request']->getUri()->getQuery())->toBe('');
})->group('asaas');

it('falha com ApiException quando o carnê não existe', function () {
    expect(fn () => asaasSubscription([jsonResponse(['errors' => [['description' => 'Assinatura não encontrada.']]], 404)])
        ->paymentBook('sub_404'))
        ->toThrow(ApiException::class, 'Assinatura não encontrada.');
})->group('asaas');

it('configura, consulta, atualiza e remove a emissão de nota fiscal', function () {
    $history      = [];
    $subscription = asaasSubscription([
        jsonResponse(['municipalServiceName' => 'Desenvolvimento de software']),
        jsonResponse(['municipalServiceName' => 'Desenvolvimento de software']),
        jsonResponse([]),
        jsonResponse(['deleted' => true]),
    ], $history);

    $subscription->createInvoiceSettings('sub_001', invoiceSettings());
    $subscription->getInvoiceSettings('sub_001');
    $subscription->updateInvoiceSettings('sub_001', ['observations' => 'Nota mensal'] + invoiceSettings());

    expect($subscription->destroyInvoiceSettings('sub_001'))->toBeTrue()
        ->and(array_map(fn (array $t) => $t['request']->getMethod(), $history))->toBe(['POST', 'GET', 'PUT', 'DELETE'])
        ->and((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001/invoiceSettings')
        ->and(recordedBody($history, 0)['taxes']['pis'])->toBe(0.65);
})->group('asaas');

it('recusa configuração de nota fiscal sem os impostos que o Asaas exige', function (array $settings, string $message) {
    $history = [];

    expect(fn () => asaasSubscription([], $history)->createInvoiceSettings('sub_001', $settings))
        ->toThrow(ValidationException::class, $message);

    expect($history)->toBeEmpty();
})->with([
    'sem taxes'       => [['observations' => 'x'], 'O campo taxes é obrigatório'],
    'sem ir'          => [['taxes' => ['retainIss' => false, 'iss' => 2, 'pis' => 0, 'cofins' => 0, 'csll' => 0, 'inss' => 0]], 'Falta o imposto ir'],
    'retainIss texto' => [['taxes' => ['retainIss' => 'não', 'iss' => 2, 'pis' => 0, 'cofins' => 0, 'csll' => 0, 'inss' => 0, 'ir' => 0]], 'booleano'],
    'alíquota texto'  => [['taxes' => ['retainIss' => false, 'iss' => '2%', 'pis' => 0, 'cofins' => 0, 'csll' => 0, 'inss' => 0, 'ir' => 0]], 'O imposto iss'],
    'período errado'  => [['effectiveDatePeriod' => 'TODO_DIA'] + invoiceSettings(), 'effectiveDatePeriod'],
])->group('asaas');

it('lista as notas fiscais das cobranças da assinatura', function () {
    $history = [];

    asaasSubscription([jsonResponse(['data' => []])], $history)
        ->getInvoices('sub_001', ['status' => 'AUTHORIZED']);

    expect((string) $history[0]['request']->getUri())->toEndWith('/subscriptions/sub_001/invoices?status=AUTHORIZED');
})->group('asaas');
