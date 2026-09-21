<?php

use PHPay\Exceptions\PHPayException;
use PHPay\MercadoPago\Enums\PaymentMethodEnum;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\MercadoPago\Resources\Charge\Charge;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$gateway = new MercadoPagoGateway(ACCESS_TOKEN_MERCADO_PAGO);

/* o ambiente vem do prefixo do token, não de uma url separada */
var_dump($gateway->isSandbox());

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway($gateway)->charge();

try {
    /* cobrança via Pix — aqui o Pix é forma de pagamento, não recurso à parte */
    $charge = $phpay
        ->setCharge([
            'transaction_amount' => 100.00,
            'payment_method_id'  => PaymentMethodEnum::PIX->value,
            'description'        => 'Cobrança de teste do PHPay',
            'external_reference' => '123456',
            /* webhooks não têm CRUD por API: aponte a url por pagamento */
            'notification_url' => 'https://exemplo.test/webhook/mercadopago',
        ])
        ->setPayer(['email' => EMAIL_PAGADOR])
        /* use uma chave estável do seu domínio para tornar o retry seguro */
        ->setIdempotencyKey('pedido-123456')
        ->create();

    $chargeId = (string) $charge['id'];

    /* código copia-e-cola do Pix */
    echo $phpay->getPixCode($chargeId) . PHP_EOL;

    /* consulta */
    $phpay->find($chargeId);
    echo $phpay->getStatus($chargeId) . PHP_EOL;

    /* busca com filtros */
    $phpay
        ->setQueryParams(['status' => 'approved', 'limit' => 10])
        ->getAll();

    /* estorno total e parcial */
    $phpay->refund($chargeId);
    $phpay->refund($chargeId, 25.00);

    /* cancelamento */
    $phpay->cancel($chargeId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
