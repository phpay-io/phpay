<?php

namespace PHPay\Woovi\Resources\WebhookSignature\Interface;

use PHPay\Webhooks\VerifiesSignature;

interface WebhookSignatureInterface extends VerifiesSignature
{
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
     * HMAC secret of the webhook, for the deprecated scheme
     *
     * @param string $secret
     * @return WebhookSignatureInterface
     */
    public function setSecret(string $secret): WebhookSignatureInterface;

    /**
     * public keys to verify against, instead of fetching them
     *
     * @param array<int, string> $publicKeys PEM encoded
     * @return WebhookSignatureInterface
     */
    public function setPublicKeys(array $publicKeys): WebhookSignatureInterface;

    /**
     * whether the notification really came from the gateway
     *
     * @return bool
     */
    public function verify(): bool;
}
