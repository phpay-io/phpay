<?php

namespace PHPay\Woovi\Resources\Subscription\Interface;

use PHPay\Support\{Customer as CustomerData, Money};

interface SubscriptionInterface
{
    /**
     * set the customer of the subscription
     *
     * @param array<mixed> $customer
     * @return SubscriptionInterface
     */
    public function setCustomer(CustomerData|array $customer): SubscriptionInterface;

    /**
     * set the day of the month the charge is generated
     *
     * @param int $day
     * @return SubscriptionInterface
     */
    public function setDayGenerateCharge(int $day): SubscriptionInterface;

    /**
     * create the subscription
     *
     * @param int $value amount in cents
     * @return array<mixed>
     */
    public function create(Money|int $value): array;

    /**
     * find a subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;
}
