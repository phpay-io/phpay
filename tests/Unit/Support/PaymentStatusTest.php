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

it('só considera paga a que recebeu o valor inteiro e ainda está com você', function () {
    expect(PaymentStatus::PAID->isPaid())->toBeTrue();

    /* nenhuma das outras é "paga": autorizada não moveu dinheiro, parcial não
       recebeu tudo, e estornada e chargeback devolveram */
    foreach (PaymentStatus::cases() as $status) {
        if ($status !== PaymentStatus::PAID) {
            expect($status->isPaid())->toBeFalse();
        }
    }
})->group('support');

it('considera devolução tanto o estorno total quanto o parcial', function () {
    expect(PaymentStatus::REFUNDED->isRefunded())->toBeTrue()
        ->and(PaymentStatus::PARTIALLY_REFUNDED->isRefunded())->toBeTrue()
        ->and(PaymentStatus::PAID->isRefunded())->toBeFalse()
        ->and(PaymentStatus::CHARGEBACK->isRefunded())->toBeFalse();
})->group('support');

it('não trata paga como estado final, porque estorno e chargeback ainda vêm depois', function () {
    expect(PaymentStatus::PAID->isFinal())->toBeFalse()
        ->and(PaymentStatus::UNKNOWN->isFinal())->toBeFalse()
        ->and(PaymentStatus::PENDING->isFinal())->toBeFalse()
        ->and(PaymentStatus::AUTHORIZED->isFinal())->toBeFalse();

    expect(PaymentStatus::REFUNDED->isFinal())->toBeTrue()
        ->and(PaymentStatus::CHARGEBACK->isFinal())->toBeTrue()
        ->and(PaymentStatus::CANCELED->isFinal())->toBeTrue()
        ->and(PaymentStatus::EXPIRED->isFinal())->toBeTrue()
        ->and(PaymentStatus::FAILED->isFinal())->toBeTrue();
})->group('support');

it('dá rótulo em português a todo estado', function () {
    foreach (PaymentStatus::cases() as $status) {
        expect($status->label())->not->toBeEmpty();
    }
})->group('support');

it('nunca devolve desconhecido para um case que o gateway declara', function (string $enum) {
    /*
     * UNKNOWN existe só para valor cru que a lib não conhece. Um case do enum
     * é, por definição, conhecido — se algum mapeasse para UNKNOWN, seria
     * buraco no match e não degradação.
     */
    foreach ($enum::cases() as $case) {
        expect($case->status())->not->toBe(PaymentStatus::UNKNOWN);
    }
})->with([
    InvoiceStatusEnum::class,
    WooviChargeStatusEnum::class,
    SaleStatusEnum::class,
    TransactionStatusEnum::class,
    BillingStatusEnum::class,
    PaymentStatusEnum::class,
    PagBankChargeStatusEnum::class,
    PagarMeChargeStatusEnum::class,
    EfiChargeStatusEnum::class,
    PixChargeStatusEnum::class,
])->group('support');

it('lê status que esta versão não conhece como desconhecido, sem levantar', function () {
    expect(InvoiceStatusEnum::of('STATUS_QUE_O_ASAAS_INVENTOU'))->toBe(PaymentStatus::UNKNOWN)
        ->and(WooviChargeStatusEnum::of('NOVO_STATUS'))->toBe(PaymentStatus::UNKNOWN)
        ->and(BillingStatusEnum::of('NOVO'))->toBe(PaymentStatus::UNKNOWN)
        ->and(PaymentStatusEnum::of('novo_status'))->toBe(PaymentStatus::UNKNOWN)
        ->and(PagBankChargeStatusEnum::of('NOVO'))->toBe(PaymentStatus::UNKNOWN)
        ->and(PagarMeChargeStatusEnum::of('novo'))->toBe(PaymentStatus::UNKNOWN)
        ->and(EfiChargeStatusEnum::of('novo'))->toBe(PaymentStatus::UNKNOWN)
        ->and(PixChargeStatusEnum::of('NOVA'))->toBe(PaymentStatus::UNKNOWN)
        ->and(SaleStatusEnum::of(999))->toBe(PaymentStatus::UNKNOWN);
})->group('support');

it('lê status ausente como desconhecido', function () {
    expect(InvoiceStatusEnum::of(null))->toBe(PaymentStatus::UNKNOWN)
        ->and(SaleStatusEnum::of(null))->toBe(PaymentStatus::UNKNOWN)
        ->and(TransactionStatusEnum::of(null))->toBe(PaymentStatus::UNKNOWN)
        ->and(TransactionStatusEnum::of(''))->toBe(PaymentStatus::UNKNOWN);
})->group('support');

