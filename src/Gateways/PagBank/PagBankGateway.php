<?php

namespace PHPay\PagBank;

use GuzzleHttp\Client;
use PHPay\PagBank\Interface\PagBankGatewayInterface;
use PHPay\PagBank\Requests\PagBankCustomerRequest;
use PHPay\PagBank\Resources\Charge\Charge;
use PHPay\PagBank\Resources\Customer\Customer;
use PHPay\PagBank\Resources\Subscription\Subscription;
use PHPay\Support\Customer as CustomerData;

class PagBankGateway implements PagBankGatewayInterface
{
    /**
     * construct
     *
     * @param string $token
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private bool $sandbox = true,
        private ?Client $client = null,
    ) {
    }

    /**
     * gateway name
     *
     * @return string
     */
    public function name(): string
    {
        return 'PagBank';
    }

    /**
     * customer
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(CustomerData|array $customer = []): Customer
    {
        if ($customer instanceof CustomerData) {
            $customer = PagBankCustomerRequest::fromCustomer($customer);
        }

        return new Customer($this->token, $customer, $this->sandbox, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->token, $this->sandbox, $this->client);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->token, $this->sandbox, $this->client);
    }
}
