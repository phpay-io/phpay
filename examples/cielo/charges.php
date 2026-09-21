<?php

use PHPay\Cielo\CieloGateway;
use PHPay\Cielo\Enums\SaleStatusEnum;
use PHPay\Cielo\Resources\Charge\Charge;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Support\Money;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway(new CieloGateway(CIELO_MERCHANT_ID, CIELO_MERCHANT_KEY))->charge();

try {
    /*
    | Venda com Pix. Todo valor é inteiro em CENTAVOS: R$ 157,00 é 15700.
    |
    | A criação vai para o host de escrita; as consultas abaixo vão para o de
    | query. O PHPay roteia sozinho.
    */
    $venda = $phpay
        ->setOrderId('pedido-' . time())
        ->setCustomer(['Name' => NAME])
        ->setPix(Money::reais(157.00))
        /* use uma chave estável do seu domínio para tornar o retry seguro */
        ->setRequestId('pedido-123456')
        ->create();

    $paymentId = (string) $venda['Payment']['PaymentId'];

    /* copia-e-cola do Pix */
    echo $phpay->getPixCode($paymentId) . PHP_EOL;

    /* status, como enum */
    $status = $phpay->getStatus($paymentId);

    if ($status !== null) {
        echo SaleStatusEnum::from($status)->name . PHP_EOL;
    }

    /* consulta pelas vendas de um pedido do seu sistema */
    $phpay->findByOrderId('pedido-123456');

    /*
    | Venda com cartão em duas etapas: autoriza agora, captura depois.
    | Passe capture: true para autorizar e capturar de uma vez.
    */
    $comCartao = PHPay::gateway(new CieloGateway(CIELO_MERCHANT_ID, CIELO_MERCHANT_KEY))
        ->charge()
        ->setCustomer(['Name' => NAME])
        ->setCreditCard(Money::reais(157.00), [
            'CardNumber'     => '0000000000000001',
            'Holder'         => 'Mario Lucas',
            'ExpirationDate' => '12/2030',
            'SecurityCode'   => '123',
            'Brand'          => 'Visa',
        ], installments: 3)
        ->create();

    $cartaoId = (string) $comCartao['Payment']['PaymentId'];

    $phpay->capture($cartaoId);           /* captura total */
    $phpay->cancel($cartaoId, 2500);      /* estorna R$ 25,00 */
    $phpay->cancel($cartaoId);            /* estorna o restante */
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