it('na rede, código não listado é recusa e não desconhecido', function () {
    /*
     * a Rede publica dezenas de códigos de recusa, e o enum lista só os dois
     * que valem desvio. Responder UNKNOWN para os outros esconderia uma recusa
     * atrás de "não sei".
     */
    expect(TransactionStatusEnum::of('00'))->toBe(PaymentStatus::PAID)
        ->and(TransactionStatusEnum::of('220'))->toBe(PaymentStatus::PENDING)
        ->and(TransactionStatusEnum::of('51'))->toBe(PaymentStatus::FAILED)
        ->and(TransactionStatusEnum::of('78'))->toBe(PaymentStatus::FAILED);
})->group('support');

it('não confunde autorizada com paga, onde o gateway separa as duas', function () {
    /* dinheiro ainda não se moveu: entregar produto aqui é risco */
    expect(SaleStatusEnum::AUTHORIZED->status())->toBe(PaymentStatus::AUTHORIZED)
        ->and(PagBankChargeStatusEnum::AUTHORIZED->status())->toBe(PaymentStatus::AUTHORIZED)
        ->and(PaymentStatusEnum::AUTHORIZED->status())->toBe(PaymentStatus::AUTHORIZED);

    expect(PaymentStatus::AUTHORIZED->isPaid())->toBeFalse();
})->group('support');

it('não dá por devolvido o estorno que o asaas ainda não concluiu', function () {
    /*
     * o Asaas só considera a devolução feita quando ela chega a DONE. Ler
     * REFUND_IN_PROGRESS como estornada reportaria dinheiro de volta antes de
     * ter voltado.
     */
    expect(InvoiceStatusEnum::REFUND_IN_PROGRESS->status())->toBe(PaymentStatus::PAID)
        ->and(InvoiceStatusEnum::REFUNDED->status())->toBe(PaymentStatus::REFUNDED);
})->group('support');

it('não lê pagamento a menos como pagamento', function () {
    /* underpaid chegou dinheiro, mas não o que era devido */
    expect(PagarMeChargeStatusEnum::UNDERPAID->status())->toBe(PaymentStatus::PARTIALLY_PAID)
        ->and(PagarMeChargeStatusEnum::UNDERPAID->status()->isPaid())->toBeFalse()
        ->and(PagarMeChargeStatusEnum::OVERPAID->status())->toBe(PaymentStatus::PAID);
})->group('support');

it('separa disputa em andamento de chargeback perdido', function () {
    expect(InvoiceStatusEnum::CHARGEBACK_REQUESTED->status())->toBe(PaymentStatus::IN_DISPUTE)
        ->and(PaymentStatusEnum::IN_MEDIATION->status())->toBe(PaymentStatus::IN_DISPUTE)
        ->and(EfiChargeStatusEnum::CONTESTED->status())->toBe(PaymentStatus::IN_DISPUTE);

    expect(PaymentStatusEnum::CHARGED_BACK->status())->toBe(PaymentStatus::CHARGEBACK)
        ->and(PagarMeChargeStatusEnum::CHARGEDBACK->status())->toBe(PaymentStatus::CHARGEBACK);

    /* disputa aberta não é final; chargeback é */
    expect(PaymentStatus::IN_DISPUTE->isFinal())->toBeFalse()
        ->and(PaymentStatus::CHARGEBACK->isFinal())->toBeTrue();
})->group('support');

it('mantém as duas APIs da efí com vocabulários separados', function () {
    /*
     * mesmas credenciais, dois conjuntos de estados. Ler um com o enum do
     * outro devolve UNKNOWN em vez de um palpite.
     */
    expect(EfiChargeStatusEnum::of('CONCLUIDA'))->toBe(PaymentStatus::UNKNOWN)
        ->and(PixChargeStatusEnum::of('paid'))->toBe(PaymentStatus::UNKNOWN);

    expect(EfiChargeStatusEnum::of('paid'))->toBe(PaymentStatus::PAID)
        ->and(PixChargeStatusEnum::of('CONCLUIDA'))->toBe(PaymentStatus::PAID);
})->group('support');

it('separa o resolvido da efí do final da biblioteca', function () {
    /* para a Efí, paga é resolvida; para a lib, não, porque estorno ainda vem */
    expect(EfiChargeStatusEnum::PAID->isResolved())->toBeTrue()
        ->and(EfiChargeStatusEnum::PAID->status()->isFinal())->toBeFalse();
})->group('support');
