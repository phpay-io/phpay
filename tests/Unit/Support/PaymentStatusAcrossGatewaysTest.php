<?php

use PHPay\AbacatePay\Enums\BillingStatusEnum;
use PHPay\Asaas\Enums\InvoiceStatusEnum;
use PHPay\Cielo\Enums\SaleStatusEnum;
use PHPay\Efi\Enums\{ChargeStatusEnum as EfiChargeStatusEnum, PixChargeStatusEnum};
use PHPay\MercadoPago\Enums\PaymentStatusEnum;
use PHPay\PagarMe\Enums\ChargeStatusEnum as PagarMeChargeStatusEnum;
use PHPay\PagBank\Enums\ChargeStatusEnum as PagBankChargeStatusEnum;
use PHPay\Rede\Enums\TransactionStatusEnum;
use PHPay\Support\PaymentStatus;
use PHPay\Woovi\Enums\ChargeStatusEnum as WooviChargeStatusEnum;

/**
 * o motivo do value object, em dois testes: dez vocabulários dizendo a mesma
 * coisa de dez formas, lidos de uma só.
 */
it('lê "paga" igual nos dez vocabulários', function () {
    $pagas = [
        'Asaas'           => InvoiceStatusEnum::of('RECEIVED'),
        'Woovi'           => WooviChargeStatusEnum::of('COMPLETED'),
        'Cielo'           => SaleStatusEnum::of(2),
        'Rede'            => TransactionStatusEnum::of('00'),
        'AbacatePay'      => BillingStatusEnum::of('PAID'),
        'Mercado Pago'    => PaymentStatusEnum::of('approved'),
        'PagBank'         => PagBankChargeStatusEnum::of('PAID'),
        'Pagar.me'        => PagarMeChargeStatusEnum::of('paid'),
        'Efí (cobranças)' => EfiChargeStatusEnum::of('paid'),
        'Efí (pix)'       => PixChargeStatusEnum::of('CONCLUIDA'),
    ];

    foreach ($pagas as $gateway => $status) {
        expect($status)->toBe(PaymentStatus::PAID, "{$gateway} deveria ler como paga")
            ->and($status->isPaid())->toBeTrue();
    }
})->group('support');

it('lê "aguardando pagamento" igual nos dez vocabulários', function () {
    $pendentes = [
        'Asaas'           => InvoiceStatusEnum::of('PENDING'),
        'Woovi'           => WooviChargeStatusEnum::of('ACTIVE'),
        'Cielo'           => SaleStatusEnum::of(12),
        'Rede'            => TransactionStatusEnum::of('220'),
        'AbacatePay'      => BillingStatusEnum::of('PENDING'),
        'Mercado Pago'    => PaymentStatusEnum::of('pending'),
        'PagBank'         => PagBankChargeStatusEnum::of('WAITING'),
        'Pagar.me'        => PagarMeChargeStatusEnum::of('pending'),
        'Efí (cobranças)' => EfiChargeStatusEnum::of('waiting'),
        'Efí (pix)'       => PixChargeStatusEnum::of('ATIVA'),
    ];

    foreach ($pendentes as $gateway => $status) {
        expect($status)->toBe(PaymentStatus::PENDING, "{$gateway} deveria ler como pendente")
            ->and($status->isPaid())->toBeFalse()
            ->and($status->isFinal())->toBeFalse();
    }
})->group('support');

it('lê "estornada" nos gateways que reportam estorno como status', function () {
    $estornadas = [
        'Asaas'        => InvoiceStatusEnum::of('REFUNDED'),
        'Woovi'        => WooviChargeStatusEnum::of('REFUNDED'),
        'Cielo'        => SaleStatusEnum::of(11),
        'AbacatePay'   => BillingStatusEnum::of('REFUNDED'),
        'Mercado Pago' => PaymentStatusEnum::of('refunded'),
        'Efí'          => EfiChargeStatusEnum::of('refunded'),
    ];

    foreach ($estornadas as $gateway => $status) {
        expect($status)->toBe(PaymentStatus::REFUNDED, "{$gateway} deveria ler como estornada")
            ->and($status->isRefunded())->toBeTrue()
            ->and($status->isFinal())->toBeTrue();
    }

    /*
     * o Pagar.me é a exceção, e é limite da API dele: cancelar e estornar
     * passam pelo mesmo DELETE e voltam como `canceled`, então um estorno lá
     * não se distingue de um cancelamento pelo status.
     */
    expect(PagarMeChargeStatusEnum::of('canceled'))->toBe(PaymentStatus::CANCELED);
})->group('support');

it('a troca de gateway deixa de reescrever o if', function () {
    /*
     * o antes: cada gateway exigia o seu literal. O depois: uma comparação.
     * É o mesmo ganho que o Money deu na entrada, do outro lado da chamada.
     */
    $respostas = [
        ['gateway' => 'asaas', 'status' => 'CONFIRMED'],
        ['gateway' => 'woovi', 'status' => 'COMPLETED'],
        ['gateway' => 'pagbank', 'status' => 'PAID'],
    ];

    $lidas = array_map(
        fn (array $resposta): PaymentStatus => match ($resposta['gateway']) {
            'asaas'   => InvoiceStatusEnum::of($resposta['status']),
            'woovi'   => WooviChargeStatusEnum::of($resposta['status']),
            'pagbank' => PagBankChargeStatusEnum::of($resposta['status']),
            default   => PaymentStatus::UNKNOWN,
        },
        $respostas
    );

    expect($lidas)->each->toBe(PaymentStatus::PAID);
})->group('support');
