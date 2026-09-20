<?php

namespace PHPay\Contracts;

/**
 * the gateway lets webhooks be managed through the API.
 */
interface SupportsWebhooks extends GatewayInterface
{
    /**
     * get resource webhook from gateway.
     *
     * @param array<mixed> $webhook
     * @return object
     */
    public function webhook(array $webhook = []): object;
}
