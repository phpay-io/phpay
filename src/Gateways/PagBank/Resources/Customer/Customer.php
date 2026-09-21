<?php

namespace PHPay\PagBank\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagBank\Requests\PagBankCustomerRequest;
use PHPay\PagBank\Resources\Customer\Interface\CustomerInterface;
use PHPay\PagBank\Traits\HasPagBankClient;

/**
 * subscribers of the PagBank subscriptions API.
 *
 * this is not the customer of an order: on the Orders API the customer is a
 * field of the order itself. A subscriber exists to be charged on a recurring
 * plan, which is why this resource lives on the subscriptions host.
 */
class Customer implements CustomerInterface
{
    /**
     * trait pagbank client
     */
    use HasPagBankClient;

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
     * @param string $token
     * @param array<mixed> $customer
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private array $customer = [],
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagBankSubscriptionsBoot();
    }

    /**
     * create subscriber
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        PagBankCustomerRequest::validate($this->customer);

        return $this->post('customers', $this->customer);
    }

    /**
     * find subscriber by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("customers/{$id}");
    }

    /**
     * update subscriber by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function update(string $id): array
    {
        return $this->put("customers/{$id}", $this->customer);
    }

    /**
     * list subscribers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('customers', $this->filter);
    }

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return CustomerInterface
     */
    public function setFilter(array $filter = []): CustomerInterface
    {
        $this->filter = $filter;

        return $this;
    }
}
