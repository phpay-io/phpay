<?php

namespace PHPay\PagBank\Resources\Subscription;

use GuzzleHttp\Client;
use PHPay\Exceptions\{ApiException, ValidationException};
use PHPay\PagBank\Requests\{PagBankCustomerRequest, PagBankSubscriptionRequest};
use PHPay\PagBank\Resources\Subscription\Interface\SubscriptionInterface;
use PHPay\PagBank\Traits\HasPagBankClient;
use PHPay\Support\Customer;

/**
 * plans and subscriptions of the PagBank subscriptions API.
 *
 * a subscription always belongs to a plan, so plan operations live here
 * instead of in a resource of their own — they are one recurring flow.
 * Amounts are integers in cents.
 */
class Subscription implements SubscriptionInterface
{
    /**
     * trait pagbank client
     */
    use HasPagBankClient;

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
     * @param string $token
     * @param bool $sandbox
     * @param Client|null $client injected http client, mainly for tests
     */
    public function __construct(
        private string $token,
        private bool $sandbox = true,
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->clientPagBankSubscriptionsBoot();
    }

    /**
     * attach an existing plan to the subscription
     *
     * @param string $planId
     * @return SubscriptionInterface
     */
    public function setPlan(string $planId): SubscriptionInterface
    {
        $this->subscription['plan'] = ['id' => $planId];

        return $this;
    }

    /**
     * attach an existing subscriber to the subscription
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface
    {
        $this->subscription['customer'] = ['id' => $customerId];

        return $this;
    }

    /**
     * attach a subscriber, created along with the subscription.
     *
     * PagBank accepts creating the subscriber inline, so no extra call is
     * needed — pass an array carrying `id` to reuse an existing one instead.
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(Customer|array $customer): SubscriptionInterface
    {
        if ($customer instanceof Customer) {
            if ($customer->id !== null) {
                return $this->setCustomerId($customer->id);
            }

            $customer = PagBankCustomerRequest::fromCustomer($customer);
        }

        $this->subscription['customer'] = $customer;

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
     * @see https://developer.pagbank.com.br/reference/criar-assinatura
     */
    public function create(array $subscription = []): array
    {
        $payload = array_merge($this->subscription, $subscription);

        PagBankSubscriptionRequest::validate($payload);

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
     * suspend subscription by id
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function suspend(string $id): array
    {
        return $this->put("subscriptions/{$id}/suspend");
    }

    /**
     * reactivate a suspended subscription
     *
     * @param string $id
     * @return array<mixed>
     * @throws ApiException
     */
    public function activate(string $id): array
    {
        return $this->put("subscriptions/{$id}/activate");
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
        return $this->put("subscriptions/{$id}/cancel");
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
        PagBankSubscriptionRequest::validatePlan($plan);

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
}
