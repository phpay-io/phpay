<?php

namespace PHPay\Asaas\Resources\Webhook;

use GuzzleHttp\Client;
use PHPay\Asaas\Resources\Webhook\Interface\WebhookInterface;
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Exceptions\ApiException;

class Webhook implements WebhookInterface
{
    /**
     * trait with methods to interact with asaas API
     */
    use HasAsaasClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * construct
     *
     * @param string $token
     * @param array<mixed> $webhook
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private array $webhook = [],
        private bool $sandbox = true,
        ?Client $client = null
    ) {
        $this->client = $client ?? $this->clientAsaasBoot();
    }

    /**
     * create webhook
     *
     * @param array<mixed> $webhook overrides the payload given to the gateway
     * @return array<mixed>
     * @throws ApiException
     * @see available fields in https://docs.asaas.com/reference/criar-novo-webhook
     */
    public function create(array $webhook = []): array
    {
        if (!empty($webhook)) {
            $this->webhook = $webhook;
        }

        return $this->post('webhooks', $this->webhook);
    }

    /**
     * get all webhooks
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('webhooks');
    }

    /**
     * get webhook by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("webhooks/{$id}");
    }

    /**
     * update webhook by id
     *
     * @param string $id
     * @param array<mixed> $webhook
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $id, array $webhook = []): array
    {
        return $this->put("webhooks/{$id}", $webhook);
    }

    /**
     * delete webhook by id
     *
     * @param string $id
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $id): bool
    {
        return $this->delete("webhooks/{$id}");
    }
}
