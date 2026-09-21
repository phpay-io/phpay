<?php

namespace PHPay\PagarMe\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagarMe\Requests\PagarMeSubscriptionRequest;
use PHPay\PagarMe\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\PagarMe\Traits\HasPagarMeClient;

/**
 * plans and subscriptions of the Pagar.me Core API v5.
 *
 * a subscription may point at a plan or carry its own items and interval.
 * Prices are integers in cents.
 */
class Subscription implements SubscriptionInterface
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
    private array $subscription = [];

    /**
     * @var array<mixed>
     */
    private array $filter = [];

    /**
     * construct
     *
     * @param string $secretKey
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $secretKey,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagarMeBoot();
    }

    /**
     * attach an existing plan to the subscription
     *
     * @param string $planId
     * @return SubscriptionInterface
     */
    public function setPlan(string $planId): SubscriptionInterface
    {
        $this->subscription['plan_id'] = $planId;

        return $this;
    }

    /**
     * attach an existing customer to the subscription
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface
    {
        $this->subscription['customer_id'] = $customerId;

        unset($this->subscription['customer']);

        return $this;
    }

    /**
     * attach a customer created along with the subscription
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(array $customer): SubscriptionInterface
    {
        if (isset($customer['id']) && is_string($customer['id']) && $customer['id'] !== '') {
            return $this->setCustomerId($customer['id']);
        }

        $this->subscription['customer'] = $customer;

        unset($this->subscription['customer_id']);

        return $this;
    }

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return SubscriptionInterface
     */
    public function setFilter(array $filter = []): SubscriptionInterface
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * create subscription
     *
     * @param array<mixed> $subscription merged over what the setters built
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function create(array $subscription = []): array
    {
        $payload = array_merge($this->subscription, $subscription);

        PagarMeSubscriptionRequest::validate($payload);

        return $this->post('subscriptions', $payload);
    }

    /**
     * find subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function find(string $id): array
    {
        return $this->get("subscriptions/{$id}");
    }

    /**
     * list subscriptions
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAll(): array
    {
        return $this->get('subscriptions', $this->filter);
    }

    /**
     * cancel subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function cancel(string $id): array
    {
        return $this->request('DELETE', "subscriptions/{$id}");
    }

    /**
     * create a recurring plan
     *
     * @param array<mixed> $plan
     * @return array<mixed>
     * @throws ValidationException|ApiException
     */
    public function createPlan(array $plan): array
    {
        PagarMeSubscriptionRequest::validatePlan($plan);

        return $this->post('plans', $plan);
    }

    /**
     * find plan by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function findPlan(string $id): array
    {
        return $this->get("plans/{$id}");
    }

    /**
     * list plans
     *
     * @return array<mixed>
     * @throws ApiException
     */
    public function getAllPlans(): array
    {
        return $this->get('plans', $this->filter);
    }

    /**
     * delete plan by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function destroyPlan(string $id): array
    {
        return $this->request('DELETE', "plans/{$id}");
    }
}
