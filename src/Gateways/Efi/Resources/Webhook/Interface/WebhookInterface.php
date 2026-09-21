<?php

namespace PHPay\Efi\Resources\Webhook\Interface;

interface WebhookInterface
{
    /**
     * configure the webhook of a Pix key
     *
     * @param array<mixed> $webhook
     * @return array<mixed>
     */
    public function create(array $webhook = []): array;

    /**
     * skip the mTLS check Efí makes on your server
     *
     * @param bool $skip
     * @return WebhookInterface
     */
    public function skipMtlsChecking(bool $skip = true): WebhookInterface;

    /**
     * find the webhook of a Pix key
     *
     * @param string $key
     * @return array<mixed>
     */
    public function find(string $key): array;

    /**
     * list webhooks
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * remove the webhook of a Pix key
     *
     * @param string $key
     * @return bool
     */
    public function destroy(string $key): bool;

    /**
     * set list filters
     *
     * @param array<mixed> $queryParams
     * @return WebhookInterface
     */
    public function setQueryParams(array $queryParams): WebhookInterface;
}
