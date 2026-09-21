<?php

use PHPay\Cielo\Enums\RecurrentIntervalEnum;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Exceptions\ValidationException;

/**
 * @param array<int, mixed> $escritas
 * @param array<int, mixed> $consultas
 * @param array<int, mixed> $historicoEscrita
 * @param array<int, mixed> $historicoConsulta
 * @return Subscription
 */
function cieloSubscription(
    array $escritas = [],
    array $consultas = [],
    array &$historicoEscrita = [],
    array &$historicoConsulta = []
): Subscription {
    return new Subscription(
        'merchant-id',
        'merchant-key',
        true,
        cieloClient($escritas, $historicoEscrita),
        cieloQueryClient($consultas, $historicoConsulta)
    );
}

/**
 * @return array<mixed>
 */
function cieloCard(): array
{
    return [
        'CardNumber'     => '0000000000000001',
        'Holder'         => 'Mario Lucas',
        'ExpirationDate' => '12/2030',
        'SecurityCode'   => '123',
        'Brand'          => 'Visa',
    ];
}

it('nasce de uma venda com bloco RecurrentPayment, não de um endpoint próprio', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse(['Payment' => ['RecurrentPayment' => ['RecurrentPaymentId' => 'rec_1']]])], [], $escrita);

    $sub
        ->setOrderId('assinatura-1')
        ->setCustomer(['Name' => 'Mário Lucas'])
        ->setCard(cieloCard())
        ->setInterval(RecurrentIntervalEnum::MONTHLY)
        ->setEndDate('2027-12-31')
        ->create(15700);

    $body = recordedBody($escrita);

    expect((string) $escrita[0]['request']->getUri())->toEndWith('/1/sales')
        ->and($body['Payment']['Type'])->toBe('CreditCard')
        ->and($body['Payment']['Amount'])->toBe(15700)
        ->and($body['Payment']['RecurrentPayment']['Interval'])->toBe('Monthly')
        ->and($body['Payment']['RecurrentPayment']['EndDate'])->toBe('2027-12-31')
        ->and($body['Payment']['RecurrentPayment']['AuthorizeNow'])->toBeTrue();
})->group('cielo');

it('usa recorrência mensal quando o intervalo não é informado', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse([])], [], $escrita);

    $sub->setCustomer(['Name' => 'Mário'])->setCard(cieloCard())->create(1000);

    expect(recordedBody($escrita)['Payment']['RecurrentPayment']['Interval'])->toBe('Monthly');
})->group('cielo');

it('consulta a recorrência no host de query', function () {
    $escrita  = [];
    $consulta = [];
    $sub      = cieloSubscription([], [jsonResponse(['RecurrentPaymentId' => 'rec_1'])], $escrita, $consulta);

    $sub->find('rec_1');

    expect($escrita)->toBeEmpty()
        ->and((string) $consulta[0]['request']->getUri())->toEndWith('/1/RecurrentPayment/rec_1');
})->group('cielo');

it('suspende e reativa pela rota de cada ação', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse([]), jsonResponse([])], [], $escrita);

    $sub->deactivate('rec_1');
    $sub->reactivate('rec_1');

    expect($escrita[0]['request']->getMethod())->toBe('PUT')
        ->and((string) $escrita[0]['request']->getUri())->toEndWith('/1/RecurrentPayment/rec_1/Deactivate')
        ->and((string) $escrita[1]['request']->getUri())->toEndWith('/1/RecurrentPayment/rec_1/Reactivate');
})->group('cielo');

it('manda o novo valor como json puro, não como objeto', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse([]), jsonResponse([]), jsonResponse([])], [], $escrita);

    $sub->updateAmount('rec_1', 19900);
    $sub->updateInterval('rec_1', RecurrentIntervalEnum::ANNUAL);
    $sub->updateEndDate('rec_1', '2028-01-31');

    expect((string) $escrita[0]['request']->getBody())->toBe('19900')
        ->and((string) $escrita[1]['request']->getBody())->toBe('"Annual"')
        ->and((string) $escrita[2]['request']->getBody())->toBe('"2028-01-31"')
        ->and((string) $escrita[0]['request']->getUri())->toEndWith('/1/RecurrentPayment/rec_1/Amount');
})->group('cielo');

it('exige cartão de crédito na recorrência', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse([])], [], $escrita);

    expect(fn () => $sub->setCustomer(['Name' => 'Mário'])->create(1000))
        ->toThrow(ValidationException::class, 'exige cartão de crédito');

    expect($escrita)->toBeEmpty();
})->group('cielo');

it('recusa valor decimal ao atualizar', function () {
    $escrita = [];
    $sub     = cieloSubscription([jsonResponse([])], [], $escrita);

    expect(fn () => $sub->updateAmount('rec_1', 0))
        ->toThrow(ValidationException::class, 'CENTAVOS');

    expect($escrita)->toBeEmpty();
})->group('cielo');
