<?php

use PHPay\AbacatePay\Resources\Charge\Charge as AbacateCharge;
use PHPay\Asaas\Resources\Charge\Charge as AsaasCharge;
use PHPay\Cielo\Resources\Charge\Charge as CieloCharge;
use PHPay\Efi\Resources\Charge\Charge as EfiCharge;
use PHPay\MercadoPago\Resources\Charge\Charge as MpCharge;
use PHPay\PagarMe\Resources\Charge\Charge as PagarMeCharge;
use PHPay\PagBank\Resources\Charge\Charge as PagBankCharge;
use PHPay\Rede\Enums\TransactionKindEnum;
use PHPay\Rede\RedeGateway;
use PHPay\Support\Money;
use PHPay\Woovi\Resources\Charge\Charge as WooviCharge;

/*
| O ponto do value object: R$ 100,50 é 100.50 em dois gateways e 10050 em sete.
| O MESMO objeto produz o número certo em cada um, sem quem integra precisar
| saber qual é qual.
*/

it('manda reais nos gateways que esperam decimal', function () {
    $valor = Money::reais(100.50);

    $asaas = [];
    (new AsaasCharge('token', true, mockClient([jsonResponse([])], $asaas)))
        ->setCharge(['billingType' => 'PIX', 'dueDate' => '2026-01-10'])
        ->setCustomerId('cus_1')
        ->setAmount($valor)
        ->create();

    $mp = [];
    (new MpCharge('TEST-token', mockClient([jsonResponse([])], $mp)))
        ->setCharge(['payment_method_id' => 'pix'])
        ->setPayer(['email' => 'fale@phpay.io'])
        ->setAmount($valor)
        ->create();

    expect(recordedBody($asaas)['value'])->toBe(100.50)
        ->and(recordedBody($mp)['transaction_amount'])->toBe(100.50);
})->group('support');

it('manda centavos nos gateways que esperam inteiro', function () {
    $valor = Money::reais(100.50);

    $pagbank = [];
    (new PagBankCharge('token', true, mockClient([jsonResponse([])], $pagbank)))
        ->setCustomer(['name' => 'Mário', 'email' => 'a@b.com', 'tax_id' => '12345678901'])
        ->addItem('Item', $valor)
        ->setQrCode($valor)
        ->create();

    $pagarme = [];
    (new PagarMeCharge('sk_test_x', mockClient([jsonResponse([])], $pagarme)))
        ->setCustomerId('cus_1')
        ->addItem('Item', $valor)
        ->setPix()
        ->create();

    $abacate = [];
    (new AbacateCharge('token', mockClient([jsonResponse([])], $abacate)))
        ->setCustomerId('cus_1')
        ->addProduct('p1', 'Item', $valor)
        ->setUrls('https://a.test/ok', 'https://a.test/volta')
        ->create();

    $woovi = [];
    (new WooviCharge('app-id', true, mockClient([jsonResponse([])], $woovi)))
        ->setCorrelationId('pedido-1')
        ->create($valor);

    $cielo = [];
    (new CieloCharge('id', 'key', true, mockClient([jsonResponse([])], $cielo)))
        ->setCustomer(['Name' => 'Mário'])
        ->setPix($valor)
        ->create();

    expect(recordedBody($pagbank)['items'][0]['unit_amount'])->toBe(10050)
        ->and(recordedBody($pagbank)['qr_codes'][0]['amount']['value'])->toBe(10050)
        ->and(recordedBody($pagarme)['items'][0]['amount'])->toBe(10050)
        ->and(recordedBody($abacate)['products'][0]['price'])->toBe(10050)
        ->and(recordedBody($woovi)['value'])->toBe(10050)
        ->and(recordedBody($cielo)['Payment']['Amount'])->toBe(10050);
})->group('support');

it('manda centavos também na rede e no efí', function () {
    $valor = Money::reais(100.50);

    $rede    = [];
    $oauth   = [];
    $gateway = new RedeGateway(
        'pv',
        'segredo',
        true,
        mockClient([jsonResponse([])], $rede),
        mockClient([jsonResponse(['access_token' => 'tok', 'expires_in' => 3600])], $oauth)
    );

    $gateway->charge()
        ->setReference('pedido-1')
        ->setCard('5448280000000007', 'M L', '12', '2030', '123')
        ->setPayment($valor, TransactionKindEnum::CREDIT)
        ->create();

    $efi = [];
    (new EfiCharge(['access_token' => 'tok', 'token_type' => 'Bearer'], [
        'description' => 'Item',
        'expire_at'   => date('Y-m-d', strtotime('+1 day')),
    ], true, mockClient([jsonResponse([])], $efi)))
        ->setAmount($valor)
        ->setCustomer(['name' => 'Mário', 'cpf_cnpj' => '12345678901'])
        ->create();

    expect(recordedBody($rede)['amount'])->toBe(10050)
        ->and(recordedBody($efi)['items'][0]['value'])->toBe(10050);
})->group('support');

it('número cru continua sendo lido na unidade que o gateway já esperava', function () {
    /* compatibilidade: quem passava int antes do Money continua funcionando */
    $pagbank = [];
    (new PagBankCharge('token', true, mockClient([jsonResponse([])], $pagbank)))
        ->setCustomer(['name' => 'Mário', 'email' => 'a@b.com', 'tax_id' => '12345678901'])
        ->addItem('Item', 10050)
        ->setQrCode(10050)
        ->create();

    $asaas = [];
    (new AsaasCharge('token', true, mockClient([jsonResponse([])], $asaas)))
        ->setCharge(['billingType' => 'PIX', 'dueDate' => '2026-01-10'])
        ->setCustomerId('cus_1')
        ->setAmount(100.50)
        ->create();

    expect(recordedBody($pagbank)['items'][0]['unit_amount'])->toBe(10050)
        ->and(recordedBody($asaas)['value'])->toBe(100.50);
})->group('support');
