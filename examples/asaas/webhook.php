<?php

use PHPay\Asaas\AsaasGateway;
use PHPay\Asaas\Resources\Webhook\Enum\WebhookEventsEnum;
use PHPay\Exceptions\PHPayException;
use PHPay\PHPay;

require_once __DIR__ . '/../../vendor/autoload.php';

require_once __DIR__ . '/credentials.php';

/**
 * @var AsaasGateway $phpay
 */
$phpay = new PHPay(new AsaasGateway(TOKEN_ASAAS_SANDBOX));

try {
    /**
     * cria um webhook
     *
     * @see available fields in https://docs.asaas.com/reference/criar-novo-webhook
     */
    $webhookCreated = $phpay
        ->webhook(WEBHOOK)
        ->create();

    $webhookId = $webhookCreated['id'];

    /* ou passando o payload direto no create, usando o enum de eventos */
    $phpay->webhook()->create([
        'name'     => 'PHPay eventos de cobrança',
        'url'      => 'https://exemplo.test/webhook/cobrancas',
        'email'    => 'fale@phpay.io',
        'enabled'  => true,
        'sendType' => 'SEQUENTIALLY',
        'events'   => [
            WebhookEventsEnum::PAYMENT_RECEIVED->value,
            WebhookEventsEnum::PAYMENT_OVERDUE->value,
        ],
    ]);

    /* lista todos */
    $phpay->webhook()->getAll();

    /* busca por id */
    $phpay->webhook()->find($webhookId);

    /**
     * atualiza
     *
     * @see available fields in https://docs.asaas.com/reference/atualizar-webhook-existente
     */
    $phpay->webhook()->update($webhookId, [
        'name' => 'Update webhook with PHPay is awesome',
        'url'  => 'https://sixtec.com.br/webhook/atualizado',
    ]);

    /* remove */
    $phpay->webhook()->destroy($webhookId);
} catch (PHPayException $exception) {
    echo $exception->getMessage() . PHP_EOL;
}
