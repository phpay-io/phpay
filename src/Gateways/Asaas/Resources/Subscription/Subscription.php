<?php

namespace PHPay\Asaas\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Gateways\Asaas\Resources\Subscription\Requests\StoreSubscriptionAsaasRequest;

class Subscription implements SubscriptionInterface
{
    use HasAsaasClient;

    private Client $client;

    protected ?string $customerId = null;

    public function __construct(
        private string $token,
        private bool $sandbox = true,
    ) {
        $this->client = $this->clientAsaasBoot();
    }

    /**
     * set customer
     *
     * @param array<mixed> $customer
     * @return Subscription
     */
    public function setCustomer(array $customer): Subscription
    {
        $customer = (new Customer(
            $this->token,
            $customer,
            $this->sandbox
        ))->create();

        $this->customerId = $customer['id'];

        return $this;
    }

    public function create(array $subscription): array
    {
        $subscription['customer'] = $this->customerId;

        StoreSubscriptionAsaasRequest::validate($subscription);

        return $this->post('subscriptions', $subscription);
    }

    // public function findAll()
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function find(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function update(string $subscriptionId, array $subscription)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function destroy(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function findCharges(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function generateCarnet(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function nfeSettings(string $subscriptionId, array $nfeSettings)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function findNfeSettings(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function updateNfeSettings(string $subscriptionId, array $nfeSettings)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function destroyNfeSettings(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }

    // public function findNfes(string $subscriptionId)
    // {
    //     throw new \Exception('Method not implemented');
    // }
}
