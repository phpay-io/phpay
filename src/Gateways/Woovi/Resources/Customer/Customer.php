<?php

namespace PHPay\Woovi\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Woovi\Requests\WooviCustomerRequest;
use PHPay\Woovi\Resources\Customer\Interface\CustomerInterface;
use PHPay\Woovi\Traits\HasWooviClient;

class Customer implements CustomerInterface
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
     * @param array<mixed> $customer
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
        private array $customer = [],
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientWooviBoot();
    }

    /**
     * create customer
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        WooviCustomerRequest::validate($this->customer);

        return $this->post('api/v1/customer', $this->customer);
    }

    /**
     * find a customer by correlationID or by the gateway id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("api/v1/customer/{$id}");
    }

    /**
     * list customers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('api/v1/customer', $this->queryParams);
    }

    /**
     * set list query params
     *
     * @param array<mixed> $queryParams
     * @return CustomerInterface
     */
    public function setQueryParams(array $queryParams): CustomerInterface
    {
        $this->queryParams = $queryParams;

        return $this;
    }
}
