<?php

namespace PHPay\Asaas\Resources\WebhookSignature\Interface;

use PHPay\Webhooks\VerifiesSignature;

interface WebhookSignatureInterface extends VerifiesSignature
{
    /**
     * secret the webhook was registered with
     *
     * @param string $secret
     * @return WebhookSignatureInterface
     */
    public function setSecret(string $secret): WebhookSignatureInterface;

    /**
     * raw request body, exactly as it arrived
     *
     * @param string $payload
     * @return WebhookSignatureInterface
     */
    public function setPayload(string $payload): WebhookSignatureInterface;

    /**
     * headers of the request, in any of the usual shapes
     *
     * @param array<mixed> $headers
     * @return WebhookSignatureInterface
     */
    public function setHeaders(array $headers): WebhookSignatureInterface;

    /**
     * whether the notification really came from the gateway
     *
     * @return bool
     */
    public function verify(): bool;
}
