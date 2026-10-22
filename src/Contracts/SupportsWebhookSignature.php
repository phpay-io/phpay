<?php

namespace PHPay\Contracts;

/**
 * the gateway signs its webhooks in a way the receiver can verify.
 *
 * declared only where the gateway documents how: a notification that
 * carries no signature cannot be told apart from one forged by whoever
 * learns the URL, and a verifier that always answers true would be worse
 * than none.
 */
interface SupportsWebhookSignature extends GatewayInterface
{
    /**
     * get resource webhook signature from gateway.
     *
     * @return object
     */
    public function webhookSignature(): object;
}
