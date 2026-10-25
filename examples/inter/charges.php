<?php

use PHPay\Exceptions\PHPayException;
use PHPay\Http\Certificate;
use PHPay\Inter\InterGateway;
use PHPay\Support\{Customer, Money};

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/*
| Toda requisição do Inter vai por mTLS, inclusive a do token. O certificado
| vem em dois arquivos — .crt e .key —, e é isso que Certificate::pair() recebe.
|
| A documentação do Inter manda juntar os dois com `openssl pkcs12 -export`
| antes de usar. Não é preciso: o cURL aceita as metades como vêm.
*/
$gateway = new InterGateway(
    CLIENT_ID,
    CLIENT_SECRET,
    certificate: Certificate::pair(CERTIFICATE, PRIVATE_KEY),
    account: ACCOUNT ?: null,
);

try {
    /*
    | O valor é sempre Money: a API Pix quer reais como string ("123.45"), e
    | ler isso errado cobraria o valor errado, não quebraria a integração.
    */
    $cobranca = $gateway->charge()
        ->setKey(PIX_KEY)
        ->setAmount(Money::reais('1,00'))
        ->setCustomer(Customer::make(NAME, CPF_CNPJ))
        ->setDescription('Teste PHPay')
        ->setExpiration(3600)
        ->create();

    echo "txid: {$cobranca['txid']}" . PHP_EOL;

    /*
    | O copia-e-cola volta na própria cobrança. O Inter não expõe o endpoint de
    | QR Code que a Efí tem, então não há segunda chamada para fazer.
    */
    echo 'copia e cola: ' . ($cobranca['pixCopiaECola'] ?? '(não veio na resposta)') . PHP_EOL;

    /* consulta e cancela */
    $gateway->charge()->find($cobranca['txid']);
    $gateway->charge()->cancel($cobranca['txid']);

    /*
    | Cobrança com vencimento (cobv) exige devedor e um txid — gerado aqui
    | quando não informado.
    */
    $comVencimento = $gateway->charge()
        ->setKey(PIX_KEY)
        ->setAmount(Money::reais('50,00'))
        ->setCustomer(Customer::make(NAME, CPF_CNPJ))
        ->setDueDate(date('Y-m-d', strtotime('+7 days')), 15)
        ->create();

    echo "cobv txid: {$comVencimento['txid']}" . PHP_EOL;

    /* Pix recebidos nos últimos 30 dias */
    $gateway->charge()->getAllPix();
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
