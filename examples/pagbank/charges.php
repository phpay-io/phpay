<?php

use PHPay\Exceptions\PHPayException;
use PHPay\PagBank\Enums\PaymentMethodEnum;
use PHPay\PagBank\PagBankGateway;
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * @var Charge $phpay
 */
$phpay = PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))->charge();

$customer = [
    'name'   => NAME,
    'email'  => EMAIL,
    'tax_id' => TAX_ID,
];

try {
    /*
    | Pedido com Pix. Repare que o Pix NÃO é uma charge: ele entra como
    | qr_codes do pedido, e só um por pedido. A conta precisa ter uma chave
    | Pix ativa no PagBank.
    |
    | Todo valor é inteiro em CENTAVOS: R$ 100,50 é 10050.
    */
    $pedido = $phpay
        ->setCustomer($customer)
        ->addItem('Assinatura PHPay', 10050)
        ->setQrCode(10050)
        /* sem CRUD de webhook na API: a notificação é por pedido */
        ->setNotificationUrls(['https://exemplo.test/webhook/pagbank'])
        ->create();

    $pedidoId = (string) $pedido['id'];

    /* código copia-e-cola, que vem em qr_codes[0].text */
    echo $phpay->getPixCode($pedidoId) . PHP_EOL;

    /* consulta do pedido */
    $phpay->find($pedidoId);

    /*
    | Pedido com cartão. Aqui sim a cobrança vai em charges.
    | O card exige tokenização — veja a documentação do PagBank.
    */
    $comCartao = PHPay::gateway(new PagBankGateway(TOKEN_PAGBANK_SANDBOX))
        ->charge()
        ->setCustomer($customer)
        ->addItem('Camiseta', 5990, 2)
        ->setCharges([[
            'reference_id'   => 'cobranca-1',
            'description'    => 'Camiseta',
            'amount'         => ['value' => 11980, 'currency' => 'BRL'],
            'payment_method' => [
                'type'         => PaymentMethodEnum::CREDIT_CARD->value,
                'installments' => 1,
                'capture'      => true,
                /* 'card' => ['encrypted' => '...'] */
            ],
        ]])
        ->create();

    $cobrancaId = (string) $comCartao['charges'][0]['id'];

    echo $phpay->getStatus($cobrancaId) . PHP_EOL;

    /* estorno parcial e total, também em centavos */
    $phpay->refund($cobrancaId, 2500);
    $phpay->refund($cobrancaId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
