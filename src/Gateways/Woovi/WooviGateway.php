<?php

namespace PHPay\Woovi;

use GuzzleHttp\Client;
use PHPay\Support\Customer as CustomerData;
use PHPay\Woovi\Interface\WooviGatewayInterface;
use PHPay\Woovi\Requests\WooviCustomerRequest;
use PHPay\Woovi\Resources\Charge\Charge;
use PHPay\Woovi\Resources\Customer\Customer;
use PHPay\Woovi\Resources\Pix\Pix;
use PHPay\Woovi\Resources\Subscription\Subscription;
use PHPay\Woovi\Resources\Webhook\Webhook;

class WooviGateway implements WooviGatewayInterface
{
    /**
     * construct
     *
     * @param string $appId the AppID, sent raw in Authorization
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $appId,
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
        return 'Woovi';
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
            $customer = WooviCustomerRequest::fromCustomer($customer);
        }

        return new Customer($this->appId, $customer, $this->sandbox, $this->client);
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->appId, $this->sandbox, $this->client);
    }

    /**
     * webhook
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook($this->appId, $webhook, $this->sandbox, $this->client);
    }

    /**
     * pix
     *
     * @return Pix
     */
    public function pix(): Pix
    {
        return new Pix($this->appId, $this->sandbox, $this->client);
    }

    /**
     * subscription
     *
     * @return Subscription
     */
    public function subscription(): Subscription
    {
        return new Subscription($this->appId, $this->sandbox, $this->client);
    }
}
