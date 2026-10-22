<?php

namespace PHPay\AbacatePay\Resources\WebhookSignature\Interface;

interface WebhookSignatureInterface
{
    /**
     * secret you put in the webhook URL
     *
     * @param string $secret
     * @return WebhookSignatureInterface
     */
    public function setSecret(string $secret): WebhookSignatureInterface;

    /**
     * query string of the incoming request
     *
     * @param array<mixed> $query
     * @return WebhookSignatureInterface
     */
    public function setQuery(array $query): WebhookSignatureInterface;

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
     * key AbacatePay signs the body with, when you also want that check
     *
     * @param string $signatureKey
     * @return WebhookSignatureInterface
     */
    public function setSignatureKey(string $signatureKey): WebhookSignatureInterface;

    /**
     * whether the notification really came from the gateway
     *
     * @return bool
     */
    public function verify(): bool;
}
