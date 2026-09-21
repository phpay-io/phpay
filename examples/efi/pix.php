<?php

use PHPay\Efi\EfiGateway;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Support\{Customer, Money};

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/*
| A API Pix do Efí exige mTLS em toda requisição, inclusive a do token: sem o
| certificado da aplicação, nada funciona. $sandbox fica no padrão (true), e o
| certificado precisa ser o de homologação.
*/
$gateway = new EfiGateway(CLIENT_ID, CLIENT_SECRET, certificate: CERTIFICATE);

$phpay = PHPay::gateway($gateway);

try {
    /*
    | Na API Pix o valor é sempre Money: ela quer reais ("123.45"), enquanto a
    | API de Cobranças do mesmo gateway quer centavos.
    */
    $cobranca = $gateway->pixCharge()
        ->setAmount(Money::reais('1,00'))
        ->setKey(PIX_KEY)
        ->setCustomer(Customer::make(NAME, CPF_CNPJ))
        ->setDescription('Teste PHPay')
        ->setExpiration(3600)
        ->create();

    $qr = $gateway->pixCharge()->qrCode($cobranca['loc']['id']);

    echo "txid: {$cobranca['txid']}" . PHP_EOL;
    echo "copia e cola: {$qr['qrcode']}" . PHP_EOL;

    /* consulta e cancela */
    $gateway->pixCharge()->find($cobranca['txid']);
    $gateway->pixCharge()->cancel($cobranca['txid']);

    /* chaves aleatórias da conta */
    $phpay->pix()->getAll();

    /* webhooks configurados */
    $phpay->webhook()->getAll();
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
