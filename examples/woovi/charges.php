<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Support\Money;
use PHPay\Woovi\Enums\PixKeyTypeEnum;
use PHPay\Woovi\WooviGateway;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * O Woovi é o segundo gateway da biblioteca com as cinco capacidades — o
 * outro é o Asaas.
 *
 * @var WooviGateway $gateway
 */
$gateway = new WooviGateway(WOOVI_APP_ID);

$phpay = PHPay::gateway($gateway);

try {
    /*
    | Cobrança. Valor em CENTAVOS, e o correlationID é o id no SEU sistema —
    | é por ele que você consulta depois, sem precisar guardar o id do gateway.
    */
    $cobranca = $phpay->charge()
        ->setCorrelationId('pedido-' . time())
        ->setCustomer(['name' => NAME, 'email' => EMAIL])
        ->create(Money::reais(100.50));   /* R$ 100,50 */

    echo $phpay->charge()->getPixCode($cobranca) . PHP_EOL;

    $phpay->charge()->find('pedido-1');
    $phpay->charge()->setQueryParams(['status' => 'ACTIVE'])->getAll();

    /*
    | Chaves Pix. Junto com o Asaas, é o único gateway da biblioteca que
    | gerencia chaves de verdade — por ser PSP.
    */
    $chave = $phpay->pix()->createKey(PixKeyTypeEnum::RANDOM);

    $phpay->pix()->getAll();

    /* consulta uma chave de terceiro antes de pagar */
    $phpay->pix()->verifyKey('destinatario@exemplo.test');

    /* QR Code estático: sem valor, o pagador escolhe quanto pagar */
    $phpay->pix()->staticQrCode('Caixa 1');
    $phpay->pix()->staticQrCode('Mensalidade', Money::reais(49.90), 'mensalidade-2026');

    /* Webhooks com CRUD por API — também só aqui e no Asaas */
    $phpay->webhook([
        'name' => 'PHPay',
        'url'  => 'https://exemplo.test/webhook/woovi',
    ])->create();

    $phpay->webhook()->getAll();

    /* Assinatura: cobrança recorrente por Pix */
    $phpay->subscription()
        ->setCustomer(['name' => NAME, 'email' => EMAIL])
        ->setDayGenerateCharge(10)
        ->create(Money::reais(49.90));

    /* Cliente avulso */
    $phpay->customer(['name' => NAME, 'email' => EMAIL])->create();
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
