<?php

use PHPay\Efi\EfiGateway;
use PHPay\Efi\Resources\Charge\Charge;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$customer = [
    'name'     => NAME,
    'cpf_cnpj' => CPF_CNPJ,
];

/*
| Atenção: o Efí trabalha com valores em CENTAVOS, como inteiro.
| R$ 100,50 é 10050 — passar 100.50 cobraria um real.
*/
$charge = [
    'value'       => 10050,
    'description' => 'Teste de fatura',
    'expire_at'   => date('Y-m-d', strtotime('+1 day')),
];

/**
 * o gateway não faz chamada de rede aqui — a autorização acontece
 * na primeira vez que um recurso precisa do token.
 *
 * @var EfiGateway $gateway
 */
$gateway = new EfiGateway(CLIENT_ID, CLIENT_SECRET);

$phpay = new PHPay($gateway);

try {
    /**
     * @var Charge $chargeResource
     */
    $chargeResource = $phpay->charge($charge);

    $chargeCreated = $chargeResource
        ->setCustomer($customer)
        ->create();

    $chargeId = (string) $chargeCreated['data']['charge_id'];

    /* lista todas as cobranças */
    $phpay->charge()->getAll();

    /* busca por id */
    $phpay->charge()->find($chargeId);

    /* status da cobrança */
    $phpay->charge()->getStatus($chargeId);

    /* atualiza o vencimento */
    $phpay->charge()->updateDueDate($chargeId, date('Y-m-d', strtotime('+10 days')));

    /* atualiza metadados do boleto */
    $phpay->charge()->updateMetadata($chargeId, [
        'notification_url' => 'https://exemplo.test/webhook/efi',
        'custom_id'        => '123456',
    ]);

    /* confirma o recebimento */
    $phpay->charge()->confirmReceipt($chargeId);

    /* cancela */
    $phpay->charge()->cancel($chargeId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
