<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$customer = [
    'name'    => NAME,
    'cpfCnpj' => CPF_CNPJ,
];

/**
 * @var AsaasGateway $phpay
 */
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

try {
    /**
     * cria o cliente
     *
     * @see available fields in https://docs.asaas.com/reference/criar-novo-cliente
     */
    $customerCreated = $phpay
        ->customer($customer)
        ->create();

    /* lista todos */
    $phpay->customer()->getAll();

    /* lista com filtro */
    $phpay
        ->customer()
        ->setFilter(['cpfCnpj' => $customerCreated['cpfCnpj']])
        ->getAll();

    /* busca por id */
    $phpay->customer()->find($customerCreated['id']);

    /* atualiza */
    $phpay
        ->customer(['name' => 'Mário Lucas Updated'])
        ->update($customerCreated['id']);

    /* notificações do cliente */
    $phpay->customer()->getNotifications($customerCreated['id']);

    /* remove e restaura */
    $phpay->customer()->destroy($customerCreated['id']);
    $phpay->customer()->restore($customerCreated['id']);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
