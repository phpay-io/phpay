<?php

namespace PHPay\Asaas\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\Asaas\Requests\AsaasCustomerRequest;
use PHPay\Asaas\Resources\Customer\Interface\CustomerInterface;
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Exceptions\{ApiException, ValidationException};

class Customer implements CustomerInterface
{
    /**
     * trait asaas client
     */
    use HasAsaasClient;

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
        ?Client $client = null
    ) {
        $this->client = $client ?? $this->clientAsaasBoot();
    }

    /**
     * get all customers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('customers', $this->filter);
    }

    /**
     * get customer by id
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
     * create customer
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see available fields in https://docs.asaas.com/reference/criar-novo-cliente
     */
    public function create(): array
    {
        AsaasCustomerRequest::validate($this->customer);

        return $this->post('customers', $this->customer);
    }

    /**
     * update customer
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     * @see available fields in https://docs.asaas.com/reference/criar-novo-cliente
     */
    public function update(string $id): array
    {
        return $this->put("customers/{$id}", $this->customer);
    }

    /**
     * destroy customer
     *
     * @param string $id
     * @return bool
     * @throws ApiException
     */
    public function destroy(string $id): bool
    {
        return $this->delete("customers/{$id}");
    }

    /**
     * restore customer deleted
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function restore(string $id): array
    {
        return $this->post("customers/{$id}/restore");
    }

    /**
     * get notifications
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function getNotifications(string $id): array
    {
        return $this->get("customers/{$id}/notifications");
    }

    /**
     * set filter
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
