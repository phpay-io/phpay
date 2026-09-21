<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Enums\SubscriptionCycleEnum;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Support\{Customer, Money};

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$phpay = PHPay::gateway(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

/**
 * @var Subscription $subscriptions
 */
$subscriptions = $phpay->subscription();

try {
    $subscription = $subscriptions
        ->setCustomer(Customer::make(NAME, CPF_CNPJ))
        ->setAmount(Money::reais('100,00'))
        ->setCycle(SubscriptionCycleEnum::MONTHLY)
        ->setSubscription([
            'billingType' => 'BOLETO',
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
            'description'       => 'Teste de assinatura',
            'maxPayments'       => 12,
            'externalReference' => '123456',
        ])
        ->create();

    $id = (string) $subscription['id'];

    /* consulta */
    $subscriptions->find($id);
    $subscriptions->setQueryParams(['customer' => $subscription['customer']])->getAll();

    /* as cobranças que a assinatura já gerou */
    $subscriptions->getPayments($id);

    /* o carnê, em PDF */
    file_put_contents(__DIR__ . '/carne.pdf', $subscriptions->paymentBook($id));

    /* muda a descrição das próximas cobranças e das pendentes */
    $subscriptions->update($id, [
        'description'           => 'Assinatura atualizada',
        'updatePendingPayments' => true,
    ]);

    /* pausa e reativa — reativar exige um novo vencimento */
    $subscriptions->deactivate($id);
    $subscriptions->reactivate($id, date('Y-m-d', strtotime('+30 days')));

    /*
    | para uma segunda assinatura do mesmo cliente, reaproveite o id — num
    | recurso novo, porque o anterior guarda o que os setters montaram
    */
    $phpay->subscription()
        ->setCustomerId((string) $subscription['customer'])
        ->create([
            'billingType' => 'PIX',
            'value'       => 50,
            'nextDueDate' => date('Y-m-d', strtotime('+7 days')),
            'cycle'       => 'MONTHLY',
        ]);

    /* remove — as cobranças pendentes vão junto */
    $subscriptions->destroy($id);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
