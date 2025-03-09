<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$customer = [
    'name'    => NAME,
    'cpfCnpj' => CPF_CNPJ,
];

$subscriptionId = 'sub_e3knxyfo6ffgb6kg';

/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX))->subscription();

/* subscription store */
$phpay->setCustomer($customer)
    ->create([
        'billingType' => 'BOLETO',
        'value'       => 100,
        'nextDueDate' => '2025-04-09',
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
        // 'split' => [
        //     [
        //         'walletId' => 'rec_123456',
        //         'fixedValue' => 50,
        //         'percentageValue' => 50,
        //         'externalReference' => '123456',
        //         'description' => 'Teste de divisão',
        //     ],
        // ],
        // 'callback' => [
        //     'successUrl' => 'https://example.com/success',
        //     'autoRedirect' => true,
        // ]
    ]);

print_r($subscriptionCreated);
