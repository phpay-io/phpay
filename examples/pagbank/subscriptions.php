<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PagBank\Enums\IntervalUnitEnum;
use PHPay\PagBank\PagBankGateway;
use PHPay\PagBank\Resources\Subscription\Subscription;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * Assinaturas do PagBank vivem em outro host — api.assinaturas.pagseguro.com.
 * O PHPay resolve isso sozinho: o recurso boota o client da API certa.
 *
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))->subscription();

try {
    /* toda assinatura pertence a um plano; valor em CENTAVOS */
    $plano = $phpay->createPlan([
        'name'        => 'Plano PHPay Mensal',
        'description' => 'Acesso mensal',
        'amount'      => ['value' => 4990, 'currency' => 'BRL'],
        'interval'    => ['unit' => IntervalUnitEnum::MONTHS->value, 'length' => 1],
    ]);

    $planoId = (string) $plano['id'];

    /* o assinante pode ser criado junto com a assinatura */
    $assinatura = $phpay
        ->setPlan($planoId)
        ->setCustomer([
            'name'   => NAME,
            'email'  => EMAIL,
            'tax_id' => TAX_ID,
        ])
        ->create();

    $assinaturaId = (string) $assinatura['id'];

    /* ou reaproveitado pelo id, se já existir */
    PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))
        ->subscription()
        ->setPlan($planoId)
        ->setCustomerId((string) $assinatura['customer']['id'])
        ->create();

    $phpay->find($assinaturaId);
    $phpay->setFilter(['offset' => 0, 'limit' => 10])->getAll();

    $phpay->suspend($assinaturaId);
    $phpay->activate($assinaturaId);
    $phpay->cancel($assinaturaId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
