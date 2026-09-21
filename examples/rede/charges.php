<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Rede\Enums\{TransactionKindEnum, TransactionStatusEnum};
use PHPay\Rede\RedeGateway;
use PHPay\Rede\Resources\Charge\Charge;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/*
| Nenhuma chamada de rede acontece aqui: o token OAuth é negociado na primeira
| vez que um recurso precisa dele, e renegociado sozinho quando expira.
*/
$gateway = new RedeGateway(REDE_PV, REDE_TOKEN);

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway($gateway)->charge();

try {
    /*
    | Autoriza e captura de uma vez. Passe capture: false para autorizar agora
    | e capturar depois.
    |
    | Todo valor é inteiro em CENTAVOS: R$ 20,99 é 2099.
    */
    $transacao = $phpay
        ->setReference('pedido-' . time())
        ->setCard('5448280000000007', 'MARIO LUCAS', '12', '2030', '123')
        ->setPayment(2099, TransactionKindEnum::CREDIT, installments: 1, capture: true)
        ->setSoftDescriptor('PHPAY')
        ->create();

    $tid = (string) $transacao['tid'];

    /* "00" significa aprovada */
    $codigo = $phpay->getStatus($tid);

    if ($codigo !== null && TransactionStatusEnum::approved($codigo)) {
        echo "Aprovada\n";
    }

    /* consulta pela referência do seu sistema */
    $phpay->findByReference('pedido-1');

    /* estorno parcial e total, em centavos */
    $phpay->refund($tid, 1000);
    $phpay->refund($tid);

    /*
    | Fluxo em duas etapas: autoriza agora, captura quando o pedido for
    | separado. Entre as duas, o valor fica reservado no limite do portador
    | mas não vira cobrança.
    */
    $emDuasEtapas = PHPay::gateway($gateway)->charge()
        ->setReference('pedido-2-etapas-' . time())
        ->setCard('5448280000000007', 'MARIO LUCAS', '12', '2030', '123')
        ->setPayment(5000, TransactionKindEnum::CREDIT, capture: false)
        ->create();

    $phpay->capture((string) $emDuasEtapas['tid']);

    /* num processo longo, dá para inspecionar ou descartar o token em mãos */
    var_dump($gateway->authorization()->hasValidToken());
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
