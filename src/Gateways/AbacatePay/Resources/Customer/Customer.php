<?php

namespace PHPay\AbacatePay\Resources\Customer;

use GuzzleHttp\Client;
use PHPay\AbacatePay\Requests\AbacatePayCustomerRequest;
use PHPay\AbacatePay\Resources\Customer\Interface\CustomerInterface;
use PHPay\AbacatePay\Traits\HasAbacatePayClient;
use PHPay\Exceptions\{ApiException, ValidationException};

/**
 * customers of the AbacatePay API.
 *
 * the API exposes creation and listing only — there is no find by id, update
 * or delete, so this resource has none either.
 */
class Customer implements CustomerInterface
{
    /**
     * trait abacatepay client
     */
    use HasAbacatePayClient;

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
     * @param string $token
     * @param array<mixed> $customer
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private array $customer = [],
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientAbacatePayBoot();
    }

    /**
     * create customer
     *
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(): array
    {
        AbacatePayCustomerRequest::validate($this->customer);

        return $this->post('customer/create', $this->customer);
    }

    /**
     * list customers
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('customer/list', $this->queryParams);
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
