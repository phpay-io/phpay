<?php

namespace PHPay\Asaas\Resources\Subscription\Interface;

use PHPay\Asaas\Resources\Subscription\Subscription;

interface SubscriptionInterface
{
    public function __construct(string $token, bool $sandbox = true);

    /**
     * set customer
     *
     * @param array<mixed> $customer
     * @return Subscription
     */
    public function setCustomer(array $customer): Subscription;

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     */
    public function create(array $subscription): array;

    // public function findAll();
    // public function find(string $subscriptionId);
    // public function update(string $subscriptionId, array $subscription);
    // public function destroy(string $subscriptionId);
    // public function findCharges(string $subscriptionId);
    // public function generateCarnet(string $subscriptionId);
    // public function nfeSettings(string $subscriptionId, array $nfeSettings);
    // public function findNfeSettings(string $subscriptionId);
    // public function updateNfeSettings(string $subscriptionId, array $nfeSettings);
    // public function destroyNfeSettings(string $subscriptionId);
    // public function findNfes(string $subscriptionId);
}
