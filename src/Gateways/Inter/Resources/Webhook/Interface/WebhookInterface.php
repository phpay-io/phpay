<?php

namespace PHPay\Inter\Resources\Webhook\Interface;

use PHPay\Exceptions\{ApiException, ValidationException};

interface WebhookInterface
{
    /**
     * configure the webhook of a Pix key.
     *
     * @param array<mixed> $webhook
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(array $webhook = []): array;

    /**
     * find the webhook of a Pix key.
     *
     * @param string $key
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $key): array;

    /**
     * remove the webhook of a Pix key.
     *
     * @param string $key
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $key): bool;

    /**
     * the callbacks Inter tried to deliver.
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function callbacks(): array;

    /**
     * ask Inter to deliver callbacks again.
     *
     * @param string $key
     * @param array<int, string> $txids
     * @return array<mixed>
     * @throws ApiException
     */
    public function retryCallbacks(string $key, array $txids): array;

    /**
     * set list filters.
     *
     * @param array<mixed> $queryParams
     * @return WebhookInterface
     */
    public function setQueryParams(array $queryParams): WebhookInterface;
}
