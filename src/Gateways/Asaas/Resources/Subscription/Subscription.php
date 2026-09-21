<?php

namespace PHPay\Asaas\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Asaas\Requests\AsaasCustomerRequest;
use PHPay\Asaas\Resources\Customer\Customer;
use PHPay\Asaas\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\Asaas\Resources\Subscription\Requests\StoreSubscriptionAsaasRequest;
use PHPay\Asaas\Traits\HasAsaasClient;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\Support\Customer as CustomerData;

class Subscription implements SubscriptionInterface
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
     * gateway customer the subscription belongs to
     */
    private ?string $customerId = null;

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
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientAsaasBoot();
    }

    /**
     * attach an existing gateway customer to the subscription.
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * attach a customer to the subscription.
     *
     * when the array carries an `id`, that customer is reused; otherwise a new
     * customer is created on the gateway. pass an id — or use setCustomerId() —
     * to avoid creating a duplicate customer on every subscription.
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     * @throws ValidationException|ApiException
     */
    public function setCustomer(CustomerData|array $customer): SubscriptionInterface
    {
        if ($customer instanceof CustomerData) {
            if ($customer->id !== null) {
                return $this->setCustomerId($customer->id);
            }

            $customer = AsaasCustomerRequest::fromCustomer($customer);
        }

        if (isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '') {
            return $this->setCustomerId($customer['id']);
        }

        $created = (new Customer(
            $this->token,
            $customer,
            $this->sandbox,
            $this->client
        ))->create();

        if (!isset($created['id']) || !is_string($created['id'])) {
            throw new ApiException(
                'Asaas: a criação do cliente não retornou um id.',
                'Asaas',
                0,
                $created
            );
        }

        return $this->setCustomerId($created['id']);
    }

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     * @throws ValidationException|ApiException
     * @see fields available in https://docs.asaas.com/reference/criar-nova-assinatura
     */
    public function create(array $subscription): array
    {
        $subscription['customer'] = $subscription['customer'] ?? $this->customerId;

        StoreSubscriptionAsaasRequest::validate($subscription);

        return $this->post('subscriptions', $subscription);
    }
}
