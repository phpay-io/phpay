<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * @var Pix $phpay
 */
$phpay = (new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX)))->pix();

try {
    /* cria uma chave pix aleatória (EVP) */
    $key = $phpay->createKey();

    $pixKeyId = $key['id'];

    /* busca a chave */
    $phpay->find($pixKeyId);

    /* lista as chaves (offset=0, limit=100 por padrão) */
    $phpay->getAll();

    /* lista com paginação própria */
    $phpay->setQueryParams(['offset' => 0, 'limit' => 10])->getAll();

    /* qrcode estático */
    $qrCode = $phpay->staticQrCode([
        'addressKey'  => $key['key'],
        'description' => 'Doação PHPay',
        'value'       => 25.00,
        'format'      => 'ALL',
    ]);

    $phpay->destroyStaticQrCode($qrCode['id']);

    /* remove a chave */
    $phpay->destroy($pixKeyId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
