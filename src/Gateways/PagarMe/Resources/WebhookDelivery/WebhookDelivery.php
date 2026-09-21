<?php

namespace PHPay\PagarMe\Resources\WebhookDelivery;

use GuzzleHttp\Client;
use PHPay\Exceptions\ApiException;
use PHPay\PagarMe\Resources\WebhookDelivery\Interface\WebhookDeliveryInterface;
use PHPay\PagarMe\Traits\HasPagarMeClient;

/**
 * webhook deliveries of the Pagar.me Core API v5.
 *
 * this is NOT the SupportsWebhooks capability, and the gateway deliberately
 * does not declare it: /hooks reads the events Pagar.me already sent, while
 * registering the endpoints that receive them is done in the dashboard.
 *
 * it is reachable only from the concrete PagarMeGateway, which is how the
 * capability model makes room for what a single gateway offers.
 */
class WebhookDelivery implements WebhookDeliveryInterface
{
    /**
     * trait pagar.me client
     */
    use HasPagarMeClient;

    /**
     * client guzzle
     */
    private Client $client;

    /**
     * @var array<mixed>
     */
    private array $filter = [];

    /**
     * construct
     *
     * @param string $secretKey
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $secretKey,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagarMeBoot();
    }

    /**
     * list webhook deliveries
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('hooks', $this->filter);
    }

    /**
     * find a webhook delivery by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("hooks/{$id}");
    }

    /**
     * resend a webhook delivery
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function resend(string $id): array
    {
        return $this->post("hooks/{$id}/resend");
    }

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return WebhookDeliveryInterface
     */
    public function setFilter(array $filter = []): WebhookDeliveryInterface
    {
        $this->filter = $filter;

        return $this;
    }
}
