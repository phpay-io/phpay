<?php

namespace PHPay\Asaas\Resources\Subscription\Interface;

use PHPay\Support\Customer as CustomerData;

interface SubscriptionInterface
{
    /**
     * attach an existing gateway customer to the subscription.
     *
     * @param string $customerId
     * @return SubscriptionInterface
     */
    public function setCustomerId(string $customerId): SubscriptionInterface;

    /**
     * attach a customer to the subscription, reusing it when an id is given.
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(CustomerData|array $customer): SubscriptionInterface;

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     */
    public function create(array $subscription): array;
}
