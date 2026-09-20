<?php

namespace PHPay\Asaas;

use GuzzleHttp\Client;
use PHPay\Asaas\Interface\AsaasGatewayInterface;
use PHPay\Asaas\Resources\Charge\Charge;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Pix\Pix;
use PHPay\Asaas\Resources\Subscription\Subscription;
use PHPay\Asaas\Resources\Webhook\Webhook;

class AsaasGateway implements AsaasGatewayInterface
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
        private ?Client $client = null
    ) {
    }

    /**
     * customer
     *
     * @param array<mixed> $customer
     * @return Customer
     */
    public function customer(array $customer = []): Customer
    {
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
     * webhook
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook($this->token, $webhook, $this->sandbox, $this->client);
    }

    /**
     * pix
     *
     * @return Pix
     */
    public function pix(): Pix
    {
        return new Pix($this->token, $this->sandbox, $this->client);
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
