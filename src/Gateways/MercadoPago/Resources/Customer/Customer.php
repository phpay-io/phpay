<?php

namespace PHPay\MercadoPago\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\MercadoPago\Requests\MercadoPagoCustomerRequest;
use PHPay\MercadoPago\Resources\Customer\Interface\CustomerInterface;
use PHPay\MercadoPago\Traits\HasMercadoPagoClient;

/**
 * customers on Mercado Pago exist to hold saved cards — they are not a
 * prerequisite for charging, which carries the payer inline.
 *
 * the API offers no deletion, so this resource has no destroy().
 */
class Customer implements CustomerInterface
{
    /**
     * trait mercado pago client
     */
    use HasMercadoPagoClient;

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
     * @param string $accessToken
     * @param array<mixed> $customer
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $accessToken,
        private array $customer = [],
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientMercadoPagoBoot();
    }

    /**
     * create customer
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        MercadoPagoCustomerRequest::validate($this->customer);

        return $this->post('v1/customers', $this->customer);
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
        return $this->get("v1/customers/{$id}");
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
        return $this->put("v1/customers/{$id}", $this->customer);
    }

    /**
     * search customers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('v1/customers/search', $this->filter);
    }

    /**
     * find a customer by e-mail
     *
     * @param string $email
     * @return array<mixed>|null null when no customer matches
     * @throws ApiException
     */
    public function findByEmail(string $email): ?array
    {
        $found = $this->get('v1/customers/search', ['email' => $email]);

        $results = $found['results'] ?? null;

        if (!is_array($results) || empty($results)) {
            return null;
        }

        $first = reset($results);

        return is_array($first) ? $first : null;
    }

    /**
     * set search filter
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
