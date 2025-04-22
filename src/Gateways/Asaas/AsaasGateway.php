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
     * client guzzle
     */
    public Client $client;

    /**
     * customer data
     *
     * @var Customer
     */
    public Customer $customer;

    /**
     * construct
     *
     * @param string $token
     * @param bool $sandbox
     */
    public function __construct(
        private string $token,
        private bool $sandbox = true
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
        $this->customer = new Customer($this->token, $customer, $this->sandbox);

        return $this->customer;
    }

    /**
     * charge
     *
     * @return Charge
     */
    public function charge(): Charge
    {
        return new Charge($this->token, $this->sandbox);
    }

    /**
     * webhook
     *
     * @param array<mixed> $webhook
     * @return Webhook
     */
    public function webhook(array $webhook = []): Webhook
    {
        return new Webhook($this->token, $webhook, $this->sandbox);
    }

    /**
     * pix
     *
     * @param array<mixed> $pix
     * @return Pix
     */
    public function pix(array $pix = []): Pix
    {
        return new Pix($this->token, $this->sandbox);
    }

    public function subscription(): Subscription
    {
        return new Subscription($this->token, $this->sandbox);
    }
}
