<?php

namespace PHPay\PagarMe;

use GuzzleHttp\Client;
use PHPay\PagarMe\Interface\PagarMeGatewayInterface;
use PHPay\PagarMe\Requests\PagarMeCustomerRequest;
use PHPay\PagarMe\Resources\Charge\Charge;
use PHPay\PagarMe\Resources\Customer\Customer;
use PHPay\PagarMe\Resources\Subscription\Subscription;
use PHPay\PagarMe\Resources\WebhookDelivery\WebhookDelivery;
use PHPay\Support\Customer as CustomerData;

class PagarMeGateway implements PagarMeGatewayInterface
{
    /**
     * prefix that marks a test secret key
     */
    public const TEST_KEY_PREFIX = 'sk_test_';

    /**
     * construct
     *
     * there is no $sandbox flag: Pagar.me serves test and production from the
     * same host, and the secret key decides which one answers.
     *
     * @param string $secretKey
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $secretKey,
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
        return 'Pagar.me';
    }

    /**
     * whether the credential in use is a test credential.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return str_starts_with($this->secretKey, self::TEST_KEY_PREFIX);
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
            $customer = PagarMeCustomerRequest::fromCustomer($customer);
        }

        return new Customer($this->secretKey, $customer, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->secretKey, $this->client);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->secretKey, $this->client);
    }

    /**
     * read the webhook events already delivered by the gateway.
     *
     * @return WebhookDelivery
     */
    public function webhookDeliveries(): WebhookDelivery
    {
        return new WebhookDelivery($this->secretKey, $this->client);
    }
}
