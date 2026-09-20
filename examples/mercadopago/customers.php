<?php

use PHPay\Exceptions\PHPayException;
use PHPay\MercadoPago\MercadoPagoGateway;
use PHPay\MercadoPago\Resources\Customer\Customer;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * No Mercado Pago o cliente serve a cartões salvos — não é pré-requisito para
 * cobrar, já que o pagamento carrega payer.email direto.
 *
 * @var Customer $phpay
 */
$phpay = PHPay::gateway(new MercadoPagoGateway(ACCESS_TOKEN_MERCADO_PAGO))
    ->customer(['email' => EMAIL_PAGADOR, 'first_name' => 'Mário', 'last_name' => 'Lucas']);

try {
    $customer = $phpay->create();

    $phpay->find($customer['id']);

    /* a API não oferece exclusão de cliente */
    $phpay->update($customer['id']);

    /* busca por e-mail devolve null quando ninguém casa */
    $encontrado = $phpay->findByEmail(EMAIL_PAGADOR);

    print_r($encontrado);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
