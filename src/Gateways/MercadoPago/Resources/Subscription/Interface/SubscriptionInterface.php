<?php

namespace PHPay\MercadoPago\Resources\Subscription\Interface;

interface SubscriptionInterface
{
    /**
     * set the payer e-mail of the subscription
     *
     * @param string $email
     * @return SubscriptionInterface
     */
    public function setPayerEmail(string $email): SubscriptionInterface;

    /**
     * attach an existing preapproval plan
     *
     * @param string $planId
     * @return SubscriptionInterface
     */
    public function setPlan(string $planId): SubscriptionInterface;

    /**
     * set search query params
     *
     * @param array<mixed> $queryParams
     * @return SubscriptionInterface
     */
    public function setQueryParams(array $queryParams): SubscriptionInterface;

    /**
     * create subscription
     *
     * @param array<mixed> $subscription
     * @return array<mixed>
     */
    public function create(array $subscription): array;

    /**
     * find subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function find(string $id): array;

    /**
     * search subscriptions
     *
     * @return array<mixed>
     */
    public function getAll(): array;

    /**
     * update subscription by id
     *
     * @param string $id
     * @param array<mixed> $subscription
     * @return array<mixed>
     */
    public function update(string $id, array $subscription): array;

    /**
     * pause subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function pause(string $id): array;

    /**
     * cancel subscription by id
     *
     * @param string $id
     * @return array<mixed>
     */
    public function cancel(string $id): array;
}
