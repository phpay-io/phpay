<?php

namespace PHPay\PagarMe\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagarMe\Requests\PagarMeCustomerRequest;
use PHPay\PagarMe\Resources\Customer\Interface\CustomerInterface;
use PHPay\PagarMe\Traits\HasPagarMeClient;

/**
 * customers of the Pagar.me Core API v5.
 *
 * unlike Mercado Pago and PagBank, this is a first-class customer with full
 * CRUD, usable both on its own and as the owner of an order.
 */
class Customer implements CustomerInterface
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
     * @param array<mixed> $customer
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $secretKey,
        private array $customer = [],
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagarMeBoot();
    }

    /**
     * create customer
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        PagarMeCustomerRequest::validate($this->customer);

        return $this->post('customers', $this->customer);
    }

    /**
     * find customer by id
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
     * update customer by id
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
     * list customers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('customers', $this->filter);
    }

    /**
     * list the saved cards of a customer
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function cards(string $id): array
    {
        return $this->get("customers/{$id}/cards");
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
