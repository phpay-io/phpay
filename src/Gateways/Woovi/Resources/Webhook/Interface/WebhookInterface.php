<?php

namespace PHPay\Woovi\Resources\Webhook\Interface;

interface WebhookInterface
{
    /**
     * create webhook
     *
     * @param array<mixed> $webhook
     * @return array<mixed>
     */
    public function create(array $webhook = []): array;

    /**
     * list webhooks
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * delete webhook by id
     *
     * @param string $id
     * @return bool
     */
    public function destroy(string $id): bool;

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return WebhookInterface
     */
    public function setQueryParams(array $queryParams): WebhookInterface;
}
