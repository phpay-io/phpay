<?php

use PHPay\AbacatePay\AbacatePayGateway;
use PHPay\AbacatePay\Resources\Charge\Charge;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;
use PHPay\Support\Money;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

$gateway = new AbacatePayGateway(ABACATEPAY_TOKEN);

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway($gateway)->charge();

$cliente = [
    'name'      => NAME,
    'email'     => EMAIL,
    'cellphone' => CELLPHONE,
    'taxId'     => TAX_ID,
];

try {
    /*
    | A cobrança é um link de pagamento montado a partir de PRODUTOS, não de um
    | valor solto — o total vem calculado na resposta, em `amount`.
    |
    | Preço em CENTAVOS, com mínimo de 100 (R$ 1,00) por produto.
    | O externalId é o id do produto no SEU sistema, e precisa ser único.
    */
    $cobranca = $phpay
        ->setCustomer($cliente)
        ->addProduct('prod-1234', 'Assinatura PHPay', Money::reais(20.00))            /* R$ 20,00 */
        ->addProduct('prod-5678', 'Camiseta', Money::reais(59.90), 2, 'Tamanho M')    /* R$ 59,90 cada */
        ->setUrls(
            completionUrl: 'https://exemplo.test/obrigado',
            returnUrl: 'https://exemplo.test/loja'
        )
        ->create();

    /* o link para onde você manda o cliente */
    echo $phpay->getPaymentUrl($cobranca) . PHP_EOL;

    /*
    | Como a URL é única para os dois ambientes, é a cobrança que diz em qual
    | deles ela nasceu.
    */
    var_dump($phpay->isDevMode($cobranca));

    /* listagem */
    $phpay->setQueryParams(['limit' => 10])->getAll();

    /* cliente avulso, para reaproveitar em cobranças futuras */
    $criado = PHPay::gateway($gateway)->customer($cliente)->create();

    PHPay::gateway($gateway)->charge()
        ->setCustomerId((string) ($criado['data']['id'] ?? ''))
        ->addProduct('prod-1234', 'Assinatura PHPay', Money::reais(20.00))
        ->setUrls('https://exemplo.test/obrigado', 'https://exemplo.test/loja')
        ->create();

    /*
    | Cupons de desconto não passam pela facade: nenhum outro gateway da
    | biblioteca tem isso, então vive no gateway concreto.
    */
    $gateway->coupons()->create([
        'code'         => 'PHPAY10',
        'discountKind' => 'PERCENTAGE',
        'discount'     => 10,
        'maxRedeems'   => 100,
    ]);

    $gateway->coupons()->getAll();
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
