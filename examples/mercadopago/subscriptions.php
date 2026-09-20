<?php

use PHPay\Exceptions\PHPayException;
use PHPay\MercadoPago\Enums\FrequencyTypeEnum;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\MercadoPago\Resources\Subscription\Subscription;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new MercadoPagoGateway(ACCESS_TOKEN_MERCADO_PAGO))->subscription();

try {
    /* sem plano associado: a recorrência vai no payload */
    $subscription = $phpay
        ->setPayerEmail(EMAIL_PAGADOR)
        ->create([
            'reason'         => 'Assinatura PHPay',
            'back_url'       => 'https://exemplo.test/retorno',
            'auto_recurring' => [
                'frequency'          => 1,
                'frequency_type'     => FrequencyTypeEnum::MONTHS->value,
                'transaction_amount' => 100.00,
                'currency_id'        => 'BRL',
            ],
        ]);

    $subscriptionId = (string) $subscription['id'];

    /* com plano associado: a recorrência vem do plano */
    $phpay
        ->setPayerEmail(EMAIL_PAGADOR)
        ->setPlan('2c938084726fca480172750000000000')
        ->create(['back_url' => 'https://exemplo.test/retorno']);

    $phpay->find($subscriptionId);

    $phpay->setQueryParams(['status' => 'authorized'])->getAll();

    $phpay->pause($subscriptionId);
    $phpay->cancel($subscriptionId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
