<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$customer = [
    'name'    => NAME,
    'cpfCnpj' => CPF_CNPJ,
];

$charge = [
    'billingType' => 'BOLETO',
    'value'       => 100.00,
    'dueDate'     => date('Y-m-d', strtotime('+3 days')),
    'description' => 'Cobrança de teste do PHPay',
];

/**
 * @var Charge $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)))->charge();

/*
| Toda falha — validação local ou erro do gateway — vira exceção.
| Um array de retorno é sempre uma resposta de sucesso.
*/
try {
    /* cria a cobrança criando também o cliente */
    $chargeCreated = $phpay
        ->setCharge($charge)
        ->setCustomer($customer)
        ->create();

    $chargeId = $chargeCreated['id'];

    /*
    | Para novas cobranças do mesmo cliente, reaproveite o id:
    | ->setCustomerId($chargeCreated['customer'])
    */

    /* busca a cobrança */
    $phpay->find($chargeId);

    /* lista todas as cobranças */
    $phpay->getAll();

    /* lista com filtros */
    $phpay
        ->setQueryParams(['limit' => 2])
        ->getAll();

    /* atualiza a cobrança */
    $phpay->update($chargeId, ['value' => 150.00]);

    /* status, linha digitável e qrcode */
    $phpay->getStatus($chargeId);
    $phpay->getDigitableLine($chargeId);
    $phpay->getQrCodePix($chargeId);

    /* confirma o recebimento em dinheiro */
    $phpay->confirmReceipt($chargeId, [
        'paymentDate'    => date('Y-m-d'),
        'value'          => 100.00,
        'notifyCustomer' => true,
    ]);

    /* desfaz a confirmação */
    $phpay->undoConfirmReceipt($chargeId);

    /* remove e restaura */
    $phpay->destroy($chargeId);
    $phpay->restore($chargeId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
