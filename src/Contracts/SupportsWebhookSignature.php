<?php

namespace PHPay\Contracts;

use PHPay\Webhooks\VerifiesSignature;

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
     * narrowed from `object` to the contract, which is covariant and so
     * compatible: code that received an object still receives one, now with a
     * surface it can call. Before this, generic code — a framework bridge, any
     * middleware that resolves the gateway by name — had nothing to type
     * against.
     *
     * @return VerifiesSignature
     */
    public function webhookSignature(): VerifiesSignature;
}
