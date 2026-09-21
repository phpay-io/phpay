<?php

namespace PHPay\Woovi\Resources\Webhook;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Woovi\Requests\WooviWebhookRequest;
use PHPay\Woovi\Resources\Webhook\Interface\WebhookInterface;
use PHPay\Woovi\Traits\HasWooviClient;

/**
 * webhooks of the Woovi/OpenPix API.
 *
 * the only gateway besides Asaas that lets endpoints be registered by API
 * instead of only in a dashboard.
 *
 * note the path prefix: webhooks live under api/openpix/v1 while every other
 * resource is under api/v1 — a leftover of the two brands merging.
 */
class Webhook implements WebhookInterface
{
    /**
     * trait woovi client
     */
    use HasWooviClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $queryParams = [];

    /**
     * construct
     *
     * @param string $appId
     * @param array<mixed> $webhook
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
        private array $webhook = [],
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
    }

    /**
     * create webhook
     *
     * @param array<mixed> $webhook overrides the payload given to the gateway
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(array $webhook = []): array
    {
        if (!empty($webhook)) {
            $this->webhook = $webhook;
        }

        $this->webhook['isActive'] = $this->webhook['isActive'] ?? true;

        WooviWebhookRequest::validate($this->webhook);

        return $this->post('api/openpix/v1/webhook', ['webhook' => $this->webhook]);
    }

    /**
     * list webhooks
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('api/openpix/v1/webhook', $this->queryParams);
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
        return $this->delete("api/openpix/v1/webhook/{$id}");
    }

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return WebhookInterface
     */
    public function setQueryParams(array $queryParams): WebhookInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }
}
