<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$gateway = new PagarMeGateway(SECRET_KEY_PAGARME);

/* o ambiente vem do prefixo da chave, não de uma url separada */
var_dump($gateway->isSandbox());

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway($gateway)->charge();

$customer = [
    'name'     => NAME,
    'email'    => EMAIL,
    'document' => DOCUMENT,
    'type'     => 'individual',
];

try {
    /*
    | Pix é forma de pagamento do pedido. Todo valor é inteiro em CENTAVOS:
    | R$ 100,50 é 10050.
    */
    $pedido = $phpay
        ->setCustomer($customer)
        ->addItem('Assinatura PHPay', 10050)
        ->setPix(1800)
        ->create();

    $pedidoId = (string) $pedido['id'];

    /* copia-e-cola, que vem em charges[0].last_transaction.qr_code */
    echo $phpay->getPixCode($pedidoId) . PHP_EOL;

    $phpay->find($pedidoId);
    $phpay->setQueryParams(['size' => 10])->getAll();

    $cobrancaId = (string) $pedido['charges'][0]['id'];

    echo $phpay->getStatus($cobrancaId) . PHP_EOL;

    /* estorno parcial e total, em centavos, via DELETE */
    $phpay->cancel($cobrancaId, 2500);
    $phpay->cancel($cobrancaId);

    /* boleto, reaproveitando um cliente que já existe */
    PHPay::gateway($gateway)->charge()
        ->setCustomerId((string) $pedido['customer']['id'])
        ->addItem('Camiseta', 5990, 2)
        ->setBoleto(date('Y-m-d', strtotime('+5 days')), ['Não receber após o vencimento'])
        ->create();

    /*
    | Leitura de entregas de webhook. Não passa pela facade: é específico do
    | Pagar.me, então vive no gateway concreto.
    |
    | O cadastro dos endpoints que recebem esses eventos é feito no dashboard,
    | não pela API — por isso o gateway não declara SupportsWebhooks.
    */
    $gateway->webhookDeliveries()->setFilter(['size' => 10])->getAll();
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
