<?php

use PHPay\Cielo\CieloGateway;
use PHPay\Cielo\Enums\RecurrentIntervalEnum;
use PHPay\Cielo\Resources\Subscription\Subscription;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * A Cielo não tem endpoint de "criar assinatura": a recorrência nasce de uma
 * venda que carrega um bloco RecurrentPayment, e só então ganha um
 * RecurrentPaymentId próprio para ser gerenciada. Sempre cobra cartão.
 *
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new CieloGateway(CIELO_MERCHANT_ID, CIELO_MERCHANT_KEY))->subscription();

try {
    $recorrencia = $phpay
        ->setOrderId('assinatura-' . time())
        ->setCustomer(['Name' => NAME])
        ->setCard([
            'CardNumber'     => '0000000000000001',
            'Holder'         => 'Mario Lucas',
            'ExpirationDate' => '12/2030',
            'SecurityCode'   => '123',
            'Brand'          => 'Visa',
        ])
        ->setInterval(RecurrentIntervalEnum::MONTHLY)
        ->setEndDate('2027-12-31')
        ->create(15700);   /* R$ 157,00 */

    $recorrenciaId = (string) $recorrencia['Payment']['RecurrentPayment']['RecurrentPaymentId'];

    $phpay->find($recorrenciaId);

    /* reajuste e mudança de periodicidade */
    $phpay->updateAmount($recorrenciaId, 19900);
    $phpay->updateInterval($recorrenciaId, RecurrentIntervalEnum::ANNUAL);
    $phpay->updateNextPaymentDate($recorrenciaId, date('Y-m-d', strtotime('+30 days')));
    $phpay->updateEndDate($recorrenciaId, '2028-01-31');

    /* suspender e retomar */
    $phpay->deactivate($recorrenciaId);
    $phpay->reactivate($recorrenciaId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
