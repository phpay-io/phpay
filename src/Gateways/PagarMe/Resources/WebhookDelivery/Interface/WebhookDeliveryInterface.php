<?php

namespace PHPay\PagarMe\Resources\WebhookDelivery\Interface;

interface WebhookDeliveryInterface
{
    /**
     * list webhook deliveries
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * find a webhook delivery by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * resend a webhook delivery
     *
     * @param string $id
     * @return array<mixed>
     */
    public function resend(string $id): array;

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return WebhookDeliveryInterface
     */
    public function setFilter(array $filter = []): WebhookDeliveryInterface;
}
