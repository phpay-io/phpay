<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PagarMe\Enums\{IntervalEnum, PaymentMethodEnum};
use PHPay\PagarMe\PagarMeGateway;
use PHPay\PagarMe\Resources\Subscription\Subscription;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$gateway = new PagarMeGateway(SECRET_KEY_PAGARME);

/**
 * @var Subscription $phpay
 */
$phpay = PHPay::gateway($gateway)->subscription();

try {
    /* preço do plano em CENTAVOS */
    $plano = $phpay->createPlan([
        'name'            => 'Plano PHPay Mensal',
        'interval'        => IntervalEnum::MONTH->value,
        'interval_count'  => 1,
        'payment_methods' => [PaymentMethodEnum::CREDIT_CARD->value, PaymentMethodEnum::PIX->value],
        'items'           => [[
            'name'           => 'Mensalidade',
            'quantity'       => 1,
            'pricing_scheme' => ['price' => 4990],
        ]],
    ]);

    $planoId = (string) $plano['id'];

    /* o cliente pode nascer junto com a assinatura */
    $assinatura = $phpay
        ->setPlan($planoId)
        ->setCustomer([
            'name'     => NAME,
            'email'    => EMAIL,
            'document' => DOCUMENT,
            'type'     => 'individual',
        ])
        ->create(['payment_method' => PaymentMethodEnum::PIX->value]);

    $assinaturaId = (string) $assinatura['id'];

    $phpay->find($assinaturaId);
    $phpay->setFilter(['size' => 10])->getAll();

    /* assinatura sem plano: a recorrência vai no próprio payload */
    PHPay::gateway($gateway)->subscription()
        ->setCustomerId((string) $assinatura['customer']['id'])
        ->create([
            'payment_method' => PaymentMethodEnum::PIX->value,
            'interval'       => IntervalEnum::MONTH->value,
            'interval_count' => 1,
            'items'          => [[
                'name'           => 'Avulso mensal',
                'quantity'       => 1,
                'pricing_scheme' => ['price' => 2990],
            ]],
        ]);

    $phpay->cancel($assinaturaId);
    $phpay->destroyPlan($planoId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
