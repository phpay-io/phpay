<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$customer = [
    'name'    => NAME,
    'cpfCnpj' => CPF_CNPJ,
];

/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

try {
    $subscriptionCreated = $phpay
        ->setCustomer($customer)
        ->create([
            'billingType' => 'BOLETO',
            'value'       => 100,
            'nextDueDate' => date('Y-m-d', strtotime('+7 days')),
            'discount'    => [
                'value'            => 10,
                'dueDateLimitDays' => 5,
                'type'             => 'FIXED', /* PERCENTAGE */
            ],
            'interest' => [
                'value' => 2,
            ],
            'fine' => [
                'value' => 1,
                'type'  => 'FIXED', /* PERCENTAGE */
            ],
            'cycle'             => 'MONTHLY',
            'description'       => 'Teste de assinatura',
            'maxPayments'       => 12,
            'externalReference' => '123456',
        ]);

    print_r($subscriptionCreated);

    /* para uma segunda assinatura do mesmo cliente, reaproveite o id */
    $phpay
        ->setCustomerId($subscriptionCreated['customer'])
        ->create([
            'billingType' => 'PIX',
            'value'       => 50,
            'nextDueDate' => date('Y-m-d', strtotime('+7 days')),
            'cycle'       => 'MONTHLY',
        ]);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
