<?php

namespace PHPay\PagBank\Resources\Subscription\Interface;

use PHPay\Support\Customer;

interface SubscriptionInterface
{
    /**
     * attach an existing plan to the subscription
     *
     * @param string $planId
     * @return SubscriptionInterface
     */
    public function setPlan(string $planId): SubscriptionInterface;

    /**
     * attach an existing subscriber to the subscription
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface;

    /**
     * attach a subscriber, created along with the subscription
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(Customer|array $customer): SubscriptionInterface;

    /**
     * set list filter
     *
     * @param array<mixed> $filter
     * @return SubscriptionInterface
     */
    public function setFilter(array $filter = []): SubscriptionInterface;

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     */
    public function create(array $subscription = []): array;

    /**
     * find subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * list subscriptions
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * suspend subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function suspend(string $id): array;

    /**
     * reactivate a suspended subscription
     *
     * @param string $id
     * @return array<mixed>
     */
    public function activate(string $id): array;

    /**
     * cancel subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function cancel(string $id): array;

    /**
     * create a recurring plan
     *
     * @param array<mixed> $plan
     * @return array<mixed>
     */
    public function createPlan(array $plan): array;

    /**
     * find plan by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function findPlan(string $id): array;

    /**
     * list plans
     *
     * @return array<mixed>
     */
    public function getAllPlans(): array;
}
